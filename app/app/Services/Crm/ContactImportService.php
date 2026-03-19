<?php

namespace App\Services\Crm;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactImportBatch;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class ContactImportService
{
    private const CACHE_PREFIX = 'contact-import-preview:';

    private const CONSUMED_PREFIX = 'contact-import-preview-consumed:';

    private const MODULE_KEY = 'contacts_organizations';

    private const TEMPLATE_COLUMNS = ['societe', 'prenom', 'nom', 'email', 'linkedin', 'telephone_fixe', 'telephone_portable'];

    private const REQUIRED_FIELDS = ['primary_email'];

    private const FIELD_MAP = [
        'organization_name' => ['societe', 'société', 'entreprise', 'organisation', 'organization_name', 'organization', 'company', 'company_name'],
        'organization_domain' => ['organization_domain', 'domaine_societe', 'domaine_entreprise', 'company_domain'],
        'organization_website' => ['organization_website', 'site_web', 'site', 'website', 'url_societe'],
        'first_name' => ['prenom', 'prénom', 'first_name', 'firstname', 'contact_first_name'],
        'last_name' => ['nom', 'last_name', 'lastname', 'contact_last_name'],
        'full_name' => ['nom_complet', 'full_name', 'fullname', 'contact_full_name'],
        'primary_email' => ['email', 'e_mail', 'e-mail', 'mail', 'adresse_email', 'adresse_e_mail', 'primary_email', 'email_principal'],
        'linkedin_url' => ['linkedin', 'profil_linkedin', 'url_linkedin', 'linkedin_url', 'linkedin_profile'],
        'phone_landline' => ['telephone_fixe', 'téléphone_fixe', 'fixe', 'phone_landline', 'landline', 'phone', 'telephone', 'téléphone'],
        'phone_mobile' => ['telephone_portable', 'téléphone_portable', 'portable', 'mobile', 'phone_mobile', 'mobile_phone'],
        'notes' => ['notes', 'note', 'commentaires', 'comments'],
    ];

    private const FIELD_LABELS = [
        'organization_name' => 'Société',
        'organization_domain' => 'Domaine société',
        'organization_website' => 'Site web société',
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'full_name' => 'Nom complet',
        'primary_email' => 'Adresse e-mail',
        'linkedin_url' => 'LinkedIn',
        'phone_landline' => 'Téléphone fixe',
        'phone_mobile' => 'Téléphone portable',
        'notes' => 'Notes',
    ];

