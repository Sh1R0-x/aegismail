<?php

namespace App\Services\Mailing\Composer;

use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Services\SettingsStore;
use Illuminate\Support\Carbon;

class ComposerPageDataService
{
    public function __construct(
        private readonly TemplateService $templateService,
        private readonly DraftService $draftService,
        private readonly CampaignService $campaignService,
        private readonly SettingsStore $settingsStore,
    ) {
    }

    public function templates(): array
    {
        return [
            'templates' => $this->templateService->list(),
        ];
    }

    public function drafts(): array
    {
        return [
            'drafts' => $this->draftService->list(),
            'templates' => $this->templateService->list(),
        ];
    }

    public function campaigns(): array
    {
        return [
            'campaigns' => $this->campaignService->list(),
            'creationFlow' => [
                'type' => 'draft_first',
                'entryHref' => '/campaigns/create',
                'actionLabel' => 'Préparer une campagne',
                'helperText' => 'Le module Campagnes conserve une couche draft technique interne, mais l’utilisateur prépare, édite et planifie ses campagnes depuis /campaigns.',
            ],
        ];
    }

    public function mails(array $filters = []): array
    {
        $generalSettings = $this->settingsStore->get('general', config('mailing.defaults.general', []));
        $status = (string) ($filters['status'] ?? 'all');

        $query = MailRecipient::query()
            ->with([
                'campaign' => fn ($q) => $q->select(['id', 'draft_id', 'mode'])->with('draft:id,subject'),
                'messages' => fn ($q) => $q->select(['id', 'recipient_id', 'thread_id'])->orderByDesc('id'),
            ]);

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $recipients = $query
            ->orderByDesc('last_event_at')
            ->limit(200)
            ->get()
            ->map(function (MailRecipient $r): array {
                $threadId = $r->messages->sortByDesc('id')->first()?->thread_id;

                return [
                    'id' => $r->id,
                    'email' => $r->email,
                    'subject' => $r->campaign?->draft?->subject ?: '(Sans objet)',
                    'status' => $r->status,
                    'type' => $r->campaign?->mode === 'bulk' ? 'multiple' : 'single',
                    'sentAt' => $this->formatDate($r->sent_at),
                    'campaignId' => $r->campaign_id,
                    'threadId' => $threadId,
                ];
            })
            ->values()
            ->all();

        $sentToday = MailMessage::query()
            ->where('direction', 'out')
            ->whereDate('sent_at', now()->toDateString())
            ->count();

        return [
            'recipients' => $recipients,
            'stats' => [
                'sentToday' => $sentToday,
                'dailyLimit' => (int) ($generalSettings['daily_limit_default'] ?? 100),
            ],
            'templates' => $this->templateService->list(),
            'filters' => [
                'status' => $status ?: 'all',
            ],
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof Carbon ? $value : Carbon::parse($value);

        return $date->timezone(config('app.timezone'))->format('Y-m-d H:i');
    }
}
