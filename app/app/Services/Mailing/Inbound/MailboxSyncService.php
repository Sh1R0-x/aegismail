<?php

namespace App\Services\Mailing\Inbound;

use App\Models\ContactEmail;
use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Models\MailboxAccount;
use App\Services\Mailing\Contracts\MailGatewayClient;
use App\Services\Mailing\MailEventLogger;
use App\Services\Mailing\MailboxSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MailboxSyncService
{
    public function __construct(
        private readonly MailboxSettingsService $mailboxSettingsService,
        private readonly InboundMessageClassifier $classifier,
        private readonly ThreadResolver $threadResolver,
        private readonly MailEventLogger $eventLogger,
    ) {
    }

    public function sync(array $payload, MailGatewayClient $gatewayClient): array
    {
        $mailbox = MailboxAccount::query()->findOrFail($payload['mailbox_account_id']);
        $folder = strtoupper((string) ($payload['folder'] ?? 'INBOX'));
        $lock = Cache::lock("mailbox-sync:{$mailbox->id}:{$folder}", 120);

        if (! $lock->get()) {
            $this->eventLogger->log(
                'mailbox.sync_skipped_locked',
                ['folder' => $folder],
                ['mailbox_account_id' => $mailbox->id],
                "mailbox.sync.locked.{$mailbox->id}.{$folder}"
            );

            return [
                'success' => true,
                'driver' => 'laravel',
                'message' => 'Mailbox sync skipped because a lock is already active.',
                'folder' => $folder,
                'processed' => 0,
            ];
        }

        try {
            $result = $gatewayClient->syncMailbox($this->gatewayPayload($mailbox, $folder, $payload));

            if (! ($result['success'] ?? false)) {
                $mailbox->forceFill([
                    'health_status' => 'warning',
                    'health_message' => $result['message'] ?? 'Mailbox sync failed.',
                ])->save();

                throw new RuntimeException($result['message'] ?? 'Mailbox sync rejected by mail gateway.');
            }

            $messages = collect($result['messages'] ?? [])
                ->filter(fn ($message) => is_array($message))
                ->sortBy(fn (array $message) => (int) ($message['uid'] ?? 0))
                ->values();

            $processed = 0;

            foreach ($messages as $message) {
                $uid = (int) ($message['uid'] ?? 0);

                if ($uid > 0 && $uid <= $this->cursorValue($mailbox, $folder)) {
                    continue;
                }

                $this->ingestMessage($mailbox->fresh(), $folder, $message);
                $processed++;

                if ($uid > 0) {
                    $this->advanceCursor($mailbox, $folder, $uid);
                }
            }

            $mailbox->forceFill([
                'last_sync_at' => now(),
                'health_status' => 'healthy',
                'health_message' => "IMAP {$folder} sync completed.",
            ])->save();

            $this->eventLogger->log(
                'mailbox.sync_completed',
                [
                    'folder' => $folder,
                    'processed' => $processed,
                    'highest_uid' => $this->cursorValue($mailbox->fresh(), $folder),
                ],
                ['mailbox_account_id' => $mailbox->id],
                $payload['idempotency_key'] ?? "mailbox.sync.completed.{$mailbox->id}.{$folder}.".now()->format('YmdHi')
            );

            return array_replace($result, [
                'folder' => $folder,
                'processed' => $processed,
                'highest_uid' => $this->cursorValue($mailbox->fresh(), $folder),
            ]);
        } catch (Throwable $exception) {
            $mailbox->forceFill([
                'last_sync_at' => now(),
                'health_status' => 'warning',
                'health_message' => $exception->getMessage(),
            ])->save();

            $this->eventLogger->log(
                'mailbox.sync_failed',
                [
                    'folder' => $folder,
                    'message' => $exception->getMessage(),
                ],
                ['mailbox_account_id' => $mailbox->id],
                $payload['idempotency_key'] ? $payload['idempotency_key'].'.failed' : null
            );

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function ingestMessage(MailboxAccount $mailbox, string $folder, array $message): MailMessage
    {
        $normalized = $this->normalizeMessage($mailbox, $folder, $message);

        if ($existing = $this->findExistingMessage($mailbox, $normalized)) {
            $this->eventLogger->log(
                'mailbox.message_duplicate_skipped',
                [
                    'folder' => $folder,
                    'uid' => $normalized['provider_uid'],
                    'message_id_header' => $normalized['message_id_header'],
                ],
                [
                    'mailbox_account_id' => $mailbox->id,
                    'thread_id' => $existing->thread_id,
                    'message_id' => $existing->id,
                ],
                $this->messageEventKey('duplicate', $folder, $normalized['provider_uid'], $normalized['message_id_header'])
            );

            return $existing;
        }

        $contactContext = $this->resolveContactContext($mailbox, $normalized);
        $classification = $normalized['direction'] === 'in'
            ? $this->classifier->classify($normalized)
            : 'unknown';
        $resolution = $this->threadResolver->resolve($mailbox, array_replace($normalized, [
            'contact_id' => $contactContext['contact_id'],
            'organization_id' => $contactContext['organization_id'],
        ]));
        $thread = $resolution['thread'];
        $occurredAt = $this->messageTimestamp($normalized);
        $recipient = $this->resolveRecipient($mailbox, $thread->id, $normalized, $classification, $contactContext, $resolution['matched_message']?->recipient_id);

        $messageModel = DB::transaction(function () use (
            $mailbox,
            $normalized,
            $contactContext,
            $thread,
            $resolution,
            $recipient,
            $classification,
            $occurredAt
        ): MailMessage {
            $thread->forceFill([
                'organization_id' => $thread->organization_id ?: $contactContext['organization_id'],
                'contact_id' => $thread->contact_id ?: $contactContext['contact_id'],
                'first_message_at' => $thread->first_message_at?->lte($occurredAt) ? $thread->first_message_at : $occurredAt,
                'last_message_at' => $thread->last_message_at?->gte($occurredAt) ? $thread->last_message_at : $occurredAt,
                'last_direction' => $normalized['direction'],
                'confidence_score' => $resolution['confidence'],
                'status' => $this->threadStatus($classification),
            ])->save();

            if ($classification === 'human_reply') {
                $thread->forceFill(['reply_received' => true])->save();
            }

            if (in_array($classification, ['auto_reply', 'out_of_office', 'auto_ack'], true)) {
                $thread->forceFill(['auto_reply_received' => true])->save();
            }

            $model = MailMessage::query()->create([
                'thread_id' => $thread->id,
                'mailbox_account_id' => $mailbox->id,
                'recipient_id' => $recipient?->id,
                'direction' => $normalized['direction'],
                'provider_folder' => $normalized['provider_folder'],
                'provider_uid' => $normalized['provider_uid'],
                'message_id_header' => $normalized['message_id_header'],
                'in_reply_to_header' => $normalized['in_reply_to_header'],
                'references_header' => $normalized['references_header'],
                'aegis_tracking_id' => $normalized['aegis_tracking_id'],
                'from_email' => $normalized['from_email'],
                'to_emails' => $normalized['to_emails'],
                'cc_emails' => $normalized['cc_emails'],
                'bcc_emails' => $normalized['bcc_emails'],
                'subject' => $normalized['subject'],
                'html_body' => $normalized['html_body'],
                'text_body' => $normalized['text_body'],
                'headers_json' => $normalized['headers_json'],
                'classification' => $classification,
                'sent_at' => $normalized['sent_at'],
                'received_at' => $normalized['received_at'],
            ]);

            foreach ($normalized['attachments'] as $attachment) {
                $model->attachments()->create($attachment);
            }

            if ($contactContext['contact_email'] !== null) {
                $contactContext['contact_email']->forceFill([
                    'last_seen_at' => $occurredAt,
                    'bounce_status' => $classification === 'hard_bounce'
                        ? 'hard_bounced'
                        : $contactContext['contact_email']->bounce_status,
                ])->save();
            }

            $this->applyRecipientOutcome($recipient, $classification, $occurredAt, $thread->id);

            return $model->fresh('attachments');
        });

        $this->eventLogger->log(
            'mailbox.message_synced',
            [
                'folder' => $folder,
                'uid' => $normalized['provider_uid'],
                'message_id_header' => $normalized['message_id_header'],
                'classification' => $classification,
                'thread_strategy' => $resolution['strategy'],
            ],
            [
                'mailbox_account_id' => $mailbox->id,
                'recipient_id' => $messageModel->recipient_id,
                'thread_id' => $messageModel->thread_id,
                'message_id' => $messageModel->id,
            ],
            $this->messageEventKey('synced', $folder, $normalized['provider_uid'], $normalized['message_id_header'])
        );

        return $messageModel;
    }

    private function normalizeMessage(MailboxAccount $mailbox, string $folder, array $message): array
    {
        $messageIdHeader = trim((string) ($message['message_id_header'] ?? ''));
        $uid = isset($message['uid']) ? (int) $message['uid'] : null;

        if ($messageIdHeader === '') {
            $messageIdHeader = '<sync-'.$mailbox->id.'-'.Str::lower($folder).'-'.$uid.'@aegis.local>';
        }

        $headers = is_array($message['headers_json'] ?? null) ? $message['headers_json'] : [];
        $headers['Message-ID'] = $messageIdHeader;

        return [
            'direction' => $folder === 'SENT' ? 'out' : 'in',
            'provider_folder' => $folder,
            'provider_uid' => $uid,
            'message_id_header' => $messageIdHeader,
            'in_reply_to_header' => $this->normalizeHeaderValue($message['in_reply_to_header'] ?? null),
            'references_header' => $this->normalizeReferences($message['references_header'] ?? null),
            'aegis_tracking_id' => (string) ($message['aegis_tracking_id'] ?? Str::uuid()),
            'from_email' => Str::lower(trim((string) ($message['from_email'] ?? $mailbox->email))),
            'to_emails' => $this->normalizeEmailList($message['to_emails'] ?? []),
            'cc_emails' => $this->normalizeEmailList($message['cc_emails'] ?? []),
            'bcc_emails' => $this->normalizeEmailList($message['bcc_emails'] ?? []),
            'subject' => trim((string) ($message['subject'] ?? '')),
            'html_body' => $message['html_body'] ?? null,
            'text_body' => $message['text_body'] ?? null,
            'headers_json' => $headers,
            'sent_at' => $folder === 'SENT' ? $this->normalizeDate($message['sent_at'] ?? $message['received_at'] ?? null) : null,
            'received_at' => $folder === 'INBOX' ? $this->normalizeDate($message['received_at'] ?? $message['sent_at'] ?? null) : null,
            'attachments' => collect($message['attachments'] ?? [])
                ->filter(fn ($attachment) => is_array($attachment))
                ->map(fn (array $attachment): array => [
                    'original_name' => (string) ($attachment['original_name'] ?? 'attachment.bin'),
                    'mime_type' => (string) ($attachment['mime_type'] ?? 'application/octet-stream'),
                    'size_bytes' => (int) ($attachment['size_bytes'] ?? 0),
                    'storage_disk' => (string) ($attachment['storage_disk'] ?? 'local'),
                    'storage_path' => (string) ($attachment['storage_path'] ?? 'mailbox-sync/'.Str::uuid()),
                    'content_id' => $attachment['content_id'] ?? null,
                    'disposition' => $attachment['disposition'] ?? 'attachment',
                ])->all(),
        ];
    }

    private function findExistingMessage(MailboxAccount $mailbox, array $normalized): ?MailMessage
    {
        if ($normalized['provider_uid'] !== null) {
            $existing = MailMessage::query()
                ->where('mailbox_account_id', $mailbox->id)
                ->where('provider_folder', $normalized['provider_folder'])
                ->where('provider_uid', $normalized['provider_uid'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $existing = MailMessage::query()
            ->where('message_id_header', $normalized['message_id_header'])
            ->first();

        if ($existing !== null && $normalized['provider_uid'] !== null && $existing->provider_uid === null) {
            $existing->forceFill([
                'mailbox_account_id' => $mailbox->id,
                'provider_folder' => $normalized['provider_folder'],
                'provider_uid' => $normalized['provider_uid'],
            ])->save();
        }

        return $existing;
    }

    private function resolveContactContext(MailboxAccount $mailbox, array $normalized): array
    {
        $externalEmails = collect([
            $normalized['from_email'],
            ...$normalized['to_emails'],
            ...$normalized['cc_emails'],
            ...$normalized['bcc_emails'],
            $this->extractBounceTarget($normalized),
        ])
            ->filter()
            ->map(fn ($email) => Str::lower((string) $email))
            ->reject(fn ($email) => $email === Str::lower($mailbox->email))
            ->unique()
            ->values();

        $contactEmail = ContactEmail::query()
            ->whereIn('email', $externalEmails->all())
            ->orderByDesc('is_primary')
            ->first();

        return [
            'contact_email' => $contactEmail,
            'contact_id' => $contactEmail?->contact_id,
            'organization_id' => $contactEmail?->contact?->organization_id,
            'external_emails' => $externalEmails->all(),
        ];
    }

    private function resolveRecipient(
        MailboxAccount $mailbox,
        int $threadId,
        array $normalized,
        string $classification,
        array $contactContext,
        ?int $matchedRecipientId,
    ): ?MailRecipient {
        if ($matchedRecipientId !== null) {
            return MailRecipient::query()->find($matchedRecipientId);
        }

        $threadRecipient = MailMessage::query()
            ->where('thread_id', $threadId)
            ->whereNotNull('recipient_id')
            ->latest('id')
            ->value('recipient_id');

        if ($threadRecipient !== null) {
            return MailRecipient::query()->find($threadRecipient);
        }

        $candidateEmail = $classification === 'hard_bounce' || $classification === 'soft_bounce'
            ? $this->extractBounceTarget($normalized)
            : ($contactContext['external_emails'][0] ?? null);

        if ($candidateEmail === null) {
            return null;
        }

        return MailRecipient::query()
            ->where('email', Str::lower($candidateEmail))
            ->whereHas('campaign', fn ($query) => $query->where('mailbox_account_id', $mailbox->id))
            ->orderByDesc('sent_at')
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id')
            ->first();
    }

    private function applyRecipientOutcome(?MailRecipient $recipient, string $classification, Carbon $occurredAt, int $threadId): void
    {
        if ($recipient === null) {
            return;
        }

        $recipient->forceFill([
            'last_event_at' => $occurredAt,
            'score_bucket' => match ($classification) {
                'human_reply' => 'engaged',
                'auto_reply', 'out_of_office', 'auto_ack' => 'auto_replied',
                'soft_bounce' => 'soft_bounced',
                'hard_bounce' => 'hard_bounced',
                default => $recipient->score_bucket,
            },
        ]);

        if ($classification === 'human_reply') {
            $recipient->status = 'replied';
            $recipient->replied_at = $occurredAt;
            $this->cancelFutureFollowUps($recipient, $threadId, 'reply_received');
        }

        if (in_array($classification, ['auto_reply', 'out_of_office', 'auto_ack'], true)) {
            $recipient->status = 'auto_replied';
            $recipient->auto_replied_at = $occurredAt;
        }

        if ($classification === 'soft_bounce') {
            $recipient->status = 'soft_bounced';
            $recipient->bounced_at = $occurredAt;
        }

        if ($classification === 'hard_bounce') {
            $recipient->status = 'hard_bounced';
            $recipient->bounced_at = $occurredAt;
            $this->cancelFutureFollowUps($recipient, $threadId, 'hard_bounce_received');
        }

        $recipient->save();
    }

    private function cancelFutureFollowUps(MailRecipient $recipient, int $threadId, string $reason): void
    {
        $cancelled = MailRecipient::query()
            ->where('campaign_id', $recipient->campaign_id)
            ->where('email', $recipient->email)
            ->where('id', '!=', $recipient->id)
            ->whereIn('status', ['draft', 'scheduled', 'queued'])
            ->update([
                'status' => 'cancelled',
                'last_event_at' => now(),
            ]);

        if ($cancelled > 0) {
            $this->eventLogger->log(
                'mail_campaign.follow_up_cancelled',
                [
                    'campaign_id' => $recipient->campaign_id,
                    'email' => $recipient->email,
                    'reason' => $reason,
                    'count' => $cancelled,
                ],
                [
                    'campaign_id' => $recipient->campaign_id,
                    'recipient_id' => $recipient->id,
                    'thread_id' => $threadId,
                ],
                "mail_campaign.follow_up_cancelled.{$recipient->campaign_id}.{$recipient->id}.{$reason}"
            );
        }
    }

    private function gatewayPayload(MailboxAccount $mailbox, string $folder, array $payload): array
    {
        $connection = $this->mailboxSettingsService->getConnectionConfiguration();

        return [
            'mailbox_account_id' => $mailbox->id,
            'folder' => $folder,
            'from_uid' => $this->cursorValue($mailbox, $folder),
            'provider' => config('mailing.provider'),
            'email' => $mailbox->email,
            'username' => $connection['mailbox_username'] ?? $mailbox->username,
            'password' => $connection['mailbox_password'] ?? null,
            'imap_host' => $connection['imap_host'] ?? $mailbox->imap_host,
            'imap_port' => $connection['imap_port'] ?? $mailbox->imap_port,
            'imap_secure' => $connection['imap_secure'] ?? $mailbox->imap_secure,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
            'stub_messages' => $payload['stub_messages'] ?? [],
        ];
    }

    private function cursorValue(MailboxAccount $mailbox, string $folder): int
    {
        return (int) ($folder === 'SENT' ? $mailbox->last_sent_uid : $mailbox->last_inbox_uid);
    }

    private function advanceCursor(MailboxAccount $mailbox, string $folder, int $uid): void
    {
        $column = $folder === 'SENT' ? 'last_sent_uid' : 'last_inbox_uid';

        $mailbox->refresh();

        if ((int) ($mailbox->{$column} ?? 0) >= $uid) {
            return;
        }

        $mailbox->forceFill([
            $column => $uid,
            'last_sync_at' => now(),
        ])->save();
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    private function normalizeHeaderValue(mixed $value): ?string
    {
        $resolved = trim((string) ($value ?? ''));

        return $resolved === '' ? null : $resolved;
    }

    private function normalizeReferences(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(' ', array_filter($value));
        }

        return $this->normalizeHeaderValue($value);
    }

    private function normalizeEmailList(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->flatten()
            ->filter()
            ->map(fn ($email) => Str::lower(trim((string) $email)))
            ->filter()
            ->values()
            ->all();
    }

    private function extractBounceTarget(array $normalized): ?string
    {
        $candidates = [
            $normalized['headers_json']['X-Failed-Recipients'] ?? null,
            $normalized['headers_json']['x-failed-recipients'] ?? null,
            $normalized['headers_json']['Final-Recipient'] ?? null,
            $normalized['headers_json']['final-recipient'] ?? null,
            $normalized['headers_json']['Original-Recipient'] ?? null,
            $normalized['headers_json']['original-recipient'] ?? null,
            $normalized['text_body'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string) $candidate, $matches) === 1) {
                return Str::lower($matches[0]);
            }
        }

        return null;
    }

    private function messageTimestamp(array $normalized): Carbon
    {
        return $normalized['received_at']
            ?? $normalized['sent_at']
            ?? now();
    }

    private function messageEventKey(string $event, string $folder, ?int $uid, string $messageIdHeader): string
    {
        return implode('.', [
            'mailbox',
            'message',
            $event,
            Str::lower($folder),
            $uid ?: 'no-uid',
            md5($messageIdHeader),
        ]);
    }

    private function threadStatus(string $classification): string
    {
        return match ($classification) {
            'human_reply' => 'replied',
            'auto_reply', 'out_of_office', 'auto_ack' => 'auto_reply',
            'hard_bounce' => 'hard_bounced',
            default => 'active',
        };
    }
}
