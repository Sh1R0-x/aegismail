<?php

namespace App\Services\Crm;

use App\Models\Contact;
use App\Models\MailboxAccount;
use App\Models\MailCampaign;
use App\Models\MailDraft;
use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Models\Organization;
use App\Services\SettingsStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CrmPageDataService
{
    public function __construct(
        private readonly SettingsStore $settingsStore,
        private readonly CrmManagementService $crmManagementService,
        private readonly ContactImportService $contactImportService,
    ) {}

    public function dashboard(): array
    {
        $generalSettings = $this->settingsStore->get('general', config('mailing.defaults.general', []));
        $mailbox = MailboxAccount::query()
            ->where('provider', config('mailing.mailbox_provider', 'ovh_mx_plan'))
            ->first();

        $scheduledSends = MailDraft::query()
            ->with(['campaigns' => fn ($query) => $query->withCount('recipients')])
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')
            ->get()
            ->map(function (MailDraft $draft): array {
                $recipientCount = (int) $draft->campaigns->sum('recipients_count');

                if ($recipientCount === 0 && $draft->mode === 'single') {
                    $recipientCount = 1;
                }

                return [
                    'id' => $draft->id,
                    'subject' => $draft->subject !== '' ? $draft->subject : '(Sans objet)',
                    'recipientCount' => $recipientCount,
                    'scheduledAt' => $this->formatDateTime($draft->scheduled_at),
                ];
            })
            ->values();

        $sentRecipients = MailRecipient::query()
            ->where(function (Builder $query): void {
                $query->whereNotNull('sent_at')
                    ->orWhereIn('status', $this->sentRecipientStatuses());
            })
            ->count();

        $bouncedRecipients = MailRecipient::query()
            ->whereIn('status', ['soft_bounced', 'hard_bounced'])
            ->count();

        $recentReplies = MailMessage::query()
            ->where('direction', 'in')
            ->where('classification', 'human_reply')
            ->orderByDesc('received_at')
            ->limit(10)
            ->get()
            ->map(fn (MailMessage $message): array => [
                'id' => $message->id,
                'status' => 'replied',
                'from' => $message->from_email,
                'subject' => $message->subject !== '' ? $message->subject : '(Sans objet)',
                'time' => $this->formatDateTime($message->received_at),
            ])
            ->values();

        $recentAlerts = MailMessage::query()
            ->where('direction', 'in')
            ->whereIn('classification', ['auto_reply', 'out_of_office', 'auto_ack', 'soft_bounce', 'hard_bounce', 'system', 'failed'])
            ->orderByDesc('received_at')
            ->limit(10)
            ->get()
            ->map(fn (MailMessage $message): array => [
                'id' => $message->id,
                'status' => $this->mapAlertStatus($message->classification),
                'email' => $message->from_email,
                'detail' => $this->describeAlert($message),
                'time' => $this->formatDateTime($message->received_at),
            ])
            ->values();

        return [
            'stats' => [
                'sentToday' => MailMessage::query()
                    ->where('direction', 'out')
                    ->whereDate('sent_at', now()->toDateString())
                    ->count(),
                'dailyLimit' => (int) ($generalSettings['daily_limit_default'] ?? 100),
                'healthStatus' => $this->mapHealthStatus($mailbox?->health_status),
                'bounceRate' => $sentRecipients > 0
                    ? (int) round(($bouncedRecipients / $sentRecipients) * 100)
                    : 0,
                'activeCampaigns' => MailCampaign::query()
                    ->whereIn('status', ['scheduled', 'queued', 'sending'])
                    ->count(),
                'scheduledCount' => $scheduledSends->count(),
            ],
            'recentReplies' => $recentReplies->all(),
            'recentAlerts' => $recentAlerts->all(),
            'scheduledSends' => $scheduledSends->all(),
        ];
    }

    public function contacts(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $scoreSettings = $this->settingsStore->get('general', config('mailing.defaults.general', []));

        $contacts = Contact::query()
            ->with([
                'organization:id,name',
                'contactEmails' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('id'),
                'mailRecipients' => fn ($query) => $query->orderByDesc('last_event_at'),
                'threads:id,contact_id,last_message_at',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhereHas('organization', fn (Builder $organizationQuery) => $organizationQuery
                            ->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('contactEmails', fn (Builder $emailQuery) => $emailQuery
                            ->where('email', 'like', "%{$search}%"));
                });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Contact $contact): array => $this->mapContact($contact, $scoreSettings));

        return [
            'contacts' => $this->applyContactFilters($contacts, $filters)->values()->all(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? 'all',
                'score' => $filters['score'] ?? 'all',
            ],
            'organizations' => $this->crmManagementService->organizationOptions(),
            'capabilities' => [
                'canCreate' => true,
                'createEndpoint' => '/api/contacts',
                'organizationRequired' => true,
                'imports' => [
                    'moduleKey' => 'contacts_organizations',
                    'moduleEndpoint' => '/api/import-export',
                    'pagePath' => '/contacts/imports',
                    'canImport' => true,
                    'canExport' => true,
                    'exportEndpoint' => '/api/import-export/export',
                    'previewEndpoint' => '/api/contacts/imports/preview',
                    'confirmEndpoint' => '/api/contacts/imports',
                    'templateEndpoint' => '/api/contacts/imports/template',
                ],
            ],
            'importExportModule' => $this->contactImportService->modulePayload(),
            'recentImports' => $this->contactImportService->recentImports(),
        ];
    }

    public function organizations(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $organizations = Organization::query()
            ->withCount('contacts')
            ->withCount([
                'mailRecipients as sent_count' => function (Builder $query): void {
                    $query->where(function (Builder $nestedQuery): void {
                        $nestedQuery->whereNotNull('sent_at')
                            ->orWhereIn('status', $this->sentRecipientStatuses());
                    });
                },
            ])
            ->withMax('mailThreads as last_thread_activity_at', 'last_message_at')
            ->withMax('mailRecipients as last_recipient_activity_at', 'last_event_at')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('domain', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
                'domain' => $organization->domain,
                'contactCount' => (int) $organization->contacts_count,
                'sentCount' => (int) $organization->sent_count,
                'lastActivityAt' => $this->formatDateTime(
                    $this->latestDate([
                        $organization->last_thread_activity_at,
                        $organization->last_recipient_activity_at,
                    ])
                ),
            ])
            ->values();

        return [
            'organizations' => $organizations->all(),
            'filters' => [
                'search' => $filters['search'] ?? '',
            ],
            'capabilities' => [
                'canCreate' => true,
                'createEndpoint' => '/api/organizations',
            ],
        ];
    }

    public function contact(Contact $contact): array
    {
        return [
            'contact' => $this->crmManagementService->serializeContactDetail($contact),
            'organizations' => $this->crmManagementService->organizationOptions(),
        ];
    }

    public function organization(Organization $organization): array
    {
        return [
            'organization' => $this->crmManagementService->serializeOrganizationDetail($organization),
        ];
    }

    private function mapContact(Contact $contact, array $scoreSettings): array
    {
        [$firstName, $lastName] = $this->resolveContactNames($contact);
        $primaryEmail = $contact->contactEmails->first();
        $unsubscribed = $contact->contactEmails->contains(fn ($email) => $email->opt_out_at !== null)
            || $contact->mailRecipients->contains(fn ($recipient) => $recipient->status === 'unsubscribed' || $recipient->unsubscribe_at !== null);
        $excluded = $contact->contactEmails->contains(fn ($email) => $email->bounce_status === 'hard_bounced')
            || $contact->mailRecipients->contains(fn ($recipient) => $recipient->status === 'hard_bounced');
        $score = $this->calculateContactScore($contact->mailRecipients, $scoreSettings);

        return [
            'id' => $contact->id,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'fullName' => $contact->full_name ?: trim($firstName.' '.$lastName),
            'title' => $contact->job_title,
            'organization' => $contact->organization?->name,
            'organizationId' => $contact->organization_id,
            'organizationName' => $contact->organization?->name,
            'email' => $primaryEmail?->email ?? '',
            'linkedinUrl' => $contact->linkedin_url,
            'phone' => $contact->phone_landline ?: $contact->phone_mobile ?: $contact->phone,
            'phoneLandline' => $contact->phone_landline,
            'phoneMobile' => $contact->phone_mobile && $contact->phone_mobile !== ($contact->phone_landline ?: $contact->phone) ? $contact->phone_mobile : null,
            'score' => $score,
            'scoreLevel' => $this->resolveScoreLevel($score, $excluded, $unsubscribed),
            'excluded' => $excluded,
            'unsubscribed' => $unsubscribed,
            'lastActivityAt' => $this->formatDateTime($this->resolveContactLastActivity($contact)),
        ];
    }

    private function calculateContactScore(Collection $recipients, array $scoreSettings): int
    {
        return
            ($recipients->where('status', 'opened')->count() * (int) ($scoreSettings['open_points'] ?? 1))
            + ($recipients->where('status', 'clicked')->count() * (int) ($scoreSettings['click_points'] ?? 2))
            + ($recipients->where('status', 'replied')->count() * (int) ($scoreSettings['reply_points'] ?? 8))
            + ($recipients->where('status', 'auto_replied')->count() * (int) ($scoreSettings['auto_reply_points'] ?? 0))
            + ($recipients->where('status', 'soft_bounced')->count() * (int) ($scoreSettings['soft_bounce_points'] ?? -5))
            + ($recipients->where('status', 'hard_bounced')->count() * (int) ($scoreSettings['hard_bounce_points'] ?? -15))
            + ($recipients->where('status', 'unsubscribed')->count() * (int) ($scoreSettings['unsubscribe_points'] ?? -20));
    }

    private function resolveScoreLevel(int $score, bool $excluded, bool $unsubscribed): string
    {
        if ($excluded || $unsubscribed) {
            return 'excluded';
        }

        return match (true) {
            $score >= 8 => 'engaged',
            $score >= 4 => 'interested',
            $score >= 1 => 'warm',
            default => 'cold',
        };
    }

    private function resolveContactLastActivity(Contact $contact): ?Carbon
    {
        return $this->latestDate([
            $contact->contactEmails->map(fn ($email) => $email->last_seen_at)->all(),
            $contact->threads->map(fn ($thread) => $thread->last_message_at)->all(),
            $contact->mailRecipients->flatMap(fn ($recipient) => [
                $recipient->last_event_at,
                $recipient->sent_at,
                $recipient->replied_at,
                $recipient->auto_replied_at,
                $recipient->bounced_at,
                $recipient->unsubscribe_at,
            ])->all(),
        ]);
    }

    private function resolveContactNames(Contact $contact): array
    {
        $firstName = $contact->first_name ?? '';
        $lastName = $contact->last_name ?? '';

        if (($firstName === '' || $lastName === '') && $contact->full_name !== null && $contact->full_name !== '') {
            $parts = preg_split('/\s+/', trim($contact->full_name)) ?: [];
            $firstName = $firstName !== '' ? $firstName : ($parts[0] ?? '');
            $lastName = $lastName !== '' ? $lastName : trim(implode(' ', array_slice($parts, 1)));
        }

        return [$firstName, $lastName];
    }

    private function applyContactFilters(Collection $contacts, array $filters): Collection
    {
        $status = $filters['status'] ?? 'all';
        $score = $filters['score'] ?? 'all';

        $contacts = match ($status) {
            'active' => $contacts->filter(fn (array $contact) => ! $contact['excluded'] && ! $contact['unsubscribed']),
            'bounced' => $contacts->filter(fn (array $contact) => $contact['excluded']),
            'unsubscribed' => $contacts->filter(fn (array $contact) => $contact['unsubscribed']),
            default => $contacts,
        };

        return match ($score) {
            'engaged', 'interested', 'warm', 'cold', 'excluded' => $contacts->filter(
                fn (array $contact) => $contact['scoreLevel'] === $score
            ),
            default => $contacts,
        };
    }

    private function sentRecipientStatuses(): array
    {
        return [
            'sent',
            'delivered_if_known',
            'opened',
            'clicked',
            'replied',
            'auto_replied',
            'soft_bounced',
            'hard_bounced',
            'unsubscribed',
        ];
    }

    private function mapHealthStatus(?string $healthStatus): string
    {
        return match ($healthStatus) {
            'healthy' => 'good',
            'warning' => 'degraded',
            'critical' => 'critical',
            default => 'unknown',
        };
    }

    private function mapAlertStatus(string $classification): string
    {
        return match ($classification) {
            'soft_bounce' => 'soft_bounced',
            'hard_bounce' => 'hard_bounced',
            default => 'auto_replied',
        };
    }

    private function describeAlert(MailMessage $message): string
    {
        $label = match ($message->classification) {
            'out_of_office' => 'Absence automatique',
            'auto_ack' => 'Accusé automatique',
            'soft_bounce' => 'Rebond temporaire',
            'hard_bounce' => 'Rebond permanent',
            'system' => 'Message système',
            default => 'Réponse automatique',
        };

        return $message->subject !== ''
            ? "{$label} · {$message->subject}"
            : $label;
    }

    private function latestDate(array $dates): ?Carbon
    {
        $resolved = collect($dates)
            ->flatten()
            ->filter()
            ->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))
            ->sortByDesc(fn (Carbon $value) => $value->getTimestamp())
            ->first();

        return $resolved?->copy();
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof Carbon ? $value : Carbon::parse($value);

        return $date->timezone(config('app.timezone'))->toIso8601String();
    }
}