    public function preview(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => ['Le fichier d’import ne peut pas être lu.']]);
        }

        $sourceName = $file->getClientOriginalName() ?: 'contacts-import.csv';
        $sourceType = Str::lower($file->getClientOriginalExtension() ?: 'csv');
        $rows = $this->readRows($file);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => ['Le fichier d’import est vide.']]);
        }

        $header = array_shift($rows);
        $mapping = $this->detectMapping($header);

        if ($mapping['missingRequired'] !== []) {
            throw ValidationException::withMessages(['file' => ['Le fichier doit contenir au minimum une colonne e-mail reconnue.']]);
        }

        $organizations = Organization::query()->orderBy('id')->get(['id', 'name', 'domain', 'website']);
        $existingEmails = ContactEmail::query()
            ->with(['contact.organization', 'contact.contactEmails'])
            ->get()
            ->mapWithKeys(fn (ContactEmail $email) => [$this->normalizeEmail($email->email) => $email]);
        $previewRows = [];
        $sampleRows = [];
        $seenEmails = [];

        foreach ($rows as $offset => $row) {
            $mapped = $this->mapRow($mapping['indices'], $row);

            if ($this->rowIsEmpty($mapped)) {
                continue;
            }

            if (count($sampleRows) < 5) {
                $sampleRows[] = $this->rawSample($header, $row);
            }

            $previewRows[] = $this->previewRow($mapped, $offset + 2, $organizations, $existingEmails, $seenEmails);
        }

        if ($previewRows === []) {
            throw ValidationException::withMessages(['file' => ['Le fichier ne contient aucune ligne exploitable.']]);
        }

        $summary = $this->previewSummary($previewRows);
        $token = (string) Str::uuid();
        $preview = [
            'moduleKey' => self::MODULE_KEY,
            'previewToken' => $token,
            'sourceName' => $sourceName,
            'sourceType' => $sourceType,
            'templateColumns' => $this->templateColumns(),
            'detectedColumns' => $mapping['detectedColumns'],
            'mapping' => $mapping['contractMapping'],
            'sampleRows' => $sampleRows,
            'persistedFields' => $this->persistedFields(),
            'summary' => $summary,
            'counters' => $summary['counters'],
            'errors' => $this->issues($previewRows, 'errors'),
            'warnings' => $this->mergeIssueGroups($mapping['warnings'], $this->issues($previewRows, 'warnings')),
            'conflicts' => $this->issues($previewRows, 'conflicts'),
            'organizationSummary' => $this->organizationSummary($previewRows),
            'contactSummary' => $this->contactSummary($previewRows),
            'rows' => $previewRows,
            'createdAt' => now()->toIso8601String(),
            'expiresAt' => now()->addHour()->toIso8601String(),
        ];

        Cache::put($this->previewKey($token), $preview, now()->addHour());

        return $preview;
    }

    public function importFromPreviewToken(string $previewToken, ?int $userId = null): array
    {
        $payload = Cache::get($this->previewKey($previewToken));

        if (! is_array($payload) || ! isset($payload['rows'])) {
            throw ValidationException::withMessages(['previewToken' => ['La prévalidation a expiré. Relancez un aperçu avant de confirmer l’import.']]);
        }

        if (! Cache::add($this->consumedKey($previewToken), now()->toIso8601String(), now()->addDay())) {
            throw ValidationException::withMessages(['previewToken' => ['Cette prévalidation a déjà été confirmée. Rechargez un aperçu avant de relancer un import.']]);
        }

        $results = [];
        $contactIds = [];

        foreach ($payload['rows'] as $row) {
            $result = $this->importRow($row);
            $results[] = array_merge($row, $result);

            if (($result['contact']['contactId'] ?? null) !== null) {
                $contactIds[] = $result['contact']['contactId'];
            }
        }

        $summary = $this->importSummary($results);
        $summary['moduleKey'] = self::MODULE_KEY;
        $batch = ContactImportBatch::query()->create([
            'user_id' => $userId,
            'source_name' => $payload['sourceName'] ?? 'contacts-import.csv',
            'source_type' => $payload['sourceType'] ?? 'csv',
            'status' => 'completed',
            'imported_contacts_count' => $summary['importedRows'],
            'skipped_rows_count' => $summary['skippedRows'] + $summary['unchangedRows'],
            'invalid_rows_count' => $summary['errorRows'],
            'contact_ids_json' => array_values(array_unique($contactIds)),
            'summary_json' => $summary,
            'report_json' => $results,
            'processed_at' => now(),
        ]);

        Cache::forget($this->previewKey($previewToken));

        return ['moduleKey' => self::MODULE_KEY, 'message' => 'Import contacts / organisations terminé.', 'batch' => $this->serializeBatch($batch), 'summary' => $summary, 'rows' => $results];
    }

    public function modulePayload(): array
    {
        return [
            'moduleKey' => self::MODULE_KEY,
            'label' => 'Import / Export contacts et organisations',
            'pagePath' => '/contacts/imports',
            'previewEndpoint' => '/api/import-export/preview',
            'confirmEndpoint' => '/api/import-export/confirm',
            'templateEndpoint' => '/api/import-export/template',
            'exportEndpoint' => '/api/import-export/export',
            'legacyEndpoints' => [
                'preview' => '/api/contacts/imports/preview',
                'confirm' => '/api/contacts/imports',
                'template' => '/api/contacts/imports/template',
                'export' => '/api/contacts/imports/export',
            ],
            'acceptedFileTypes' => ['csv', 'xlsx'],
            'defaultFileType' => 'csv',
            'templateColumns' => $this->templateColumns(),
            'acceptedAliases' => $this->acceptedAliases(),
            'recentImports' => $this->recentImports(),
        ];
    }

    public function recentImports(int $limit = 5): array
    {
        return ContactImportBatch::query()->orderByDesc('processed_at')->limit($limit)->get()->map(fn (ContactImportBatch $batch) => $this->serializeBatch($batch))->all();
    }

    public function recentImportAudienceOptions(int $limit = 5): array
    {
        return ContactImportBatch::query()->orderByDesc('processed_at')->limit($limit)->get()->map(function (ContactImportBatch $batch): array {
            $contactIds = collect($batch->contact_ids_json ?? [])->filter()->values();
            $contacts = Contact::query()
                ->with([
                    'organization:id,name,domain',
                    'contactEmails' => fn ($query) => $query->where('is_primary', true)->orderByDesc('is_primary')->orderBy('id'),
                ])
                ->whereIn('id', $contactIds->all())
                ->get()
                ->sortBy(fn (Contact $contact) => Str::lower($contact->full_name ?: trim(($contact->first_name ?? '').' '.($contact->last_name ?? ''))))
                ->values()
                ->map(fn (Contact $contact) => $this->serializeAudienceContact($contact))
                ->all();

            return ['id' => $batch->id, 'moduleKey' => self::MODULE_KEY, 'sourceName' => $batch->source_name, 'sourceType' => $batch->source_type, 'importedAt' => $batch->processed_at?->toIso8601String(), 'contactCount' => count($contacts), 'contacts' => $contacts];
        })->all();
    }

    public function templateDownload(): string
    {
        return $this->buildCsv(function ($handle): void {
            fputcsv($handle, self::TEMPLATE_COLUMNS);
            fputcsv($handle, ['Acme Industries', 'Alice', 'Martin', 'alice@acme.test', 'https://www.linkedin.com/in/alice-martin', '+33 1 02 03 04 05', '+33 6 12 34 56 78']);
        });
    }

    public function exportDownload(): string
    {
        return $this->buildCsv(function ($handle): void {
            fputcsv($handle, self::TEMPLATE_COLUMNS);

            Contact::query()
                ->with([
                    'organization:id,name',
                    'contactEmails' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id'),
                ])
                ->orderBy('id')
                ->get()
                ->each(function (Contact $contact) use ($handle): void {
                    $primaryEmail = $contact->contactEmails->first();

                    fputcsv($handle, [
                        $contact->organization?->name ?? '',
                        $contact->first_name ?? '',
                        $contact->last_name ?? '',
                        $primaryEmail?->email ?? '',
                        $contact->linkedin_url ?? '',
                        $contact->phone_landline ?: $contact->phone ?? '',
                        $contact->phone_mobile ?? '',
                    ]);
                });
        });
    }

    public function serializeBatch(ContactImportBatch $batch): array
    {
        return ['id' => $batch->id, 'moduleKey' => self::MODULE_KEY, 'sourceName' => $batch->source_name, 'sourceType' => $batch->source_type, 'status' => $batch->status, 'importedContactsCount' => $batch->imported_contacts_count, 'skippedRowsCount' => $batch->skipped_rows_count, 'invalidRowsCount' => $batch->invalid_rows_count, 'summary' => $batch->summary_json ?? [], 'processedAt' => $batch->processed_at?->toIso8601String()];
    }

    public function templateColumns(): array
    {
        return self::TEMPLATE_COLUMNS;
    }

    private function previewRow(array $row, int $lineNumber, Collection $organizations, Collection $existingEmails, array &$seenEmails): array
    {
        $errors = [];
        $warnings = [];
        $conflicts = [];
        $email = $this->normalizeEmail($row['primary_email'] ?? null);
        $existingEmail = $email ? $existingEmails->get($email) : null;
        $existingContact = $existingEmail?->contact;

        if ($email === null) {
            $errors[] = $this->issue('primary_email_required', 'Une adresse e-mail est obligatoire pour importer une ligne.');
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->issue('primary_email_invalid', 'La colonne e-mail doit contenir une adresse valide.');
        } elseif (isset($seenEmails[$email])) {
            $conflicts[] = $this->issue('primary_email_duplicate_in_file', 'Cette adresse e-mail apparaît plusieurs fois dans le fichier.');
        } else {
            $seenEmails[$email] = $lineNumber;
        }

        $org = $this->resolveOrganizationPreview($row, $organizations, $existingContact);
        $warnings = array_merge($warnings, $org['warnings']);
        $conflicts = array_merge($conflicts, $org['conflicts']);

        if ($org['status'] === 'invalid') {
            $errors[] = $this->issue($org['reasonCode'], $org['reason']);
        }

        $normalized = [
            'firstName' => $this->nullableString($row['first_name'] ?? null),
            'lastName' => $this->nullableString($row['last_name'] ?? null),
            'fullName' => $this->fullName($row),
            'primaryEmail' => $email,
            'linkedinUrl' => $this->nullableString($row['linkedin_url'] ?? null),
            'phoneLandline' => $this->phone($row['phone_landline'] ?? null),
            'phoneMobile' => $this->phone($row['phone_mobile'] ?? null),
            'notes' => $this->nullableString($row['notes'] ?? null),
        ];

        $contact = $this->previewContactPayload($normalized, $existingContact, $existingEmail, $org['payload']);
        $action = $contact['action'];
        $status = $action === 'unchanged' ? 'unchanged' : 'valid';

        if ($conflicts !== []) {
            $action = 'skip';
            $status = 'duplicate_in_file';
        }

        if ($errors !== []) {
            $action = 'error';
            $status = 'invalid';
        }

        [$reasonCode, $reason] = $this->primaryIssue($errors, $conflicts, $warnings);
        $name = $normalized['fullName'] ?: trim(($normalized['firstName'] ?? '').' '.($normalized['lastName'] ?? ''));

        return [
            'lineNumber' => $lineNumber,
            'status' => $status,
            'action' => $action,
            'reasonCode' => $reasonCode,
            'reason' => $reason,
            'primaryEmail' => $normalized['primaryEmail'],
            'name' => $name !== '' ? $name : null,
            'organizationName' => $org['payload']['name'],
            'linkedinUrl' => $normalized['linkedinUrl'],
            'phoneLandline' => $normalized['phoneLandline'],
            'phoneMobile' => $normalized['phoneMobile'],
            'plannedActions' => [
                'organization' => [
                    'code' => $org['payload']['action'],
                    'label' => $this->organizationActionLabel($org['payload']['action']),
                ],
                'contact' => [
                    'code' => $contact['action'],
                    'label' => $this->contactActionLabel($contact['action']),
                ],
            ],
            'organization' => $org['payload'],
            'normalized' => array_merge(['organizationName' => $org['payload']['name']], $normalized),
            'persistedFields' => $this->rowPersistedFields($contact, $org['payload'], $action),
            'errors' => $errors,
            'warnings' => $warnings,
            'conflicts' => $conflicts,
            'contact' => $contact,
            'existingContact' => $existingContact ? $this->existingContactPayload($existingContact, $existingEmail) : null,
            'raw' => $row,
        ];
    }

    private function importRow(array $row): array
    {
        if (($row['action'] ?? null) === 'error') {
            return ['resultStatus' => 'error', 'resultAction' => 'error', 'resultMessage' => $row['reason'] ?? 'Ligne invalide.'];
        }

        if (($row['action'] ?? null) === 'skip') {
            return ['resultStatus' => 'skipped', 'resultAction' => 'skip', 'resultMessage' => $row['reason'] ?? 'Ligne ignorée.'];
        }

        if (($row['action'] ?? null) === 'unchanged') {
            return ['resultStatus' => 'skipped', 'resultAction' => 'unchanged', 'resultMessage' => 'Aucune modification détectée.'];
        }

        $email = $this->normalizeEmail($row['contact']['primaryEmail'] ?? null);

        if ($email === null) {
            return ['resultStatus' => 'error', 'resultAction' => 'error', 'resultMessage' => 'Ligne invalide: e-mail manquant.'];
        }

        $existingEmail = ContactEmail::query()->with(['contact.organization', 'contact.contactEmails'])->where('email', $email)->first();

        if (($row['action'] ?? 'create') === 'create' && $existingEmail) {
            return ['resultStatus' => 'skipped', 'resultAction' => 'skip', 'resultMessage' => 'Un contact utilise désormais cette adresse e-mail. Relancez un aperçu avant de confirmer.'];
        }

        if (($row['action'] ?? null) === 'update' && ! $existingEmail) {
            return ['resultStatus' => 'skipped', 'resultAction' => 'skip', 'resultMessage' => 'Le contact attendu a changé depuis la prévalidation. Relancez un aperçu avant de confirmer.'];
        }

        $mode = ($row['action'] ?? 'create') === 'update' ? 'update' : 'create';
        $contact = $mode === 'update' ? $this->updateContactFromImport($row, $existingEmail) : $this->createContactFromImport($row);

        return ['resultStatus' => 'imported', 'resultAction' => $mode, 'resultMessage' => $mode === 'update' ? 'Contact mis à jour.' : 'Contact importé.', 'contact' => $this->serializeAudienceContact($contact)];
    }

    private function createContactFromImport(array $row): Contact
    {
        return DB::transaction(function () use ($row): Contact {
            $organization = $this->resolveOrganizationForImport($row);
            $landline = $row['contact']['phoneLandline'] ?? null;
            $mobile = $row['contact']['phoneMobile'] ?? null;

            $contact = Contact::query()->create([
                'organization_id' => $organization?->id,
                'first_name' => $row['contact']['firstName'],
                'last_name' => $row['contact']['lastName'],
                'full_name' => $row['contact']['fullName'],
                'phone' => $this->firstFilled([$landline, $mobile]),
                'phone_landline' => $landline,
                'phone_mobile' => $mobile,
                'linkedin_url' => $row['contact']['linkedinUrl'],
                'notes' => $row['contact']['notes'],
                'status' => null,
            ]);

            $contact->contactEmails()->create(['email' => $row['contact']['primaryEmail'], 'is_primary' => true]);

            return $contact->fresh([
                'organization:id,name,domain',
                'contactEmails' => fn ($query) => $query->where('is_primary', true)->orderByDesc('is_primary')->orderBy('id'),
            ]);
        });
    }

    private function updateContactFromImport(array $row, ContactEmail $existingEmail): Contact
    {
        return DB::transaction(function () use ($row, $existingEmail): Contact {
            $contact = $existingEmail->contact()->with(['organization', 'contactEmails'])->firstOrFail();
            $organization = $this->resolveOrganizationForImport($row, $contact);
            $landline = $row['contact']['phoneLandline'] ?? $contact->phone_landline ?? $contact->phone;
            $mobile = $row['contact']['phoneMobile'] ?? $contact->phone_mobile;
            $firstName = $row['contact']['firstName'] ?? $contact->first_name;
            $lastName = $row['contact']['lastName'] ?? $contact->last_name;
            $fullName = $row['contact']['fullName'] ?? $contact->full_name ?? $this->firstFilled([trim(($firstName ?? '').' '.($lastName ?? ''))]);

            $contact->forceFill([
                'organization_id' => $organization?->id ?? $contact->organization_id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'phone' => $this->firstFilled([$landline, $mobile, $contact->phone]),
                'phone_landline' => $landline,
                'phone_mobile' => $mobile,
                'linkedin_url' => $row['contact']['linkedinUrl'] ?? $contact->linkedin_url,
                'notes' => $row['contact']['notes'] ?? $contact->notes,
            ])->save();

            if (! $existingEmail->is_primary) {
                $contact->contactEmails()->update(['is_primary' => false]);
                $existingEmail->forceFill(['is_primary' => true])->save();
            }

            return $contact->fresh([
                'organization:id,name,domain',
                'contactEmails' => fn ($query) => $query->where('is_primary', true)->orderByDesc('is_primary')->orderBy('id'),
            ]);
        });
    }

    private function resolveOrganizationPreview(array $row, Collection $organizations, ?Contact $existingContact = null): array
    {
        $warnings = [];
        $conflicts = [];
        $name = $this->nullableString($row['organization_name'] ?? null);
        $domain = $this->domain($row['organization_domain'] ?? null, $row['organization_website'] ?? null);
        $website = $this->nullableString($row['organization_website'] ?? null);
        $existingOrganization = $existingContact?->organization;

        if ($existingContact && $name === null) {
            if ($existingOrganization) {
                return ['status' => 'resolved', 'reasonCode' => null, 'reason' => null, 'warnings' => $warnings, 'conflicts' => $conflicts, 'payload' => $this->organizationPayload('keep_existing', $existingOrganization, [], 'existing')];
            }

            $warnings[] = $this->issue('organization_missing_existing_contact', 'Aucune société fournie: le contact existant sera conservé sans organisation liée.');

            return ['status' => 'resolved', 'reasonCode' => null, 'reason' => null, 'warnings' => $warnings, 'conflicts' => $conflicts, 'payload' => $this->organizationPayload('missing', null, [], null)];
        }

        if ($name === null) {
            return ['status' => 'invalid', 'reasonCode' => 'organization_required', 'reason' => 'La société est obligatoire pour créer un nouveau contact.', 'warnings' => $warnings, 'conflicts' => $conflicts, 'payload' => $this->organizationPayload('missing', null, [], null)];
        }

        $match = null;

        if ($domain !== null) {
            $matches = $organizations->filter(fn (Organization $organization) => Str::lower((string) $organization->domain) === $domain)->values();

            if ($matches->count() > 1) {
                return ['status' => 'invalid', 'reasonCode' => 'organization_domain_ambiguous', 'reason' => 'Le domaine société correspond à plusieurs organisations existantes.', 'warnings' => $warnings, 'conflicts' => $conflicts, 'payload' => $this->organizationPayload('ambiguous', null, [], 'domain', $name, $domain, $website)];
            }

            $match = $matches->first();
        }

        if (! $match) {
            $normalizedName = $this->normalizeOrganizationName($name);
            $matches = $organizations->filter(fn (Organization $organization) => $this->normalizeOrganizationName($organization->name) === $normalizedName)->values();

            if ($matches->count() > 1) {
                return ['status' => 'invalid', 'reasonCode' => 'organization_name_ambiguous', 'reason' => 'Le nom de société correspond à plusieurs organisations existantes.', 'warnings' => $warnings, 'conflicts' => $conflicts, 'payload' => $this->organizationPayload('ambiguous', null, [], 'name', $name, $domain, $website)];
            }

            $match = $matches->first();
        }

        if ($match) {
            if ($existingOrganization && $existingOrganization->id !== $match->id) {
                $conflicts[] = $this->issue('organization_conflict_preserved_existing', 'La société importée ne correspond pas à celle déjà liée au contact; l’organisation existante sera conservée.');

                return ['status' => 'resolved', 'reasonCode' => null, 'reason' => null, 'warnings' => $warnings, 'conflicts' => $conflicts, 'payload' => $this->organizationPayload('preserve_existing', $existingOrganization, [], 'existing')];
            }

            $changes = [];

            if ($match->domain === null && $domain !== null) {
                $changes[] = $this->change('organizationDomain', 'Domaine société', null, $domain);
            } elseif ($match->domain !== null && $domain !== null && Str::lower((string) $match->domain) !== $domain) {
                $warnings[] = $this->issue('organization_domain_preserved_existing', 'Le domaine fourni diffère du domaine enregistré; la valeur existante sera conservée.');
            }

            if ($match->website === null && $website !== null) {
                $changes[] = $this->change('organizationWebsite', 'Site web société', null, $website);
            } elseif ($match->website !== null && $website !== null && $match->website !== $website) {
                $warnings[] = $this->issue('organization_website_preserved_existing', 'Le site web fourni diffère de la valeur enregistrée; la valeur existante sera conservée.');
            }

            $action = $changes === [] ? 'reuse' : 'update';
            $strategy = $domain !== null && Str::lower((string) $match->domain) === $domain ? 'domain' : 'name';

            return ['status' => 'resolved', 'reasonCode' => null, 'reason' => null, 'warnings' => $warnings, 'conflicts' => $conflicts, 'payload' => $this->organizationPayload($action, $match, $changes, $strategy)];
        }

        $changes = [$this->change('organizationName', 'Société', null, $name)];
        if ($domain !== null) {
            $changes[] = $this->change('organizationDomain', 'Domaine société', null, $domain);
        }
        if ($website !== null) {
            $changes[] = $this->change('organizationWebsite', 'Site web société', null, $website);
        }

        return ['status' => 'resolved', 'reasonCode' => null, 'reason' => null, 'warnings' => $warnings, 'conflicts' => $conflicts, 'payload' => $this->organizationPayload('create', null, $changes, null, $name, $domain, $website)];
    }

    private function resolveOrganizationForImport(array $previewRow, ?Contact $existingContact = null): ?Organization
    {
        $action = $previewRow['organization']['action'] ?? null;
        $raw = $previewRow['raw'] ?? [];

        if (in_array($action, ['keep_existing', 'preserve_existing', 'missing'], true)) {
            return $existingContact?->organization;
        }

        $name = $this->nullableString($raw['organization_name'] ?? null);
        $domain = $this->domain($raw['organization_domain'] ?? null, $raw['organization_website'] ?? null);

        if ($name === null) {
            return $existingContact?->organization;
        }

        $organization = $domain ? Organization::query()->where('domain', $domain)->first() : null;

        if (! $organization) {
            $organization = Organization::query()->get(['id', 'name', 'domain', 'website'])->first(fn (Organization $candidate) => $this->normalizeOrganizationName($candidate->name) === $this->normalizeOrganizationName($name));
        }

        if ($organization) {
            $website = $this->nullableString($raw['organization_website'] ?? null);
            $dirty = false;

            if ($organization->domain === null && $domain !== null) {
                $organization->domain = $domain;
                $dirty = true;
            }

            if ($organization->website === null && $website !== null) {
                $organization->website = $website;
                $dirty = true;
            }

            if ($dirty) {
                $organization->save();
            }

            return $organization->refresh();
        }

        return Organization::query()->create(['name' => $name, 'domain' => $domain, 'website' => $this->nullableString($raw['organization_website'] ?? null), 'notes' => null]);
    }

    private function serializeAudienceContact(Contact $contact): array
    {
        $primaryEmail = $contact->contactEmails->sortByDesc('is_primary')->first();

        return ['contactId' => $contact->id, 'contactEmailId' => $primaryEmail?->id, 'organizationId' => $contact->organization_id, 'organizationName' => $contact->organization?->name, 'email' => $primaryEmail?->email, 'name' => $contact->full_name ?: trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')), 'jobTitle' => $contact->job_title];
    }

    private function detectMapping(array $header): array
    {
        $indices = [];
        $mapping = [];
        $detected = [];
        $warnings = [];
        $lookup = $this->aliasLookup();

        foreach ($header as $index => $column) {
            $source = (string) $column;
            $normalized = $this->normalizeHeader($source);
            $field = $lookup[$normalized] ?? null;
            $retained = false;

            if ($field !== null && ! array_key_exists($field, $indices)) {
                $indices[$field] = $index;
                $mapping[$this->contractFieldName($field)] = $source;
                $retained = true;
            } elseif ($field !== null) {
                $warnings[] = ['code' => 'duplicate_mapped_column', 'message' => sprintf('Plusieurs colonnes correspondent à "%s". La première occurrence est conservée.', self::FIELD_LABELS[$field]), 'count' => 1, 'lineNumbers' => []];
            }

            $detected[] = ['index' => $index, 'sourceHeader' => $source, 'normalizedHeader' => $normalized, 'field' => $field ? $this->contractFieldName($field) : null, 'label' => $field ? self::FIELD_LABELS[$field] : null, 'retained' => $retained];
        }

        $ignored = collect($detected)->whereNull('field')->pluck('sourceHeader')->values()->all();

        if ($ignored !== []) {
            $warnings[] = ['code' => 'ignored_columns', 'message' => 'Certaines colonnes ne sont pas importées et seront ignorées.', 'count' => 1, 'lineNumbers' => []];
        }

        return ['indices' => $indices, 'contractMapping' => $mapping, 'detectedColumns' => $detected, 'warnings' => $warnings, 'missingRequired' => array_values(array_diff(self::REQUIRED_FIELDS, array_keys($indices)))];
    }

    private function aliasLookup(): array
    {
        $lookup = [];

        foreach (self::FIELD_MAP as $field => $aliases) {
            foreach ($aliases as $alias) {
                $lookup[$this->normalizeHeader($alias)] = $field;
            }
        }

        return $lookup;
    }

    private function acceptedAliases(): array
    {
        return collect(self::FIELD_MAP)->mapWithKeys(
            fn (array $aliases, string $field) => [$this->contractFieldName($field) => array_values(array_unique($aliases))]
        )->all();
    }

    private function mapRow(array $indices, array $row): array
    {
        return [
            'organization_name' => $this->cell($indices, 'organization_name', $row),
            'organization_domain' => $this->cell($indices, 'organization_domain', $row),
            'organization_website' => $this->cell($indices, 'organization_website', $row),
            'first_name' => $this->cell($indices, 'first_name', $row),
            'last_name' => $this->cell($indices, 'last_name', $row),
            'full_name' => $this->cell($indices, 'full_name', $row),
            'primary_email' => $this->cell($indices, 'primary_email', $row),
            'linkedin_url' => $this->cell($indices, 'linkedin_url', $row),
            'phone_landline' => $this->cell($indices, 'phone_landline', $row),
            'phone_mobile' => $this->cell($indices, 'phone_mobile', $row),
            'notes' => $this->cell($indices, 'notes', $row),
        ];
    }

    private function cell(array $indices, string $field, array $row): mixed
    {
        if (! array_key_exists($field, $indices)) {
            return null;
        }

        return $row[$indices[$field]] ?? null;
    }

    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->map(fn ($value) => trim((string) $value))->filter()->isEmpty();
    }

    private function previewSummary(array $rows): array
    {
        $create = collect($rows)->where('action', 'create')->count();
        $update = collect($rows)->where('action', 'update')->count();
        $unchanged = collect($rows)->where('action', 'unchanged')->count();
        $skip = collect($rows)->where('action', 'skip')->count();
        $error = collect($rows)->where('action', 'error')->count();

        return [
            'totalRows' => count($rows),
            'validRows' => $create + $update + $unchanged,
            'writeRows' => $create + $update,
            'createRows' => $create,
            'updateRows' => $update,
            'unchangedRows' => $unchanged,
            'skipRows' => $skip,
            'errorRows' => $error,
            'invalidRows' => $error,
            'duplicateExistingRows' => $update + $unchanged,
            'duplicateFileRows' => collect($rows)->where('status', 'duplicate_in_file')->count(),
            'organizationMatches' => collect($rows)->filter(fn (array $row) => in_array($row['organization']['action'] ?? null, ['reuse', 'update', 'keep_existing', 'preserve_existing'], true))->count(),
            'organizationCreates' => collect($rows)->where('organization.action', 'create')->count(),
            'organizationUpdates' => collect($rows)->where('organization.action', 'update')->count(),
            'contactCreates' => collect($rows)->where('contact.action', 'create')->count(),
            'contactUpdates' => collect($rows)->where('contact.action', 'update')->count(),
            'contactUnchanged' => collect($rows)->where('contact.action', 'unchanged')->count(),
            'counters' => ['create' => $create, 'update' => $update, 'unchanged' => $unchanged, 'skip' => $skip, 'error' => $error],
        ];
    }

    private function importSummary(array $rows): array
    {
        $create = collect($rows)->where('resultAction', 'create')->count();
        $update = collect($rows)->where('resultAction', 'update')->count();
        $unchanged = collect($rows)->where('resultAction', 'unchanged')->count();
        $skip = collect($rows)->where('resultAction', 'skip')->count();
        $error = collect($rows)->where('resultStatus', 'error')->count();

        return [
            'totalRows' => count($rows),
            'importedRows' => $create + $update,
            'createdRows' => $create,
            'updatedRows' => $update,
            'unchangedRows' => $unchanged,
            'skippedRows' => $skip,
            'errorRows' => $error,
            'invalidRows' => $error,
            'duplicateExistingRows' => $update,
            'counters' => ['create' => $create, 'update' => $update, 'unchanged' => $unchanged, 'skip' => $skip, 'error' => $error],
        ];
    }

    private function organizationSummary(array $rows): array
    {
        $actions = collect($rows)->pluck('organization.action');

        return [
            'matchedExistingCount' => $actions->filter(fn ($action) => in_array($action, ['reuse', 'update'], true))->count(),
            'createdCount' => $actions->filter(fn ($action) => $action === 'create')->count(),
            'keptExistingCount' => $actions->filter(fn ($action) => in_array($action, ['keep_existing', 'preserve_existing'], true))->count(),
            'updatedCount' => $actions->filter(fn ($action) => $action === 'update')->count(),
            'missingCount' => $actions->filter(fn ($action) => $action === 'missing')->count(),
        ];
    }

    private function contactSummary(array $rows): array
    {
        $actions = collect($rows)->pluck('contact.action');

        return [
            'createdCount' => $actions->filter(fn ($action) => $action === 'create')->count(),
            'updatedCount' => $actions->filter(fn ($action) => $action === 'update')->count(),
            'unchangedCount' => $actions->filter(fn ($action) => $action === 'unchanged')->count(),
        ];
    }

    private function issues(array $rows, string $bucket): array
    {
        return collect($rows)->flatMap(function (array $row) use ($bucket) {
            return collect($row[$bucket] ?? [])->map(fn (array $issue) => ['code' => $issue['code'], 'message' => $issue['message'], 'lineNumber' => $row['lineNumber']])->all();
        })->groupBy('code')->map(function (Collection $issues, string $code) {
            $first = $issues->first();

            return ['code' => $code, 'message' => $first['message'], 'count' => $issues->count(), 'lineNumbers' => $issues->pluck('lineNumber')->unique()->values()->all()];
        })->values()->all();
    }

    private function mergeIssueGroups(array ...$groups): array
    {
        return collect($groups)->flatten(1)->groupBy('code')->map(function (Collection $items, string $code) {
            $first = $items->first();

            return ['code' => $code, 'message' => $first['message'], 'count' => $items->sum(fn ($item) => (int) ($item['count'] ?? 1)), 'lineNumbers' => $items->flatMap(fn ($item) => $item['lineNumbers'] ?? [])->unique()->values()->all()];
        })->values()->all();
    }

    private function persistedFields(): array
    {
        return [
            ['field' => 'organizationName', 'label' => 'Société', 'persistsTo' => 'organizations.name + contacts.organization_id'],
            ['field' => 'firstName', 'label' => 'Prénom', 'persistsTo' => 'contacts.first_name'],
            ['field' => 'lastName', 'label' => 'Nom', 'persistsTo' => 'contacts.last_name'],
            ['field' => 'primaryEmail', 'label' => 'E-mail', 'persistsTo' => 'contact_emails.email (principal)'],
            ['field' => 'linkedinUrl', 'label' => 'LinkedIn', 'persistsTo' => 'contacts.linkedin_url'],
            ['field' => 'phoneLandline', 'label' => 'Téléphone fixe', 'persistsTo' => 'contacts.phone_landline'],
            ['field' => 'phoneMobile', 'label' => 'Téléphone portable', 'persistsTo' => 'contacts.phone_mobile'],
        ];
    }

    private function rowPersistedFields(array $contact, array $organization, string $action): array
    {
        $fields = collect($contact['changes'] ?? [])->pluck('field')->filter()->values()->all();

        if (($organization['name'] ?? null) !== null && in_array($organization['action'] ?? null, ['create', 'update'], true) && $action !== 'skip') {
            $fields[] = 'organizationName';
        }

        return array_values(array_unique($fields));
    }

    private function existingContactPayload(Contact $contact, ContactEmail $email): array
    {
        $primaryEmail = $contact->contactEmails->firstWhere('is_primary', true);

        return ['contactId' => $contact->id, 'organizationId' => $contact->organization_id, 'organizationName' => $contact->organization?->name, 'primaryEmail' => $primaryEmail?->email, 'matchedEmail' => $email->email, 'firstName' => $contact->first_name, 'lastName' => $contact->last_name, 'fullName' => $contact->full_name ?: trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')), 'linkedinUrl' => $contact->linkedin_url, 'phoneLandline' => $contact->phone_landline ?: $contact->phone, 'phoneMobile' => $contact->phone_mobile];
    }

    private function previewContactPayload(array $normalized, ?Contact $existingContact, ?ContactEmail $existingEmail, array $organization): array
    {
        if (! $existingContact) {
            $changes = array_values(array_filter([
                ($organization['name'] ?? null) !== null ? $this->change('organizationName', 'Société', null, $organization['name']) : null,
                ($normalized['firstName'] ?? null) !== null ? $this->change('firstName', 'Prénom', null, $normalized['firstName']) : null,
                ($normalized['lastName'] ?? null) !== null ? $this->change('lastName', 'Nom', null, $normalized['lastName']) : null,
                ($normalized['primaryEmail'] ?? null) !== null ? $this->change('primaryEmail', 'Adresse e-mail', null, $normalized['primaryEmail']) : null,
                ($normalized['linkedinUrl'] ?? null) !== null ? $this->change('linkedinUrl', 'LinkedIn', null, $normalized['linkedinUrl']) : null,
                ($normalized['phoneLandline'] ?? null) !== null ? $this->change('phoneLandline', 'Téléphone fixe', null, $normalized['phoneLandline']) : null,
                ($normalized['phoneMobile'] ?? null) !== null ? $this->change('phoneMobile', 'Téléphone portable', null, $normalized['phoneMobile']) : null,
            ]));

            return array_merge($normalized, [
                'action' => 'create',
                'willWrite' => true,
                'changes' => $changes,
                'matchStrategy' => 'exact_email_missing',
            ]);
        }

        $changes = [];
        $primaryEmail = $existingContact->contactEmails->firstWhere('is_primary', true);

        if ($existingEmail && ! $existingEmail->is_primary) {
            $changes[] = $this->change('primaryEmail', 'Adresse e-mail', $primaryEmail?->email, $existingEmail->email);
        }

        if (($organization['organizationId'] ?? $existingContact->organization_id) !== $existingContact->organization_id && ! in_array($organization['action'] ?? null, ['keep_existing', 'preserve_existing', 'missing'], true)) {
            $changes[] = $this->change('organizationName', 'Société', $existingContact->organization?->name, $organization['name'] ?? null);
        }

        foreach ([
            ['field' => 'firstName', 'label' => 'Prénom', 'current' => $existingContact->first_name, 'incoming' => $normalized['firstName'] ?? null],
            ['field' => 'lastName', 'label' => 'Nom', 'current' => $existingContact->last_name, 'incoming' => $normalized['lastName'] ?? null],
            ['field' => 'linkedinUrl', 'label' => 'LinkedIn', 'current' => $existingContact->linkedin_url, 'incoming' => $normalized['linkedinUrl'] ?? null],
            ['field' => 'phoneLandline', 'label' => 'Téléphone fixe', 'current' => $existingContact->phone_landline ?: $existingContact->phone, 'incoming' => $normalized['phoneLandline'] ?? null],
            ['field' => 'phoneMobile', 'label' => 'Téléphone portable', 'current' => $existingContact->phone_mobile, 'incoming' => $normalized['phoneMobile'] ?? null],
        ] as $field) {
            if (($field['incoming'] ?? null) !== null && $field['current'] !== $field['incoming']) {
                $changes[] = $this->change($field['field'], $field['label'], $field['current'], $field['incoming']);
            }
        }

        if ($changes === [] && ! ($organization['willWrite'] ?? false)) {
            return array_merge($normalized, [
                'action' => 'unchanged',
                'willWrite' => false,
                'changes' => [],
                'matchStrategy' => 'exact_email',
            ]);
        }

        return array_merge($normalized, [
            'action' => 'update',
            'willWrite' => true,
            'changes' => $changes,
            'matchStrategy' => 'exact_email',
        ]);
    }

    private function organizationPayload(
        string $action,
        ?Organization $organization,
        array $changes,
        ?string $matchStrategy,
        ?string $name = null,
        ?string $domain = null,
        ?string $website = null,
    ): array {
        return [
            'action' => $action,
            'writeAction' => in_array($action, ['create', 'update'], true) ? $action : 'unchanged',
            'willWrite' => in_array($action, ['create', 'update'], true),
            'organizationId' => $organization?->id,
            'organizationName' => $organization?->name ?? $name,
            'name' => $organization?->name ?? $name,
            'domain' => $organization?->domain ?? $domain,
            'website' => $organization?->website ?? $website,
            'matchStrategy' => $matchStrategy,
            'changes' => $changes,
        ];
    }

    private function organizationSnapshot(?string $name, ?string $domain, ?string $website): array
    {
        return [
            'name' => $name,
            'domain' => $domain,
            'website' => $website,
        ];
    }

    private function change(string $field, string $label, mixed $current, mixed $incoming): array
    {
        return [
            'field' => $field,
            'label' => $label,
            'current' => $current,
            'incoming' => $incoming,
        ];
    }

    private function organizationActionLabel(?string $action): string
    {
        return match ($action) {
            'create' => 'Créer l’organisation',
            'update' => 'Mettre à jour l’organisation',
            'reuse' => 'Réutiliser l’organisation',
            'keep_existing' => 'Conserver l’organisation liée',
            'preserve_existing' => 'Conserver l’organisation existante',
            'missing' => 'Aucune organisation',
            default => 'Organisation à vérifier',
        };
    }

    private function contactActionLabel(?string $action): string
    {
        return match ($action) {
            'create' => 'Créer le contact',
            'update' => 'Mettre à jour le contact',
            'unchanged' => 'Aucun changement',
            default => 'Contact à vérifier',
        };
    }

    private function primaryIssue(array $errors, array $conflicts, array $warnings): array
    {
        $issue = $errors[0] ?? $conflicts[0] ?? $warnings[0] ?? null;

        return [$issue['code'] ?? null, $issue['message'] ?? null];
    }

    private function issue(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }

    private function rawSample(array $header, array $row): array
    {
        $sample = [];
        foreach ($header as $index => $column) {
            $sample[(string) $column] = trim((string) ($row[$index] ?? ''));
        }

        return $sample;
    }

    private function previewKey(string $token): string
    {
        return self::CACHE_PREFIX.$token;
    }

    private function consumedKey(string $token): string
    {
        return self::CONSUMED_PREFIX.$token;
    }

    private function contractFieldName(string $field): string
    {
        return match ($field) {
            'organization_name' => 'organizationName',
            'organization_domain' => 'organizationDomain',
            'organization_website' => 'organizationWebsite',
            'first_name' => 'firstName',
            'last_name' => 'lastName',
            'full_name' => 'fullName',
            'primary_email' => 'primaryEmail',
            'linkedin_url' => 'linkedinUrl',
            'phone_landline' => 'phoneLandline',
            'phone_mobile' => 'phoneMobile',
            default => Str::camel($field),
        };
    }

    private function normalizeHeader(string $value): string
    {
        $ascii = Str::ascii(Str::lower(trim($value)));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $ascii), '_');
    }

    private function normalizeEmail(?string $value): ?string
    {
        $email = Str::lower(trim((string) $value));

        return $email !== '' ? $email : null;
    }

    private function normalizeOrganizationName(?string $value): string
    {
        return preg_replace('/\s+/', ' ', Str::lower(trim((string) $value))) ?: '';
    }

    private function domain(?string $domain, ?string $website = null): ?string
    {
        $normalized = Str::lower(trim((string) $domain));

        if ($normalized !== '') {
            return preg_replace('/^www\./', '', $normalized) ?: null;
        }

        $host = parse_url(trim((string) $website), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? (preg_replace('/^www\./', '', Str::lower($host)) ?: null) : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function phone(mixed $value): ?string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    private function fullName(array $row): ?string
    {
        $fullName = $this->nullableString($row['full_name'] ?? null);
        if ($fullName !== null) {
            return $fullName;
        }
        $fullName = trim((string) ($row['first_name'] ?? '').' '.(string) ($row['last_name'] ?? ''));

        return $fullName !== '' ? $fullName : null;
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $normalized = $this->nullableString($value);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function buildCsv(callable $writer): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        $writer($handle);
        rewind($handle);

        return (string) stream_get_contents($handle);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readRows(UploadedFile $file): array
    {
        return Str::lower($file->getClientOriginalExtension() ?: '') === 'xlsx' ? $this->readXlsxRows($file) : $this->readCsvRows($file);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsvRows(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => ['Le fichier CSV ne peut pas être ouvert.']]);
        }

        while (($row = fgetcsv($handle, separator: ',', escape: '\\')) !== false) {
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) ?? (string) $row[0];
            }
            $rows[] = array_map(fn ($value) => trim((string) $value), $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readXlsxRows(UploadedFile $file): array
    {
        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['file' => ['Le fichier XLSX ne peut pas être ouvert.']]);
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $relationships = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));

        if (! $workbook instanceof SimpleXMLElement || ! $relationships instanceof SimpleXMLElement) {
            $zip->close();
            throw ValidationException::withMessages(['file' => ['Le fichier XLSX est invalide.']]);
        }

        $sheet = $workbook->sheets?->sheet[0] ?? null;
        $relationshipId = (string) ($sheet?->attributes('r', true)?->id ?? '');
        $target = null;

        foreach ($relationships->Relationship as $relationship) {
            if ((string) $relationship['Id'] === $relationshipId) {
                $target = (string) $relationship['Target'];
                break;
            }
        }

        if (! is_string($target) || $target === '') {
            $zip->close();
            throw ValidationException::withMessages(['file' => ['Impossible de localiser la première feuille XLSX.']]);
        }

        $sheetXml = simplexml_load_string((string) $zip->getFromName('xl/'.ltrim($target, '/')));
        $zip->close();

        if (! $sheetXml instanceof SimpleXMLElement) {
            throw ValidationException::withMessages(['file' => ['La feuille XLSX ne peut pas être lue.']]);
        }

        $rows = [];

        foreach ($sheetXml->sheetData?->row ?? [] as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnIndexFromReference($reference);
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }

                $values[$columnIndex] = trim($value);
            }

            if ($values === []) {
                continue;
            }

            $lastColumn = max(array_keys($values));
            $normalized = [];

            for ($index = 0; $index <= $lastColumn; $index++) {
                $normalized[] = $values[$index] ?? '';
            }

            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

        if (! is_string($sharedStringsXml) || $sharedStringsXml === '') {
            return [];
        }

        $sharedStrings = simplexml_load_string($sharedStringsXml);

        if (! $sharedStrings instanceof SimpleXMLElement) {
            return [];
        }

        $values = [];

        foreach ($sharedStrings->si as $stringItem) {
            if (isset($stringItem->t)) {
                $values[] = (string) $stringItem->t;

                continue;
            }

            $values[] = collect($stringItem->r ?? [])->map(fn ($run) => (string) ($run->t ?? ''))->implode('');
        }

        return $values;
    }

    private function columnIndexFromReference(string $reference): int
    {
        preg_match('/^[A-Z]+/', strtoupper($reference), $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }
}
