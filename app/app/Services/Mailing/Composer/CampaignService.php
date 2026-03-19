<?php

namespace App\Services\Mailing\Composer;

use App\Models\MailboxAccount;
use App\Models\MailCampaign;
use App\Models\MailDraft;
use App\Models\MailRecipient;
use App\Services\Mailing\MailEventLogger;
use App\Services\SettingsStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    public function __construct(
        private readonly DraftPreflightService $preflightService,
        private readonly MailEventLogger $eventLogger,
        private readonly SettingsStore $settingsStore,
    ) {}

    public function list(): array
    {
        return MailCampaign::query()
            ->with('recipients')
            ->orderByDesc('last_edited_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (MailCampaign $campaign) => $this->serializeListItem($campaign))
            ->all();
    }

    public function syncAutosavedCampaign(
        MailDraft $draft,
        MailboxAccount $mailbox,
        ?string $name = null,
        ?int $userId = null,
    ): MailCampaign {
        $existingCampaign = MailCampaign::query()->where('draft_id', $draft->id)->first();

        $campaign = MailCampaign::query()->updateOrCreate(
            ['draft_id' => $draft->id],
            [
                'mailbox_account_id' => $mailbox->id,
                'user_id' => $userId ?? $draft->user_id,
                'name' => $name ?: $draft->subject ?: 'Campagne sans nom',
                'mode' => $draft->mode,
                'status' => in_array((string) $existingCampaign?->status, ['scheduled', 'queued', 'sending', 'sent'], true)
                    ? $existingCampaign->status
                    : 'draft',
                'last_edited_at' => now(),
            ],
        );

        return $campaign->fresh('recipients');
    }

    public function createFromDraft(
        MailDraft $draft,
        MailboxAccount $mailbox,
        ?string $name = null,
        ?Carbon $scheduledAt = null,
    ): array {
        $draft->loadMissing('attachments');
        $preflight = $this->preflightService->run($draft, $mailbox);

        if (! $preflight['ok']) {
            return [null, $preflight];
        }

        $generalSettings = $this->settingsStore->get('general', config('mailing.defaults.general', []));
        $mailSettings = $this->settingsStore->get('mail', config('mailing.defaults.mail', []));

        $campaign = DB::transaction(function () use ($draft, $mailbox, $name, $scheduledAt, $preflight, $generalSettings, $mailSettings): MailCampaign {
            $campaign = MailCampaign::query()->updateOrCreate(
                ['draft_id' => $draft->id],
                [
                    'mailbox_account_id' => $mailbox->id,
                    'user_id' => $draft->user_id,
                    'name' => $name ?: $draft->subject,
                    'mode' => $draft->mode,
                    'status' => $scheduledAt ? 'scheduled' : 'draft',
                    'send_window_json' => [
                        'start' => $mailSettings['send_window_start'] ?? config('mailing.defaults.mail.send_window_start'),
                        'end' => $mailSettings['send_window_end'] ?? config('mailing.defaults.mail.send_window_end'),
                    ],
                    'throttling_json' => [
                        'dailyLimit' => $generalSettings['daily_limit_default'] ?? config('mailing.defaults.general.daily_limit_default'),
                        'hourlyLimit' => $generalSettings['hourly_limit_default'] ?? config('mailing.defaults.general.hourly_limit_default'),
                    ],
                ],
            );

            $campaign->recipients()->delete();

            foreach ($preflight['deliverableRecipients'] as $recipient) {
                MailRecipient::query()->create([
                    'campaign_id' => $campaign->id,
                    'organization_id' => $recipient['organizationId'],
                    'contact_id' => $recipient['contactId'],
                    'contact_email_id' => $recipient['contactEmailId'],
                    'email' => $recipient['email'],
                    'status' => 'draft',
                ]);
            }

            $this->eventLogger->log(
                'mail_campaign.created_from_draft',
                [
                    'draft_id' => $draft->id,
                    'scheduled_at' => $scheduledAt?->toIso8601String(),
                    'recipient_count' => count($preflight['deliverableRecipients']),
                    'queue' => config('mailing.queues.outbound'),
                ],
                [
                    'mailbox_account_id' => $mailbox->id,
                    'campaign_id' => $campaign->id,
                ],
                'mail_campaign.created_from_draft.'.$campaign->id.'.'.($scheduledAt?->timestamp ?? 'draft'),
            );

            return $campaign->refresh();
        });

        return [$campaign->load('recipients'), $preflight];
    }

    public function unschedule(MailDraft $draft): ?MailCampaign
    {
        $campaign = MailCampaign::query()->where('draft_id', $draft->id)->first();

        if ($campaign === null) {
            return null;
        }

        DB::transaction(function () use ($campaign): void {
            $campaign->forceFill(['status' => 'draft'])->save();
            $campaign->recipients()->update([
                'scheduled_for' => null,
                'status' => 'draft',
            ]);

            $this->eventLogger->log(
                'mail_campaign.unscheduled',
                ['campaign_id' => $campaign->id],
                [
                    'mailbox_account_id' => $campaign->mailbox_account_id,
                    'campaign_id' => $campaign->id,
                ],
                'mail_campaign.unscheduled.'.$campaign->id,
            );
        });

        return $campaign->fresh('recipients');
    }

    public function serialize(MailCampaign $campaign): array
    {
        $campaign->loadMissing(['recipients', 'draft']);

        return [
            'id' => $campaign->id,
            'draftId' => $campaign->draft_id,
            'name' => $campaign->name,
            'status' => $campaign->status,
            'type' => $campaign->mode === 'bulk' ? 'multiple' : 'single',
            'recipientCount' => $campaign->recipients->count() > 0
                ? $campaign->recipients->count()
                : count($campaign->draft?->payload_json['recipients'] ?? []),
            'progressPercent' => $this->progressPercent($campaign),
            'openCount' => $campaign->recipients->whereIn('status', ['opened', 'clicked', 'replied', 'auto_replied'])->count(),
            'replyCount' => $campaign->recipients->where('status', 'replied')->count(),
            'bounceCount' => $campaign->recipients->whereIn('status', ['soft_bounced', 'hard_bounced'])->count(),
            'scheduledAt' => $campaign->recipients->sortBy('scheduled_for')->first()?->scheduled_for?->timezone(config('app.timezone'))->toIso8601String(),
            'createdAt' => $campaign->created_at?->toIso8601String(),
            'updatedAt' => $campaign->updated_at?->timezone(config('app.timezone'))->toIso8601String(),
            'lastEditedAt' => $campaign->last_edited_at?->toIso8601String(),
        ];
    }

    public function serializeListItem(MailCampaign $campaign): array
    {
        return $this->serialize($campaign);
    }

    public function serializeDetail(MailCampaign $campaign, array $draftPayload): array
    {
        $campaign->loadMissing(['recipients.contact.organization', 'recipients.contactEmail', 'draft']);

        return array_merge($this->serialize($campaign), [
            'draft' => $draftPayload,
            'recipients' => $campaign->recipients->isNotEmpty()
                ? $campaign->recipients
                    ->sortBy('scheduled_for')
                    ->values()
                    ->map(fn (MailRecipient $recipient): array => [
                        'id' => $recipient->id,
                        'email' => $recipient->email,
                        'status' => $recipient->status,
                        'contactName' => trim((string) ($recipient->contact?->first_name.' '.$recipient->contact?->last_name)) ?: null,
                        'organization' => $recipient->organization?->name,
                        'scheduledFor' => $recipient->scheduled_for?->timezone(config('app.timezone'))->toIso8601String(),
                        'lastEventAt' => $recipient->last_event_at?->timezone(config('app.timezone'))->toIso8601String(),
                    ])->all()
                : collect($draftPayload['recipients'] ?? [])
                    ->map(fn (array $recipient, int $index): array => [
                        'id' => null,
                        'email' => $recipient['email'] ?? null,
                        'status' => 'draft',
                        'contactName' => $recipient['name'] ?? null,
                        'organization' => $recipient['organizationName'] ?? null,
                        'scheduledFor' => null,
                        'lastEventAt' => null,
                        'position' => $index,
                    ])->all(),
        ]);
    }

    public function clone(MailCampaign $campaign): MailCampaign
    {
        $campaign->loadMissing(['draft.attachments']);

        $sourceDraft = $campaign->draft;

        return DB::transaction(function () use ($campaign, $sourceDraft): MailCampaign {
            $newDraft = MailDraft::query()->create([
                'mailbox_account_id' => $campaign->mailbox_account_id,
                'user_id' => $campaign->user_id,
                'mode' => $campaign->mode,
                'template_id' => $sourceDraft?->template_id,
                'subject' => $sourceDraft?->subject ?? $campaign->name,
                'html_body' => $sourceDraft?->html_body,
                'text_body' => $sourceDraft?->text_body,
                'signature_snapshot' => $sourceDraft?->signature_snapshot,
                'payload_json' => $sourceDraft?->payload_json ?? ['recipients' => []],
                'status' => 'draft',
                'scheduled_at' => null,
            ]);

            if ($sourceDraft !== null) {
                foreach ($sourceDraft->attachments as $attachment) {
                    $newDraft->attachments()->create([
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'size_bytes' => $attachment->size_bytes,
                        'storage_disk' => $attachment->storage_disk,
                        'storage_path' => $attachment->storage_path,
                        'content_id' => $attachment->content_id,
                        'disposition' => $attachment->disposition,
                    ]);
                }
            }

            $newCampaign = MailCampaign::query()->create([
                'mailbox_account_id' => $campaign->mailbox_account_id,
                'user_id' => $campaign->user_id,
                'name' => $campaign->name.' (copie)',
                'mode' => $campaign->mode,
                'draft_id' => $newDraft->id,
                'status' => 'draft',
                'send_window_json' => $campaign->send_window_json,
                'throttling_json' => $campaign->throttling_json,
                'last_edited_at' => now(),
                'started_at' => null,
                'completed_at' => null,
            ]);

            $this->eventLogger->log(
                'mail_campaign.cloned',
                [
                    'source_campaign_id' => $campaign->id,
                    'new_campaign_id' => $newCampaign->id,
                    'new_draft_id' => $newDraft->id,
                ],
                [
                    'mailbox_account_id' => $campaign->mailbox_account_id,
                    'campaign_id' => $newCampaign->id,
                ],
                'mail_campaign.cloned.'.$campaign->id.'.'.$newCampaign->id,
            );

            return $newCampaign->load('recipients');
        });
    }

    private function progressPercent(MailCampaign $campaign): int
    {
        $total = max($campaign->recipients->count(), 1);
        $completed = $campaign->recipients->whereIn('status', [
            'sent',
            'delivered_if_known',
            'opened',
            'clicked',
            'replied',
            'auto_replied',
            'soft_bounced',
            'hard_bounced',
            'unsubscribed',
            'failed',
            'cancelled',
        ])->count();

        return (int) floor(($completed / $total) * 100);
    }
}
