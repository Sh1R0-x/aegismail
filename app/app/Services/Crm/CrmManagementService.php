<?php

namespace App\Services\Crm;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailRecipient;
use App\Models\MailThread;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrmManagementService
{
    public function createOrganization(array $validated): Organization
    {
        return Organization::query()->create([
            'name' => trim($validated['name']),
            'domain' => $this->nullableString(Str::lower((string) ($validated['domain'] ?? ''))),
            'website' => $this->nullableString($validated['website'] ?? null),
            'notes' => $this->nullableString($validated['notes'] ?? null),
        ]);
    }

    public function createContact(array $validated): Contact
    {
        return DB::transaction(function () use ($validated): Contact {
            $contact = Contact::query()->create([
                'organization_id' => $validated['organizationId'],
                'first_name' => $this->nullableString($validated['firstName'] ?? null),
                'last_name' => $this->nullableString($validated['lastName'] ?? null),
                'full_name' => $this->resolvedFullName($validated),
                'job_title' => $this->nullableString($validated['title'] ?? null),
                'phone' => $this->resolvedLegacyPhone($validated),
                'phone_landline' => $this->resolvedLandlinePhone($validated),
                'phone_mobile' => $this->resolvedMobilePhone($validated),
                'linkedin_url' => $this->nullableString($validated['linkedinUrl'] ?? null),
                'country' => $this->nullableString($validated['country'] ?? null),
                'city' => $this->nullableString($validated['city'] ?? null),
                'tags_json' => $this->normalizedTags($validated['tags'] ?? []),
                'notes' => $this->nullableString($validated['notes'] ?? null),
                'status' => $this->nullableString($validated['status'] ?? null),
            ]);

            $contact->contactEmails()->create([
                'email' => Str::lower(trim($validated['email'])),
                'is_primary' => true,
            ]);

            return $contact->fresh(['organization:id,name', 'contactEmails']);
        });
    }

    public function updateOrganization(Organization $organization, array $validated): Organization
    {
        $organization->fill([
            'name' => trim($validated['name']),
            'domain' => $this->nullableString(Str::lower((string) ($validated['domain'] ?? ''))),
            'website' => $this->nullableString($validated['website'] ?? null),
            'notes' => $this->nullableString($validated['notes'] ?? null),
        ])->save();

        return $organization->fresh();
    }

    public function deleteOrganization(Organization $organization): void
    {
        if (Contact::query()->where('organization_id', $organization->id)->exists()) {
            throw ValidationException::withMessages([
                'organization' => ['Cette organisation ne peut pas être supprimée tant que des contacts y sont rattachés.'],
            ]);
        }

        DB::transaction(function () use ($organization): void {
            MailThread::query()->where('organization_id', $organization->id)->update(['organization_id' => null]);
            MailRecipient::query()->where('organization_id', $organization->id)->update(['organization_id' => null]);
            $organization->delete();
        });
    }

    public function updateContact(Contact $contact, array $validated): Contact
    {
        return DB::transaction(function () use ($contact, $validated): Contact {
            $contact->fill([
                'organization_id' => $validated['organizationId'],
                'first_name' => $this->nullableString($validated['firstName'] ?? null),
                'last_name' => $this->nullableString($validated['lastName'] ?? null),
                'full_name' => $this->resolvedFullName($validated),
                'job_title' => $this->nullableString($validated['title'] ?? null),
                'phone' => $this->resolvedLegacyPhone($validated, $contact),
                'phone_landline' => $this->resolvedLandlinePhone($validated, $contact),
                'phone_mobile' => $this->resolvedMobilePhone($validated, $contact),
                'linkedin_url' => $this->nullableString($validated['linkedinUrl'] ?? null),
                'country' => $this->nullableString($validated['country'] ?? null),
                'city' => $this->nullableString($validated['city'] ?? null),
                'tags_json' => $this->normalizedTags($validated['tags'] ?? []),
                'notes' => $this->nullableString($validated['notes'] ?? null),
                'status' => $this->nullableString($validated['status'] ?? null),
            ])->save();

            $primaryEmail = $contact->contactEmails()->where('is_primary', true)->first();

            if ($primaryEmail !== null) {
                $primaryEmail->forceFill([
                    'email' => Str::lower(trim($validated['email'])),
                ])->save();
            } else {
                $contact->contactEmails()->create([
                    'email' => Str::lower(trim($validated['email'])),
                    'is_primary' => true,
                ]);
            }

            return $contact->fresh(['organization:id,name', 'contactEmails']);
        });
    }

    public function deleteContact(Contact $contact): void
    {
        DB::transaction(function () use ($contact): void {
            $contactEmailIds = $contact->contactEmails()->pluck('id');

            MailThread::query()->where('contact_id', $contact->id)->update(['contact_id' => null]);
            MailRecipient::query()
                ->where('contact_id', $contact->id)
                ->update([
                    'contact_id' => null,
                    'contact_email_id' => null,
                ]);

            if ($contactEmailIds->isNotEmpty()) {
                MailRecipient::query()
                    ->whereIn('contact_email_id', $contactEmailIds)
                    ->update(['contact_email_id' => null]);
            }

            $contact->contactEmails()->delete();
            $contact->delete();
        });
    }

    public function addContactEmail(Contact $contact, array $validated): Contact
    {
        return DB::transaction(function () use ($contact, $validated): Contact {
            $isPrimary = (bool) ($validated['isPrimary'] ?? false);

            if ($isPrimary) {
                $contact->contactEmails()->update(['is_primary' => false]);
            }

            $contact->contactEmails()->create([
                'email' => Str::lower(trim($validated['email'])),
                'is_primary' => $isPrimary || $contact->contactEmails()->doesntExist(),
            ]);

            return $contact->fresh(['organization:id,name', 'contactEmails']);
        });
    }

    public function deleteContactEmail(Contact $contact, ContactEmail $contactEmail): Contact
    {
        if ($contactEmail->contact_id !== $contact->id) {
            throw ValidationException::withMessages([
                'email' => ['Cette adresse e-mail n’appartient pas au contact demandé.'],
            ]);
        }

        return DB::transaction(function () use ($contact, $contactEmail): Contact {
            $count = $contact->contactEmails()->count();

            if ($count <= 1) {
                throw ValidationException::withMessages([
                    'email' => ['Le contact doit conserver au moins une adresse e-mail.'],
                ]);
            }

            $wasPrimary = (bool) $contactEmail->is_primary;

            MailRecipient::where('contact_email_id', $contactEmail->id)
                ->update(['contact_email_id' => null]);

            $contactEmail->delete();

            if ($wasPrimary) {
                $replacement = $contact->contactEmails()->orderBy('id')->first();
                $replacement?->forceFill(['is_primary' => true])->save();
            }

            return $contact->fresh(['organization:id,name', 'contactEmails']);
        });
    }

    public function serializeContactDetail(Contact $contact): array
    {
        $contact->loadMissing([
            'organization:id,name',
            'contactEmails',
            'threads.messages',
            'mailRecipients',
        ]);

        return [
            'id' => $contact->id,
            'firstName' => $contact->first_name ?? '',
            'lastName' => $contact->last_name ?? '',
            'fullName' => $contact->full_name,
            'title' => $contact->job_title,
            'primaryEmail' => $contact->contactEmails->sortByDesc('is_primary')->first()?->email,
            'phone' => $this->displayPhone($contact),
            'phoneLandline' => $contact->phone_landline ?: $contact->phone,
            'phoneMobile' => $contact->phone_mobile && $contact->phone_mobile !== ($contact->phone_landline ?: $contact->phone)
                ? $contact->phone_mobile
                : null,
            'linkedinUrl' => $contact->linkedin_url,
            'country' => $contact->country,
            'city' => $contact->city,
            'tags' => $contact->tags_json ?? [],
            'notes' => $contact->notes,
            'status' => $contact->status,
            'organizationId' => $contact->organization_id,
            'organizationName' => $contact->organization?->name,
            'organization' => $contact->organization ? [
                'id' => $contact->organization->id,
                'name' => $contact->organization->name,
            ] : null,
            'emails' => $contact->contactEmails
                ->sortByDesc('is_primary')
                ->values()
                ->map(fn (ContactEmail $email): array => [
                    'id' => $email->id,
                    'email' => $email->email,
                    'isPrimary' => $email->is_primary,
                    'optedOutAt' => $email->opt_out_at?->toIso8601String(),
                    'bounceStatus' => $email->bounce_status,
                    'lastSeenAt' => $email->last_seen_at?->timezone(config('app.timezone'))->toIso8601String(),
                    'canDelete' => ! $email->is_primary || $contact->contactEmails->count() > 1,
                ])->all(),
            'recentThreads' => $contact->threads
                ->sortByDesc('last_message_at')
                ->take(10)
                ->values()
                ->map(fn (MailThread $thread): array => [
                    'id' => $thread->id,
                    'subject' => $thread->messages->sortByDesc('created_at')->first()?->subject ?: $thread->subject_canonical,
                    'lastActivityAt' => $thread->last_message_at?->timezone(config('app.timezone'))->toIso8601String(),
                    'lastDirection' => $thread->last_direction,
                    'replyReceived' => $thread->reply_received,
                    'autoReplyReceived' => $thread->auto_reply_received,
                ])->all(),
            'stats' => [
                'threadCount' => $contact->threads->count(),
                'recipientCount' => $contact->mailRecipients->count(),
                'lastActivityAt' => $this->latestDate([
                    $contact->threads->pluck('last_message_at')->all(),
                    $contact->mailRecipients->pluck('last_event_at')->all(),
                    $contact->contactEmails->pluck('last_seen_at')->all(),
                ])?->timezone(config('app.timezone'))->toIso8601String(),
            ],
        ];
    }

    public function serializeOrganizationDetail(Organization $organization): array
    {
        $organization->loadMissing([
            'contacts.organization',
            'contacts.contactEmails',
            'mailThreads.messages',
        ]);

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'domain' => $organization->domain,
            'website' => $organization->website,
            'notes' => $organization->notes,
            'contactCount' => $organization->contacts->count(),
            'sentCount' => MailRecipient::query()->where('organization_id', $organization->id)->count(),
            'lastActivityAt' => $this->latestDate([
                $organization->mailThreads->pluck('last_message_at')->all(),
                MailRecipient::query()->where('organization_id', $organization->id)->pluck('last_event_at')->all(),
            ])?->timezone(config('app.timezone'))->toIso8601String(),
            'contacts' => $organization->contacts
                ->sortBy(fn (Contact $contact) => mb_strtolower(trim(($contact->last_name ?? '').' '.($contact->first_name ?? ''))))
                ->values()
                ->map(fn (Contact $contact): array => [
                    'id' => $contact->id,
                    'name' => trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')),
                    'title' => $contact->job_title,
                    'email' => $contact->contactEmails->sortByDesc('is_primary')->first()?->email,
                ])->all(),
            'recentThreads' => $organization->mailThreads
                ->sortByDesc('last_message_at')
                ->take(10)
                ->values()
                ->map(fn (MailThread $thread): array => [
                    'id' => $thread->id,
                    'subject' => $thread->messages->sortByDesc('created_at')->first()?->subject ?: $thread->subject_canonical,
                    'contactName' => trim((string) ($thread->contact?->first_name.' '.$thread->contact?->last_name)) ?: null,
                    'lastActivityAt' => $thread->last_message_at?->timezone(config('app.timezone'))->toIso8601String(),
                    'lastDirection' => $thread->last_direction,
                    'replyReceived' => $thread->messages->contains(fn ($m) => $m->direction === 'in' && $m->classification === 'human'),
                    'autoReplyReceived' => $thread->messages->contains(fn ($m) => $m->direction === 'in' && $m->classification === 'auto_reply'),
                ])->all(),
        ];
    }

    public function organizationOptions(): array
    {
        return Organization::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
            ])->all();
    }

    public function campaignAudienceContacts(int $limit = 200): array
    {
        return Contact::query()
            ->with([
                'organization:id,name,domain',
                'contactEmails' => fn ($query) => $query
                    ->where('is_primary', true)
                    ->orderByDesc('is_primary')
                    ->orderBy('id'),
            ])
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (Contact $contact): array => $this->serializeAudienceContact($contact))
            ->all();
    }

    public function campaignAudienceOrganizations(): array
    {
        return Organization::query()
            ->with([
                'contacts.organization',
                'contacts.contactEmails' => fn ($query) => $query
                    ->where('is_primary', true)
                    ->orderByDesc('is_primary')
                    ->orderBy('id'),
            ])
            ->withCount('contacts')
            ->orderBy('name')
            ->get()
            ->map(function (Organization $organization): array {
                return [
                    'organizationId' => $organization->id,
                    'organizationName' => $organization->name,
                    'domain' => $organization->domain,
                    'contactCount' => (int) $organization->contacts_count,
                    'contacts' => $organization->contacts
                        ->filter(fn (Contact $contact) => $contact->organization_id !== null)
                        ->map(fn (Contact $contact): array => $this->serializeAudienceContact($contact))
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    public function serializeOrganization(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'domain' => $organization->domain,
            'contactCount' => (int) ($organization->contacts_count ?? 0),
            'sentCount' => (int) ($organization->sent_count ?? 0),
            'lastActivityAt' => null,
        ];
    }

    public function serializeContact(Contact $contact): array
    {
        $firstName = $contact->first_name ?? '';
        $lastName = $contact->last_name ?? '';

        if (($firstName === '' || $lastName === '') && filled($contact->full_name)) {
            $parts = preg_split('/\s+/', trim($contact->full_name)) ?: [];
            $firstName = $firstName !== '' ? $firstName : ($parts[0] ?? '');
            $lastName = $lastName !== '' ? $lastName : trim(implode(' ', array_slice($parts, 1)));
        }

        return [
            'id' => $contact->id,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'fullName' => $contact->full_name ?: trim($firstName.' '.$lastName),
            'title' => $contact->job_title,
            'organization' => $contact->organization?->name,
            'organizationId' => $contact->organization_id,
            'organizationName' => $contact->organization?->name,
            'email' => $contact->contactEmails->first()?->email ?? '',
            'linkedinUrl' => $contact->linkedin_url,
            'phone' => $this->displayPhone($contact),
            'phoneLandline' => $contact->phone_landline ?: $contact->phone,
            'phoneMobile' => $contact->phone_mobile,
            'score' => 0,
            'scoreLevel' => 'cold',
            'excluded' => false,
            'unsubscribed' => false,
            'lastActivityAt' => null,
        ];
    }

    private function serializeAudienceContact(Contact $contact): array
    {
        $primaryEmail = $contact->contactEmails->sortByDesc('is_primary')->first();

        return [
            'contactId' => $contact->id,
            'contactEmailId' => $primaryEmail?->id,
            'organizationId' => $contact->organization_id,
            'organizationName' => $contact->organization?->name,
            'email' => $primaryEmail?->email,
            'name' => $contact->full_name ?: trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')),
            'jobTitle' => $contact->job_title,
        ];
    }

    private function resolvedFullName(array $validated): ?string
    {
        $fullName = $this->nullableString($validated['fullName'] ?? null);

        if ($fullName !== null) {
            return $fullName;
        }

        $firstName = trim((string) ($validated['firstName'] ?? ''));
        $lastName = trim((string) ($validated['lastName'] ?? ''));
        $combined = trim($firstName.' '.$lastName);

        return $combined !== '' ? $combined : null;
    }

    private function nullableString(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizedTags(array $tags): array
    {
        return collect($tags)
            ->map(fn ($tag): string => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolvedLandlinePhone(array $validated, ?Contact $contact = null): ?string
    {
        return $this->normalizedPhone(
            $validated['phoneLandline']
            ?? $validated['phone']
            ?? $contact?->phone_landline
            ?? $contact?->phone
        );
    }

    private function resolvedMobilePhone(array $validated, ?Contact $contact = null): ?string
    {
        return $this->normalizedPhone(
            $validated['phoneMobile']
            ?? $contact?->phone_mobile
        );
    }

    private function resolvedLegacyPhone(array $validated, ?Contact $contact = null): ?string
    {
        return $this->firstNonEmptyString([
            $validated['phoneLandline'] ?? null,
            $validated['phone'] ?? null,
            $validated['phoneMobile'] ?? null,
            $contact?->phone_landline,
            $contact?->phone,
            $contact?->phone_mobile,
        ]);
    }

    private function displayPhone(Contact $contact): ?string
    {
        return $this->firstNonEmptyString([
            $contact->phone_landline,
            $contact->phone_mobile,
            $contact->phone,
        ]);
    }

    private function normalizedPhone(mixed $value): ?string
    {
        $phone = preg_replace('/\s+/', ' ', trim((string) $value));

        return $phone !== '' ? $phone : null;
    }

    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            $normalized = $this->nullableString($value);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function latestDate(array $dates): ?Carbon
    {
        return collect($dates)
            ->flatten()
            ->filter()
            ->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))
            ->sortByDesc(fn (Carbon $value) => $value->getTimestamp())
            ->first();
    }
}
