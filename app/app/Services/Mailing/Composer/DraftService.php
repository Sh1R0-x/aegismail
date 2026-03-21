<?php

namespace App\Services\Mailing\Composer;

use App\Models\MailboxAccount;
use App\Models\MailCampaign;
use App\Models\MailDraft;
use App\Models\MailEvent;
use App\Models\MailMessage;
use App\Services\Mailing\Contracts\MailGatewayClient;
use App\Services\Mailing\EmailContentService;
use App\Services\Mailing\MailboxSettingsService;
use App\Services\Mailing\MailEventLogger;
use App\Services\Mailing\Outbound\OutboundMailService;
use App\Services\Mailing\PublicEmailUrlService;
use App\Services\Mailing\SmtpProviderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DraftService
{
    public function __construct(
        private readonly MailboxSettingsService $mailboxSettingsService,
        private readonly MailEventLogger $eventLogger,
        private readonly CampaignService $campaignService,
        private readonly DraftPreflightService $preflightService,
        private readonly OutboundMailService $outboundMailService,
        private readonly EmailContentService $emailContentService,
        private readonly PublicEmailUrlService $publicEmailUrlService,
        private readonly SmtpProviderService $smtpProviderService,
    ) {}

    public function list(): array
    {
        return MailDraft::query()
            ->whereDoesntHave('campaigns', fn ($query) => $query->onlyTrashed())
            ->with(['campaigns.recipients'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (MailDraft $draft) => $this->serializeListItem($draft))
            ->all();
    }

    public function create(array $validated, ?int $userId = null): MailDraft
    {
        $mailbox = $this->mailbox();

        if ($mailbox === null) {
            throw ValidationException::withMessages([
                'mailbox' => ['La boîte OVH MX Plan doit être configurée avant de créer un brouillon.'],
            ]);
        }

        $provider = $this->smtpProviderService->activeProvider();
        $this->smtpProviderService->validateActiveProvider($provider, $mailbox);

        $mailSettings = $this->mailboxSettingsService->getSettings();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'outbound_provider' => $provider,
            'user_id' => $userId,
            'mode' => $validated['type'],
            'template_id' => $validated['templateId'] ?? null,
            'subject' => $validated['subject'],
            'html_body' => $validated['htmlBody'] ?? null,
            'text_body' => $validated['textBody'] ?? null,
            'signature_snapshot' => $validated['signatureHtml'] ?? $mailSettings['global_signature_html'] ?? null,
            'payload_json' => [
                'recipients' => array_values($validated['recipients'] ?? []),
            ],
            'status' => 'draft',
        ]);

        $this->eventLogger->log(
            'mail_draft.created',
            [
                'draft_id' => $draft->id,
                'outbound_provider' => $provider,
            ],
            ['mailbox_account_id' => $mailbox->id],
            'mail_draft.created.'.$draft->id,
        );

        return $draft->fresh(['campaigns.recipients', 'attachments']);
    }

    public function update(MailDraft $draft, array $validated): MailDraft
    {
        $draft->fill([
            'mode' => $validated['type'],
            'template_id' => $validated['templateId'] ?? null,
            'subject' => $validated['subject'],
            'html_body' => $validated['htmlBody'] ?? null,
            'text_body' => $validated['textBody'] ?? null,
            'signature_snapshot' => $validated['signatureHtml'] ?? $draft->signature_snapshot,
            'payload_json' => array_replace($draft->payload_json ?? [], [
                'recipients' => array_values($validated['recipients'] ?? []),
            ]),
        ])->save();

        $this->eventLogger->log(
            'mail_draft.updated',
            ['draft_id' => $draft->id],
            ['mailbox_account_id' => $draft->mailbox_account_id],
            'mail_draft.updated.'.$draft->id.'.'.$draft->updated_at?->timestamp,
        );

        return $draft->fresh(['campaigns.recipients', 'attachments']);
    }

    public function duplicate(MailDraft $draft): MailDraft
    {
        $copy = MailDraft::query()->create([
            'mailbox_account_id' => $draft->mailbox_account_id,
            'outbound_provider' => $draft->outbound_provider,
            'user_id' => $draft->user_id,
            'mode' => $draft->mode,
            'template_id' => $draft->template_id,
            'subject' => $draft->subject.' (copie)',
            'html_body' => $draft->html_body,
            'text_body' => $draft->text_body,
            'signature_snapshot' => $draft->signature_snapshot,
            'payload_json' => $draft->payload_json,
            'status' => 'draft',
            'scheduled_at' => null,
        ]);

        foreach ($draft->attachments as $attachment) {
            $copy->attachments()->create([
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'storage_disk' => $attachment->storage_disk,
                'storage_path' => $attachment->storage_path,
                'content_id' => $attachment->content_id,
                'disposition' => $attachment->disposition,
            ]);
        }

        $this->eventLogger->log(
            'mail_draft.duplicated',
            ['draft_id' => $draft->id, 'duplicate_id' => $copy->id],
            ['mailbox_account_id' => $draft->mailbox_account_id],
            'mail_draft.duplicated.'.$draft->id.'.'.$copy->id,
        );

        return $copy->fresh(['campaigns.recipients', 'attachments']);
    }

    public function delete(MailDraft $draft): void
    {
        $this->ensureCampaignDraftIsEditable($draft);

        $campaigns = $draft->campaigns()->withTrashed()->with(['recipients.messages', 'events'])->get();

        $hasSentHistory = $campaigns->contains(function ($campaign) {
            return $campaign->recipients->contains(function ($recipient) {
                return $recipient->messages->contains(fn (MailMessage $message) => $message->sent_at !== null)
                    || in_array($recipient->status, [
                        'sent',
                        'delivered_if_known',
                        'opened',
                        'clicked',
                        'replied',
                        'auto_replied',
                        'soft_bounced',
                        'hard_bounced',
                        'unsubscribed',
                    ], true);
            });
        });

        if ($hasSentHistory) {
            throw ValidationException::withMessages([
                'draft' => ['Ce brouillon ne peut plus être supprimé car des envois ou des événements de campagne existent déjà.'],
            ]);
        }

        DB::transaction(function () use ($draft, $campaigns): void {
            foreach ($campaigns as $campaign) {
                $recipientIds = $campaign->recipients->pluck('id');
                $messageIds = $campaign->recipients->flatMap(fn ($recipient) => $recipient->messages->pluck('id'))->unique();

                if ($messageIds->isNotEmpty()) {
                    MailEvent::query()->whereIn('message_id', $messageIds)->delete();
                    MailMessage::query()->whereIn('id', $messageIds)->delete();
                }

                if ($recipientIds->isNotEmpty()) {
                    MailEvent::query()->whereIn('recipient_id', $recipientIds)->delete();
                    $campaign->recipients()->delete();
                }

                MailEvent::query()->where('campaign_id', $campaign->id)->delete();
                $campaign->delete();
            }

            $draft->attachments()->delete();
            MailEvent::query()->where('event_type', 'like', 'mail_draft.%')
                ->whereRaw("json_extract(event_payload, '$.draft_id') = ?", [$draft->id])
                ->delete();

            $draft->delete();
        });
    }

    public function deleteMany(Collection $drafts): int
    {
        $deleted = 0;

        foreach ($drafts as $draft) {
            $this->delete($draft);
            $deleted++;
        }

        return $deleted;
    }

    public function preflight(MailDraft $draft): array
    {
        $draft->loadMissing('attachments');
        $provider = $draft->outbound_provider ?: $this->smtpProviderService->activeProvider();

        $preflight = $this->preflightService->run($draft, $this->mailboxForDraft($draft), $provider);

        $this->eventLogger->log(
            'mail_draft.preflight_ran',
            [
                'draft_id' => $draft->id,
                'outbound_provider' => $provider,
                'ok' => $preflight['ok'],
                'errors' => array_column($preflight['errors'], 'code'),
                'warnings' => array_column($preflight['warnings'], 'code'),
            ],
            ['mailbox_account_id' => $draft->mailbox_account_id],
            'mail_draft.preflight_ran.'.$draft->id.'.'.md5(json_encode($preflight)),
        );

        return $preflight;
    }

    public function schedule(MailDraft $draft, Carbon $scheduledAt, ?string $name = null): array
    {
        $this->ensureCampaignDraftIsEditable($draft);

        $mailbox = $this->mailboxForDraft($draft);

        if ($mailbox === null) {
            throw ValidationException::withMessages([
                'mailbox' => ['La boîte OVH MX Plan doit être configurée avant la planification.'],
            ]);
        }

        [$campaign, $preflight] = $this->campaignService->createFromDraft(
            $draft->fresh(['attachments']),
            $mailbox,
            $draft->outbound_provider ?: $this->smtpProviderService->activeProvider(),
            $name,
            $scheduledAt,
        );

        if ($campaign === null) {
            throw ValidationException::withMessages([
                'preflight' => array_column($preflight['errors'], 'message') ?: ['Le preflight contient des erreurs bloquantes.'],
            ]);
        }

        $draft->forceFill([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ])->save();

        $campaign = $this->outboundMailService->queueCampaign($draft, $campaign, $scheduledAt);

        $this->eventLogger->log(
            'mail_draft.scheduled',
            [
                'draft_id' => $draft->id,
                'campaign_id' => $campaign->id,
                'outbound_provider' => $draft->outbound_provider,
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'queue' => config('mailing.queues.outbound'),
            ],
            [
                'mailbox_account_id' => $draft->mailbox_account_id,
                'campaign_id' => $campaign->id,
            ],
            'mail_draft.scheduled.'.$draft->id.'.'.$scheduledAt->timestamp,
        );

        return [$draft->fresh(['campaigns.recipients', 'attachments']), $campaign, $preflight];
    }

    public function unschedule(MailDraft $draft): array
    {
        $this->ensureCampaignDraftIsEditable($draft);

        $campaign = $this->campaignService->unschedule($draft);

        $draft->forceFill([
            'status' => 'draft',
            'scheduled_at' => null,
        ])->save();

        $this->eventLogger->log(
            'mail_draft.unscheduled',
            ['draft_id' => $draft->id],
            [
                'mailbox_account_id' => $draft->mailbox_account_id,
                'campaign_id' => $campaign?->id,
            ],
            'mail_draft.unscheduled.'.$draft->id,
        );

        return [$draft->fresh(['campaigns.recipients', 'attachments']), $campaign];
    }

    public function sendNow(MailDraft $draft, ?string $name = null): array
    {
        $this->ensureCampaignDraftIsEditable($draft);

        return $this->schedule($draft, Carbon::now(), $name);
    }

    public function testSend(MailDraft $draft, string $testEmail): array
    {
        $mailbox = $this->mailboxForDraft($draft);

        if ($mailbox === null) {
            throw ValidationException::withMessages([
                'mailbox' => ['La boîte OVH MX Plan doit être configurée avant l\'envoi de test.'],
            ]);
        }

        if (empty(trim($draft->text_body)) && empty(trim((string) $draft->html_body))) {
            throw ValidationException::withMessages([
                'body' => ['Le brouillon ne contient aucun contenu à envoyer.'],
            ]);
        }

        $mailSettings = $this->mailboxSettingsService->getSettings();
        $provider = $draft->outbound_provider ?: $this->smtpProviderService->activeProvider();
        $smtpConnection = $this->smtpProviderService->runtimeConfiguration($provider, $mailbox);
        $gatewayClient = app(MailGatewayClient::class);
        $preparedBodies = $this->emailContentService->prepareBodies(
            $draft->html_body,
            $draft->text_body,
            $draft->signature_snapshot ?: ($mailSettings['global_signature_html'] ?? null),
            $mailSettings['global_signature_text'] ?? null,
        );

        if ($preparedBodies['analysis']['issues'] !== []) {
            $group = collect($preparedBodies['analysis']['issues'])->groupBy('code')->first();
            $firstCode = $group?->first()['code'] ?? 'link_not_public';
            [$kind, $issue] = explode('_', $firstCode, 2);

            throw ValidationException::withMessages([
                'body' => [$this->publicEmailUrlService->issueMessage(
                    $kind,
                    $issue,
                    $group?->count() ?? 1,
                    $group?->pluck('url')->filter()->unique()->take(2)->values()->all() ?? [],
                )],
            ]);
        }

        $payload = [
            'mailbox_account_id' => $mailbox->id,
            'mail_message_id' => null,
            'thread_id' => null,
            'campaign_id' => null,
            'recipient_id' => null,
            'provider' => $provider,
            'email' => $mailbox->email,
            'username' => $smtpConnection['smtp_username'],
            'password' => $smtpConnection['smtp_password'],
            'smtp_host' => $smtpConnection['smtp_host'],
            'smtp_port' => $smtpConnection['smtp_port'],
            'smtp_secure' => $smtpConnection['smtp_secure'],
            'from_email' => $mailbox->email,
            'from_name' => $mailbox->display_name,
            'to_emails' => [$testEmail],
            'subject' => '[TEST] '.($draft->subject ?: '(Sans objet)'),
            'html_body' => $preparedBodies['html_body'],
            'text_body' => $preparedBodies['text_body'],
            'message_id_header' => '<test-'.now()->timestamp.'-'.uniqid().'@'.($mailbox->email ? explode('@', $mailbox->email)[1] : 'aegis.local').'>',
            'in_reply_to_header' => null,
            'references_header' => null,
            'aegis_tracking_id' => null,
            'headers_json' => [
                'X-Aegis-Test' => 'true',
                'transport' => [
                    'provider' => $provider,
                ],
            ],
            'attachments' => [],
        ];

        $result = $gatewayClient->dispatchMessage($payload);

        $this->eventLogger->log(
            'mail_draft.test_sent',
            [
                'draft_id' => $draft->id,
                'outbound_provider' => $provider,
                'test_email' => $testEmail,
                'success' => $result['success'] ?? false,
                'driver' => $result['driver'] ?? config('mailing.gateway.driver'),
            ],
            ['mailbox_account_id' => $mailbox->id],
        );

        return [
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? ($result['success'] ? 'Test envoyé.' : 'Échec de l\'envoi de test.'),
            'provider' => $provider,
            'providerLabel' => $this->smtpProviderService->label($provider),
            'driver' => $result['driver'] ?? config('mailing.gateway.driver'),
            'acceptedAt' => $result['accepted_at'] ?? null,
        ];
    }

    public function serialize(MailDraft $draft): array
    {
        $draft->loadMissing(['campaigns.recipients', 'attachments']);

        return [
            'id' => $draft->id,
            'templateId' => $draft->template_id,
            'type' => $draft->mode === 'bulk' ? 'multiple' : 'single',
            'outboundProvider' => $draft->outbound_provider,
            'outboundProviderLabel' => $this->smtpProviderService->label($draft->outbound_provider ?: $this->smtpProviderService->activeProvider()),
            'subject' => $draft->subject,
            'htmlBody' => $draft->html_body,
            'textBody' => $draft->text_body,
            'signatureHtml' => $draft->signature_snapshot,
            'status' => $draft->status,
            'scheduledAt' => $draft->scheduled_at?->timezone(config('app.timezone'))->toIso8601String(),
            'recipientCount' => $this->recipientCount($draft),
            'recipients' => array_values($draft->payload_json['recipients'] ?? []),
            'attachmentCount' => $draft->attachments->count(),
            'attachments' => $draft->attachments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'size' => $a->size_bytes,
                'mimeType' => $a->mime_type,
            ])->values()->all(),
            'updatedAt' => $draft->updated_at?->timezone(config('app.timezone'))->toIso8601String(),
        ];
    }

    public function serializeListItem(MailDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'subject' => $draft->subject,
            'recipientCount' => $this->recipientCount($draft),
            'type' => $draft->mode === 'bulk' ? 'multiple' : 'single',
            'outboundProvider' => $draft->outbound_provider,
            'outboundProviderLabel' => $this->smtpProviderService->label($draft->outbound_provider ?: $this->smtpProviderService->activeProvider()),
            'status' => $draft->status,
            'scheduledAt' => $draft->scheduled_at?->timezone(config('app.timezone'))->toIso8601String(),
            'updatedAt' => $draft->updated_at?->timezone(config('app.timezone'))->toIso8601String(),
        ];
    }

    public function mailbox(): ?MailboxAccount
    {
        return $this->mailboxSettingsService->mailbox();
    }

    public function autosaveCampaign(array $validated, ?int $userId = null): array
    {
        $mailbox = $this->mailbox();

        if ($mailbox === null) {
            throw ValidationException::withMessages([
                'mailbox' => ['La boîte OVH MX Plan doit être configurée avant de préparer une campagne.'],
            ]);
        }

        $draft = isset($validated['draftId'])
            ? MailDraft::query()->findOrFail($validated['draftId'])
            : null;

        $existingCampaign = isset($validated['campaignId'])
            ? MailCampaign::query()->withTrashed()->findOrFail($validated['campaignId'])
            : ($draft?->campaigns()->withTrashed()->latest('id')->first());

        if ($existingCampaign?->trashed()) {
            throw ValidationException::withMessages([
                'campaign' => ['Cette campagne a été supprimée et ne peut plus être modifiée.'],
            ]);
        }

        if ($existingCampaign !== null) {
            $this->assertAutosaveNotStale($existingCampaign, $validated['expectedUpdatedAt'] ?? null);
        }

        $mailSettings = $this->mailboxSettingsService->getSettings();
        $draftPayload = [
            'type' => $validated['type'],
            'templateId' => $validated['templateId'] ?? null,
            'subject' => $validated['subject'],
            'htmlBody' => $validated['htmlBody'] ?? null,
            'textBody' => $validated['textBody'] ?? null,
            'signatureHtml' => $validated['signatureHtml'] ?? $mailSettings['global_signature_html'] ?? null,
            'recipients' => array_values($validated['recipients'] ?? []),
        ];

        $created = $draft === null && $existingCampaign === null;

        $draft = $draft === null
            ? $this->create($draftPayload, $userId)
            : $this->update($draft, $draftPayload);

        $campaign = $this->campaignService->syncAutosavedCampaign(
            $draft,
            $mailbox,
            $validated['name'] ?? null,
            $userId,
        );

        $this->eventLogger->log(
            'mail_campaign.autosaved',
            [
                'draft_id' => $draft->id,
                'campaign_id' => $campaign->id,
                'recipient_count' => count($draft->payload_json['recipients'] ?? []),
            ],
            [
                'mailbox_account_id' => $mailbox->id,
                'campaign_id' => $campaign->id,
            ],
        );

        return [$draft->fresh(['campaigns.recipients', 'attachments']), $campaign->fresh('recipients'), $created];
    }

    private function recipientCount(MailDraft $draft): int
    {
        $campaign = $draft->campaigns->sortByDesc('id')->first();

        if ($campaign !== null) {
            return $campaign->recipients->count();
        }

        $payloadRecipients = count($draft->payload_json['recipients'] ?? []);

        if ($payloadRecipients > 0) {
            return $payloadRecipients;
        }

        return $draft->mode === 'single' ? 1 : 0;
    }

    private function assertAutosaveNotStale(MailCampaign $campaign, ?string $expectedUpdatedAt): void
    {
        if ($expectedUpdatedAt === null || $expectedUpdatedAt === '') {
            return;
        }

        $expected = Carbon::parse($expectedUpdatedAt);
        $current = $campaign->last_edited_at ?? $campaign->updated_at;

        if ($current !== null && $current->greaterThan($expected)) {
            throw new ConflictHttpException('Une version plus récente de la campagne existe déjà. Rechargez la page avant de continuer.');
        }
    }

    private function ensureCampaignDraftIsEditable(MailDraft $draft): void
    {
        $deletedCampaignExists = $draft->campaigns()->withTrashed()->onlyTrashed()->exists();

        if (! $deletedCampaignExists) {
            return;
        }

        throw ValidationException::withMessages([
            'campaign' => ['Cette campagne a été supprimée et ne peut plus être modifiée.'],
        ]);
    }

    private function mailboxForDraft(MailDraft $draft): ?MailboxAccount
    {
        return $draft->mailboxAccount()->first() ?? $this->mailbox();
    }
}
