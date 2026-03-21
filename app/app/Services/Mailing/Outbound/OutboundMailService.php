<?php

namespace App\Services\Mailing\Outbound;

use App\Jobs\Mailing\DispatchMailMessageJob;
use App\Models\MailboxAccount;
use App\Models\MailCampaign;
use App\Models\MailDraft;
use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Models\MailThread;
use App\Services\Mailing\Contracts\MailGatewayClient;
use App\Services\Mailing\EmailContentService;
use App\Services\Mailing\MailEventLogger;
use App\Services\Mailing\MailUnsubscribeService;
use App\Services\Mailing\SmtpProviderService;
use App\Services\Mailing\Tracking\MailTrackingService;
use App\Services\SettingsStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OutboundMailService
{
    public function __construct(
        private readonly SettingsStore $settingsStore,
        private readonly MailEventLogger $eventLogger,
        private readonly MailTrackingService $trackingService,
        private readonly EmailContentService $emailContentService,
        private readonly MailUnsubscribeService $mailUnsubscribeService,
        private readonly SmtpProviderService $smtpProviderService,
    ) {}

    public function queueCampaign(MailDraft $draft, MailCampaign $campaign, Carbon $requestedStart): MailCampaign
    {
        $mailbox = $campaign->mailboxAccount()->firstOrFail();
        $slots = $this->plannedSlots($mailbox, $requestedStart, $campaign->recipients()->count());
        $firstScheduledFor = $slots[0] ?? $requestedStart;
        $draft->loadMissing('attachments');
        $campaign->load('recipients.contact', 'recipients.organization');
        $mailSettings = $this->settingsStore->get('mail', config('mailing.defaults.mail', []));

        DB::transaction(function () use ($campaign, $draft, $slots, $mailbox, $mailSettings, $firstScheduledFor): void {
            foreach ($campaign->recipients->values() as $index => $recipient) {
                $scheduledFor = $slots[$index] ?? $firstScheduledFor->copy();
                $message = $this->createQueuedMessage($draft, $recipient, $mailbox, $scheduledFor, $mailSettings);

                $recipient->forceFill([
                    'status' => 'queued',
                    'scheduled_for' => $scheduledFor,
                    'last_event_at' => now(),
                ])->save();

                DispatchMailMessageJob::dispatch([
                    'mailbox_account_id' => $mailbox->id,
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'thread_id' => $message->thread_id,
                    'mail_message_id' => $message->id,
                    'idempotency_key' => 'dispatch.'.$message->id,
                ])->delay($scheduledFor)->afterCommit();

                $this->eventLogger->log(
                    'mail_message.queued',
                    [
                        'mail_message_id' => $message->id,
                        'outbound_provider' => $campaign->outbound_provider,
                        'scheduled_for' => $scheduledFor->toIso8601String(),
                        'queue' => config('mailing.queues.outbound'),
                    ],
                    [
                        'mailbox_account_id' => $mailbox->id,
                        'campaign_id' => $campaign->id,
                        'recipient_id' => $recipient->id,
                        'thread_id' => $message->thread_id,
                        'message_id' => $message->id,
                    ],
                    'mail_message.queued.'.$message->id,
                );
            }

            $campaign->forceFill([
                'status' => $firstScheduledFor->greaterThan(now()) ? 'scheduled' : 'queued',
                'started_at' => null,
                'completed_at' => null,
            ])->save();
        });

        return $campaign->fresh('recipients');
    }

    public function dispatchQueuedMessage(array $payload, MailGatewayClient $gatewayClient): void
    {
        $message = MailMessage::query()
            ->with(['recipient.campaign', 'mailboxAccount', 'thread', 'attachments'])
            ->findOrFail($payload['mail_message_id']);

        $recipient = $message->recipient;
        $campaign = $recipient?->campaign;

        if ($recipient === null || $campaign === null) {
            return;
        }

        if (! $this->canDispatchQueuedMessage($message, $recipient, $campaign)) {
            return;
        }

        if ($this->shouldAutoStop($campaign)) {
            $this->markRecipientCancelled($message, $recipient, $campaign);

            return;
        }

        $recipient->forceFill([
            'status' => 'sending',
            'last_event_at' => now(),
        ])->save();

        $campaign->forceFill([
            'status' => 'sending',
            'started_at' => $campaign->started_at ?? now(),
        ])->save();

        $this->eventLogger->log(
            'mail_message.sending',
            ['mail_message_id' => $message->id],
            [
                'mailbox_account_id' => $message->mailbox_account_id,
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'thread_id' => $message->thread_id,
                'message_id' => $message->id,
            ],
            'mail_message.sending.'.$message->id,
        );

        $gatewayPayload = $this->gatewayPayload($message);
        $result = $gatewayClient->dispatchMessage($gatewayPayload);

        $this->eventLogger->log(
            'mail_message.dispatch_requested',
            [
                'driver' => $result['driver'] ?? config('mailing.gateway.driver'),
                'outbound_provider' => $gatewayPayload['provider'],
                'result' => array_diff_key($result, array_flip(['html_body', 'text_body', 'password', 'mailbox_password'])),
            ],
            [
                'mailbox_account_id' => $message->mailbox_account_id,
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'thread_id' => $message->thread_id,
                'message_id' => $message->id,
            ],
            $payload['idempotency_key'] ?? null,
        );

        if ($result['success'] ?? false) {
            $this->markSent($message, $recipient, $campaign, $result);

            return;
        }

        $this->markFailed($message, $recipient, $campaign, $result);
    }

    private function createQueuedMessage(
        MailDraft $draft,
        MailRecipient $recipient,
        MailboxAccount $mailbox,
        Carbon $scheduledFor,
        array $mailSettings,
    ): MailMessage {
        $thread = MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailbox->id,
            'organization_id' => $recipient->organization_id,
            'contact_id' => $recipient->contact_id,
            'subject_canonical' => Str::lower(trim($draft->subject)),
            'first_message_at' => $scheduledFor,
            'last_message_at' => $scheduledFor,
            'last_direction' => 'out',
            'reply_received' => false,
            'auto_reply_received' => false,
        ]);

        $messageIdHeader = $this->messageIdHeader($mailbox->email);
        $trackingId = (string) Str::uuid();
        $preparedBodies = $this->emailContentService->prepareBodies(
            $draft->html_body,
            $draft->text_body,
            $draft->signature_snapshot ?: ($mailSettings['global_signature_html'] ?? null),
            $mailSettings['global_signature_text'] ?? null,
        );
        $trackedBodies = $this->trackingService->prepareOutboundBodies(
            $preparedBodies['html_body'],
            $preparedBodies['text_body'],
            $trackingId,
        );
        $headers = [
            'Message-ID' => $messageIdHeader,
            'In-Reply-To' => null,
            'References' => null,
            'X-Aegis-Tracking-Id' => $trackingId,
            'tracking' => $trackedBodies['tracking'],
            'transport' => [
                'provider' => $draft->outbound_provider,
            ],
        ];

        if ($draft->mode === 'bulk') {
            $headers = array_replace($headers, $this->bulkHeaders($recipient));
        }

        $message = MailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_account_id' => $mailbox->id,
            'recipient_id' => $recipient->id,
            'direction' => 'out',
            'message_id_header' => $messageIdHeader,
            'in_reply_to_header' => null,
            'references_header' => null,
            'aegis_tracking_id' => $trackingId,
            'from_email' => $mailSettings['sender_email'] ?: $mailbox->email,
            'to_emails' => [$recipient->email],
            'subject' => $draft->subject,
            'html_body' => $trackedBodies['html_body'],
            'text_body' => $trackedBodies['text_body'],
            'headers_json' => $headers,
            'classification' => 'unknown',
        ]);

        foreach ($draft->attachments as $attachment) {
            $message->attachments()->create([
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'storage_disk' => $attachment->storage_disk,
                'storage_path' => $attachment->storage_path,
                'content_id' => $attachment->content_id,
                'disposition' => $attachment->disposition,
            ]);
        }

        return $message->fresh('attachments');
    }

    private function plannedSlots(MailboxAccount $mailbox, Carbon $requestedStart, int $count): array
    {
        $general = $this->settingsStore->get('general', config('mailing.defaults.general', []));
        $mail = $this->settingsStore->get('mail', config('mailing.defaults.mail', []));
        $start = $this->applySendWindow($requestedStart->copy(), $mail);
        $slots = [];
        $cursor = $start->copy();
        $effectiveDelay = max(1, (int) ($general['min_delay_seconds'] ?? 60));

        if ((bool) ($general['slow_mode_enabled'] ?? false)) {
            $effectiveDelay *= 2;
        }

        for ($i = 0; $i < $count; $i++) {
            if ($i > 0) {
                $cursor = $cursor->copy()->addSeconds($effectiveDelay + $this->jitter($i, $general));
            }

            $cursor = $this->applySendWindow($cursor, $mail);
            $cursor = $this->respectCeilings($mailbox, $cursor, $general, $mail, $slots);
            $slots[] = $cursor->copy();
        }

        return $slots;
    }

    private function respectCeilings(MailboxAccount $mailbox, Carbon $candidate, array $general, array $mail, array $plannedSlots): Carbon
    {
        $dailyLimit = max(1, (int) ($general['daily_limit_default'] ?? 100));
        $hourlyLimit = max(1, (int) ($general['hourly_limit_default'] ?? 12));

        while (true) {
            $plannedDailyCount = collect($plannedSlots)
                ->filter(fn (Carbon $slot) => $slot->toDateString() === $candidate->toDateString())
                ->count();

            $dailyCount = MailRecipient::query()
                ->whereHas('campaign', fn ($query) => $query->where('mailbox_account_id', $mailbox->id))
                ->whereDate('scheduled_for', $candidate->toDateString())
                ->whereIn('status', ['queued', 'sending', 'sent'])
                ->count() + $plannedDailyCount;

            if ($dailyCount >= $dailyLimit) {
                $candidate = $this->applySendWindow($candidate->copy()->addDay()->startOfDay(), $mail);

                continue;
            }

            $hourlyStart = $candidate->copy()->startOfHour();
            $hourlyEnd = $hourlyStart->copy()->endOfHour();
            $plannedHourlyCount = collect($plannedSlots)
                ->filter(fn (Carbon $slot) => $slot->format('Y-m-d H') === $candidate->format('Y-m-d H'))
                ->count();

            $hourlyCount = MailRecipient::query()
                ->whereHas('campaign', fn ($query) => $query->where('mailbox_account_id', $mailbox->id))
                ->whereBetween('scheduled_for', [$hourlyStart, $hourlyEnd])
                ->whereIn('status', ['queued', 'sending', 'sent'])
                ->count() + $plannedHourlyCount;

            if ($hourlyCount >= $hourlyLimit) {
                $candidate = $this->applySendWindow($candidate->copy()->addHour()->startOfHour(), $mail);

                continue;
            }

            return $candidate;
        }
    }

    private function applySendWindow(Carbon $candidate, array $mail): Carbon
    {
        [$startHour, $startMinute] = explode(':', $mail['send_window_start'] ?? '09:00');
        [$endHour, $endMinute] = explode(':', $mail['send_window_end'] ?? '18:00');
        $windowStart = $candidate->copy()->setTime((int) $startHour, (int) $startMinute);
        $windowEnd = $candidate->copy()->setTime((int) $endHour, (int) $endMinute);

        if ($candidate->lt($windowStart)) {
            return $windowStart;
        }

        if ($candidate->gte($windowEnd)) {
            return $candidate->copy()->addDay()->setTime((int) $startHour, (int) $startMinute);
        }

        return $candidate;
    }

    private function jitter(int $index, array $general): int
    {
        $min = (int) ($general['jitter_min_seconds'] ?? 5);
        $max = (int) ($general['jitter_max_seconds'] ?? 20);

        if ($max <= $min) {
            return $min;
        }

        return $min + (($index * 7) % (($max - $min) + 1));
    }

    private function gatewayPayload(MailMessage $message): array
    {
        $mailbox = $message->mailboxAccount;
        $provider = $message->recipient?->campaign?->outbound_provider ?: $this->smtpProviderService->activeProvider();
        $smtpConnection = $this->smtpProviderService->runtimeConfiguration($provider, $mailbox);

        return [
            'mailbox_account_id' => $mailbox->id,
            'mail_message_id' => $message->id,
            'thread_id' => $message->thread_id,
            'campaign_id' => $message->recipient?->campaign_id,
            'recipient_id' => $message->recipient_id,
            'provider' => $provider,
            'email' => $mailbox->email,
            'username' => $smtpConnection['smtp_username'],
            'password' => $smtpConnection['smtp_password'],
            'smtp_host' => $smtpConnection['smtp_host'],
            'smtp_port' => $smtpConnection['smtp_port'],
            'smtp_secure' => $smtpConnection['smtp_secure'],
            'from_email' => $message->from_email,
            'from_name' => $mailbox->display_name,
            'to_emails' => $message->to_emails,
            'subject' => $message->subject,
            'html_body' => $message->html_body,
            'text_body' => $message->text_body,
            'message_id_header' => $message->message_id_header,
            'in_reply_to_header' => $message->in_reply_to_header,
            'references_header' => $message->references_header,
            'aegis_tracking_id' => $message->aegis_tracking_id,
            'headers_json' => $message->headers_json,
            'attachments' => $message->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'storage_disk' => $attachment->storage_disk,
                'storage_path' => $attachment->storage_path,
                'content_id' => $attachment->content_id,
                'disposition' => $attachment->disposition,
            ])->all(),
        ];
    }

    private function markSent(MailMessage $message, MailRecipient $recipient, MailCampaign $campaign, array $result): void
    {
        $sentAt = isset($result['accepted_at']) ? Carbon::parse($result['accepted_at'])->timezone(config('app.timezone')) : now();
        $messageIdHeader = $result['message_id_header'] ?? $message->message_id_header;
        $headersJson = $this->mergeGatewayHeaders($message->headers_json ?? [], $result);

        DB::transaction(function () use ($message, $recipient, $campaign, $sentAt, $messageIdHeader, $headersJson): void {
            $message->forceFill([
                'message_id_header' => $messageIdHeader,
                'sent_at' => $sentAt,
                'headers_json' => $headersJson,
            ])->save();

            $message->thread->forceFill([
                'last_message_at' => $sentAt,
                'last_direction' => 'out',
            ])->save();

            $recipient->forceFill([
                'status' => 'sent',
                'sent_at' => $sentAt,
                'last_event_at' => $sentAt,
            ])->save();

            $this->eventLogger->log(
                'mail_message.sent',
                [
                    'mail_message_id' => $message->id,
                    'outbound_provider' => $campaign->outbound_provider,
                    'accepted_at' => $sentAt->toIso8601String(),
                    'message_id_header' => $messageIdHeader,
                ],
                [
                    'mailbox_account_id' => $message->mailbox_account_id,
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'thread_id' => $message->thread_id,
                    'message_id' => $message->id,
                ],
                'mail_message.sent.'.$message->id,
            );

            $this->refreshCampaignStatus($campaign);
        });
    }

    private function markFailed(MailMessage $message, MailRecipient $recipient, MailCampaign $campaign, array $result): void
    {
        $messageIdHeader = $result['message_id_header'] ?? $message->message_id_header;
        $headersJson = $this->mergeGatewayHeaders($message->headers_json ?? [], $result, false);

        DB::transaction(function () use ($message, $recipient, $campaign, $result, $messageIdHeader, $headersJson): void {
            $message->forceFill([
                'message_id_header' => $messageIdHeader,
                'headers_json' => $headersJson,
            ])->save();

            $recipient->forceFill([
                'status' => 'failed',
                'last_event_at' => now(),
            ])->save();

            $this->eventLogger->log(
                'mail_message.failed',
                [
                    'mail_message_id' => $message->id,
                    'outbound_provider' => $campaign->outbound_provider,
                    'message' => $result['message'] ?? 'Envoi rejeté par la passerelle.',
                ],
                [
                    'mailbox_account_id' => $message->mailbox_account_id,
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'thread_id' => $message->thread_id,
                    'message_id' => $message->id,
                ],
                'mail_message.failed.'.$message->id,
            );

            $this->refreshCampaignStatus($campaign);
        });
    }

    private function shouldAutoStop(MailCampaign $campaign): bool
    {
        $general = $this->settingsStore->get('general', config('mailing.defaults.general', []));
        $failedLimit = max(1, (int) ($general['stop_on_consecutive_failures'] ?? 5));
        $hardBounceLimit = max(1, (int) ($general['stop_on_hard_bounce_threshold'] ?? 3));

        $failedCount = $campaign->recipients()->where('status', 'failed')->count();
        $hardBounceCount = $campaign->recipients()->where('status', 'hard_bounced')->count();

        return $failedCount >= $failedLimit
            || $hardBounceCount >= $hardBounceLimit
            || $campaign->trashed()
            || in_array($campaign->status, ['cancelled', 'failed'], true);
    }

    private function canDispatchQueuedMessage(MailMessage $message, MailRecipient $recipient, MailCampaign $campaign): bool
    {
        if (! $campaign->trashed() && $recipient->status === 'queued' && in_array($campaign->status, ['queued', 'scheduled', 'sending'], true)) {
            return true;
        }

        $this->eventLogger->log(
            'mail_message.dispatch_skipped',
            [
                'mail_message_id' => $message->id,
                'recipient_status' => $recipient->status,
                'campaign_status' => $campaign->status,
                'reason' => 'message_is_no_longer_dispatchable',
            ],
            [
                'mailbox_account_id' => $message->mailbox_account_id,
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'thread_id' => $message->thread_id,
                'message_id' => $message->id,
            ],
            'mail_message.dispatch_skipped.'.$message->id.'.'.$recipient->status.'.'.$campaign->status,
        );

        return false;
    }

    private function markRecipientCancelled(MailMessage $message, MailRecipient $recipient, MailCampaign $campaign): void
    {
        DB::transaction(function () use ($message, $recipient, $campaign): void {
            $campaign->forceFill(['status' => 'failed'])->save();

            $recipient->forceFill([
                'status' => 'cancelled',
                'last_event_at' => now(),
            ])->save();

            $this->eventLogger->log(
                'mail_campaign.auto_stopped',
                [
                    'campaign_id' => $campaign->id,
                    'message' => 'Seuil d\'arrêt automatique atteint avant l\'envoi.',
                ],
                [
                    'mailbox_account_id' => $message->mailbox_account_id,
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'thread_id' => $message->thread_id,
                    'message_id' => $message->id,
                ],
                'mail_campaign.auto_stopped.'.$campaign->id,
            );
        });
    }

    private function refreshCampaignStatus(MailCampaign $campaign): void
    {
        $campaign->refresh();

        if ($campaign->trashed() || $campaign->status === 'cancelled') {
            $campaign->forceFill([
                'status' => 'cancelled',
                'completed_at' => $campaign->completed_at ?? now(),
            ])->save();

            return;
        }

        $remaining = $campaign->recipients()->whereIn('status', ['queued', 'sending', 'scheduled', 'draft'])->count();
        $failed = $campaign->recipients()->where('status', 'failed')->count();

        if ($remaining > 0) {
            $campaign->forceFill(['status' => 'sending'])->save();

            return;
        }

        $campaign->forceFill([
            'status' => $failed > 0 ? 'failed' : 'sent',
            'completed_at' => now(),
        ])->save();
    }

    private function mergeGatewayHeaders(array $existingHeaders, array $result, bool $successful = true): array
    {
        $sanitizedResult = array_diff_key($result, array_flip(['password', 'mailbox_password']));
        $gatewayHeaders = is_array($result['headers_json'] ?? null) ? $result['headers_json'] : [];

        if (isset($result['message_id_header']) && is_string($result['message_id_header']) && $result['message_id_header'] !== '') {
            $gatewayHeaders['Message-ID'] = $result['message_id_header'];
        }

        return array_replace($existingHeaders, $gatewayHeaders, [
            $successful ? 'gateway' : 'gateway_error' => $sanitizedResult,
        ]);
    }

    private function messageIdHeader(string $senderEmail): string
    {
        $domain = Str::after($senderEmail, '@');

        if ($domain === '' || ! Str::contains($senderEmail, '@')) {
            $domain = parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
        }

        return '<'.Str::uuid().'@'.$domain.'>';
    }

    private function bulkHeaders(MailRecipient $recipient): array
    {
        $unsubscribeUrl = $this->mailUnsubscribeService->unsubscribeUrl($recipient);

        if ($unsubscribeUrl === null) {
            throw ValidationException::withMessages([
                'preflight' => ['Les campagnes bulk exigent une URL publique HTTPS pour l’en-tête de désinscription.'],
            ]);
        }

        return [
            'List-Unsubscribe' => '<'.$unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            'Precedence' => 'bulk',
            'X-Auto-Response-Suppress' => 'OOF, DR, RN, NRN, AutoReply',
        ];
    }
}
