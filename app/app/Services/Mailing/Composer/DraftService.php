<?php

namespace App\Services\Mailing\Composer;

use App\Models\MailDraft;
use App\Models\MailboxAccount;
use App\Services\Mailing\MailEventLogger;
use App\Services\Mailing\MailboxSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DraftService
{
    public function __construct(
        private readonly MailboxSettingsService $mailboxSettingsService,
        private readonly MailEventLogger $eventLogger,
        private readonly CampaignService $campaignService,
        private readonly DraftPreflightService $preflightService,
    ) {
    }

    public function list(): array
    {
        return MailDraft::query()
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

        $mailSettings = $this->mailboxSettingsService->getSettings();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => $mailbox->id,
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
            ['draft_id' => $draft->id],
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

    public function preflight(MailDraft $draft): array
    {
        $draft->loadMissing('attachments');

        $preflight = $this->preflightService->run($draft, $this->mailbox());

        $this->eventLogger->log(
            'mail_draft.preflight_ran',
            [
                'draft_id' => $draft->id,
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
        $mailbox = $this->mailbox();

        if ($mailbox === null) {
            throw ValidationException::withMessages([
                'mailbox' => ['La boîte OVH MX Plan doit être configurée avant la planification.'],
            ]);
        }

        [$campaign, $preflight] = $this->campaignService->createFromDraft($draft->fresh(['attachments']), $mailbox, $name, $scheduledAt);

        if ($campaign === null) {
            throw ValidationException::withMessages([
                'preflight' => ['Le preflight contient des erreurs bloquantes.'],
            ]);
        }

        $draft->forceFill([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ])->save();

        $this->eventLogger->log(
            'mail_draft.scheduled',
            [
                'draft_id' => $draft->id,
                'campaign_id' => $campaign->id,
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
        $draft->forceFill([
            'status' => 'draft',
            'scheduled_at' => null,
        ])->save();

        $campaign = $this->campaignService->unschedule($draft);

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

    public function serialize(MailDraft $draft): array
    {
        $draft->loadMissing(['campaigns.recipients', 'attachments']);

        return [
            'id' => $draft->id,
            'templateId' => $draft->template_id,
            'type' => $draft->mode === 'bulk' ? 'multiple' : 'single',
            'subject' => $draft->subject,
            'htmlBody' => $draft->html_body,
            'textBody' => $draft->text_body,
            'signatureHtml' => $draft->signature_snapshot,
            'status' => $draft->status,
            'scheduledAt' => $draft->scheduled_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'recipientCount' => $this->recipientCount($draft),
            'recipients' => array_values($draft->payload_json['recipients'] ?? []),
            'attachmentCount' => $draft->attachments->count(),
            'updatedAt' => $draft->updated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
        ];
    }

    public function serializeListItem(MailDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'subject' => $draft->subject,
            'recipientCount' => $this->recipientCount($draft),
            'type' => $draft->mode === 'bulk' ? 'multiple' : 'single',
            'status' => $draft->status,
            'scheduledAt' => $draft->scheduled_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'updatedAt' => $draft->updated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
        ];
    }

    public function mailbox(): ?MailboxAccount
    {
        return MailboxAccount::query()->where('provider', config('mailing.provider'))->first();
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
}
