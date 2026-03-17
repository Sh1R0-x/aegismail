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
                'organization_id' => $validated['organizationId'] ?? null,
                'first_name' => $this->nullableString($validated['firstName'] ?? null),
                'last_name' => $this->nullableString($validated['lastName'] ?? null),
                'full_name' => $this->resolvedFullName($validated),
                'job_title' => $this->nullableString($validated['title'] ?? null),
                'phone' => $this->nullableString($validated['phone'] ?? null),
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
        DB::transaction(function () use ($organization): void {
            Contact::query()->where('organization_id', $organization->id)->update(['organization_id' => null]);
            MailThread::query()->where('organization_id', $organization->id)->update(['organization_id' => null]);
            MailRecipient::query()->where('organization_id', $organization->id)->update(['organization_id' => null]);

            $organization->delete();
        });
    }

    public function updateContact(Contact $contact, array $validated): Contact
    {
        return DB::transaction(function () use ($contact, $validated): Contact {
            $contact->fill([
                'organization_id' => $validated['organizationId'] ?? null,
                'first_name' => $this->nullableString($validated['firstName'] ?? null),
                'last_name' => $this->nullableString($validated['lastName'] ?? null),
                'full_name' => $this->resolvedFullName($validated),
                'job_title' => $this->nullableString($validated['title'] ?? null),
                'phone' => $this->nullableString($validated['phone'] ?? null),
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
            'phone' => $contact->phone,
            'notes' => $contact->notes,
            'status' => $contact->status,
            'organizationId' => $contact->organization_id,
            'organizationName' => $contact->organization?->name,
            'emails' => $contact->contactEmails
                ->sortByDesc('is_primary')
                ->values()
                ->map(fn (ContactEmail $email): array => [
                    'id' => $email->id,
                    'email' => $email->email,
                    'isPrimary' => $email->is_primary,
                    'optedOutAt' => $email->opt_out_at?->toIso8601String(),
                    'bounceStatus' => $email->bounce_status,
                    'lastSeenAt' => $email->last_seen_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    'canDelete' => ! $email->is_primary || $contact->contactEmails->count() > 1,
                ])->all(),
            'recentThreads' => $contact->threads
                ->sortByDesc('last_message_at')
                ->take(10)
                ->values()
                ->map(fn (MailThread $thread): array => [
                    'id' => $thread->id,
                    'subject' => $thread->messages->sortByDesc('created_at')->first()?->subject ?: $thread->subject_canonical,
                    'lastActivityAt' => $thread->last_message_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
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
                ])?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
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
            ])?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
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
                    'lastActivityAt' => $thread->last_message_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    'lastDirection' => $thread->last_direction,
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
            'title' => $contact->job_title,
            'organization' => $contact->organization?->name,
            'email' => $contact->contactEmails->first()?->email ?? '',
            'score' => 0,
            'scoreLevel' => 'cold',
            'excluded' => false,
            'unsubscribed' => false,
            'lastActivityAt' => null,
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
