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

    /**
     * @var string[]
     */
    private const TEMPLATE_COLUMNS = [
        'organization_name',
        'organization_domain',
        'organization_website',
        'contact_first_name',
        'contact_last_name',
        'contact_full_name',
        'job_title',
        'primary_email',
        'secondary_email',
        'phone',
        'linkedin_url',
        'country',
        'city',
        'tags',
        'notes',
    ];

    public function preview(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['Le fichier d’import ne peut pas être lu.'],
            ]);
        }

        $sourceName = $file->getClientOriginalName() ?: 'contacts-import.csv';
        $sourceType = Str::lower($file->getClientOriginalExtension() ?: 'csv');
        $rows = $this->readRows($file);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => ['Le fichier d’import est vide.'],
            ]);
        }

        $header = array_shift($rows);
        $normalizedHeader = array_map([$this, 'normalizeHeader'], $header);
        $missingHeaders = array_diff(['organization_name', 'primary_email'], $normalizedHeader);

        if ($missingHeaders !== []) {
            throw ValidationException::withMessages([
                'file' => ['Le fichier doit contenir au minimum les colonnes organization_name et primary_email.'],
            ]);
        }

        $organizations = Organization::query()
            ->orderBy('id')
            ->get(['id', 'name', 'domain', 'website']);
        $existingEmails = ContactEmail::query()
            ->pluck('email')
            ->map(fn (string $email): string => Str::lower(trim($email)))
            ->flip();

        $previewRows = [];
        $seenPrimaryEmails = [];
        $seenSecondaryEmails = [];

        foreach ($rows as $offset => $row) {
            $mapped = $this->mapRowToTemplate($normalizedHeader, $row);

            if ($this->rowIsEmpty($mapped)) {
                continue;
            }

            $previewRows[] = $this->analyzeRow(
                $mapped,
                $offset + 2,
                $organizations,
                $existingEmails->all(),
                $seenPrimaryEmails,
                $seenSecondaryEmails,
            );
        }

        if ($previewRows === []) {
            throw ValidationException::withMessages([
                'file' => ['Le fichier ne contient aucune ligne exploitable.'],
            ]);
        }

        $summary = $this->buildPreviewSummary($previewRows);
        $previewToken = (string) Str::uuid();

        Cache::put(
            $this->previewCacheKey($previewToken),
            [
                'source_name' => $sourceName,
                'source_type' => $sourceType,
                'rows' => $previewRows,
                'summary' => $summary,
                'created_at' => now()->toIso8601String(),
            ],
            now()->addHour(),
        );

        return [
            'previewToken' => $previewToken,
            'sourceName' => $sourceName,
            'sourceType' => $sourceType,
            'summary' => $summary,
            'rows' => $previewRows,
        ];
    }

    public function importFromPreviewToken(string $previewToken, ?int $userId = null): array
    {
        $payload = Cache::get($this->previewCacheKey($previewToken));

        if (! is_array($payload) || ! isset($payload['rows'])) {
            throw ValidationException::withMessages([
                'previewToken' => ['La prévalidation a expiré. Relancez un aperçu avant de confirmer l’import.'],
            ]);
        }

        $results = [];
        $importedContactIds = [];

        foreach ($payload['rows'] as $row) {
            if (($row['status'] ?? null) !== 'valid') {
                $results[] = array_merge($row, [
                    'resultStatus' => 'skipped',
                    'resultMessage' => $row['reason'] ?? 'Ligne ignorée.',
                ]);

                continue;
            }

            $result = $this->persistPreviewRow($row);
            $results[] = array_merge($row, $result);

            if (($result['resultStatus'] ?? null) === 'imported' && isset($result['contact']['contactId'])) {
                $importedContactIds[] = $result['contact']['contactId'];
            }
        }

        $batch = ContactImportBatch::query()->create([
            'user_id' => $userId,
            'source_name' => $payload['source_name'] ?? 'contacts-import.csv',
            'source_type' => $payload['source_type'] ?? 'csv',
            'status' => 'completed',
            'imported_contacts_count' => collect($results)->where('resultStatus', 'imported')->count(),
            'skipped_rows_count' => collect($results)->whereIn('resultStatus', ['skipped', 'duplicate_existing'])->count(),
            'invalid_rows_count' => collect($results)->where('status', 'invalid')->count(),
            'contact_ids_json' => array_values(array_unique($importedContactIds)),
            'summary_json' => $this->buildImportSummary($results),
            'report_json' => $results,
            'processed_at' => now(),
        ]);

        Cache::forget($this->previewCacheKey($previewToken));

        return [
            'message' => 'Import des contacts terminé.',
            'batch' => $this->serializeBatch($batch),
            'summary' => $batch->summary_json,
            'rows' => $results,
        ];
    }

    public function recentImports(int $limit = 5): array
    {
        return ContactImportBatch::query()
            ->orderByDesc('processed_at')
            ->limit($limit)
            ->get()
            ->map(fn (ContactImportBatch $batch): array => $this->serializeBatch($batch))
            ->all();
    }

    public function recentImportAudienceOptions(int $limit = 5): array
    {
        return ContactImportBatch::query()
            ->orderByDesc('processed_at')
            ->limit($limit)
            ->get()
            ->map(function (ContactImportBatch $batch): array {
                $contactIds = collect($batch->contact_ids_json ?? [])->filter()->values();
                $contacts = Contact::query()
                    ->with([
                        'organization:id,name,domain',
                        'contactEmails' => fn ($query) => $query
                            ->where('is_primary', true)
                            ->orderByDesc('is_primary')
                            ->orderBy('id'),
                    ])
                    ->whereIn('id', $contactIds->all())
                    ->get()
                    ->sortBy(fn (Contact $contact) => Str::lower($contact->full_name ?: trim(($contact->first_name ?? '').' '.($contact->last_name ?? ''))))
                    ->values()
                    ->map(fn (Contact $contact): array => $this->serializeAudienceContact($contact))
                    ->all();

                return [
                    'id' => $batch->id,
                    'sourceName' => $batch->source_name,
                    'sourceType' => $batch->source_type,
                    'importedAt' => $batch->processed_at?->toIso8601String(),
                    'contactCount' => count($contacts),
                    'contacts' => $contacts,
                ];
            })
            ->all();
    }

    public function templateDownload(): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::TEMPLATE_COLUMNS);
        fputcsv($handle, [
            'Acme Industries',
            'acme.test',
            'https://acme.test',
            'Alice',
            'Martin',
            'Alice Martin',
            'Head of Sales',
            'alice@acme.test',
            'alice.secondary@acme.test',
            '+33102030405',
            'https://www.linkedin.com/in/alice-martin',
            'France',
            'Paris',
            'prospect;priority',
            'Premier import',
        ]);
        rewind($handle);

        return (string) stream_get_contents($handle);
    }

    public function serializeBatch(ContactImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'sourceName' => $batch->source_name,
            'sourceType' => $batch->source_type,
            'status' => $batch->status,
            'importedContactsCount' => $batch->imported_contacts_count,
            'skippedRowsCount' => $batch->skipped_rows_count,
            'invalidRowsCount' => $batch->invalid_rows_count,
            'summary' => $batch->summary_json ?? [],
            'processedAt' => $batch->processed_at?->toIso8601String(),
        ];
    }

    public function templateColumns(): array
    {
        return self::TEMPLATE_COLUMNS;
    }

    private function buildPreviewSummary(array $rows): array
    {
        return [
            'totalRows' => count($rows),
            'validRows' => collect($rows)->where('status', 'valid')->count(),
            'invalidRows' => collect($rows)->where('status', 'invalid')->count(),
            'duplicateExistingRows' => collect($rows)->where('status', 'duplicate_existing')->count(),
            'duplicateFileRows' => collect($rows)->where('status', 'duplicate_in_file')->count(),
            'organizationMatches' => collect($rows)->filter(fn (array $row) => in_array($row['organization']['action'] ?? null, ['match_domain', 'match_name'], true))->count(),
            'organizationCreates' => collect($rows)->where('organization.action', 'create')->count(),
        ];
    }

    private function buildImportSummary(array $rows): array
    {
        return [
            'totalRows' => count($rows),
            'importedRows' => collect($rows)->where('resultStatus', 'imported')->count(),
            'skippedRows' => collect($rows)->where('resultStatus', 'skipped')->count(),
            'duplicateExistingRows' => collect($rows)->where('resultStatus', 'duplicate_existing')->count(),
            'invalidRows' => collect($rows)->where('status', 'invalid')->count(),
        ];
    }

    private function analyzeRow(
        array $row,
        int $lineNumber,
        Collection $organizations,
        array $existingEmails,
        array &$seenPrimaryEmails,
        array &$seenSecondaryEmails,
    ): array {
        $primaryEmail = Str::lower(trim((string) ($row['primary_email'] ?? '')));
        $secondaryEmail = Str::lower(trim((string) ($row['secondary_email'] ?? '')));

        $reasonCode = null;
        $reason = null;
        $status = 'valid';

        if (trim((string) ($row['organization_name'] ?? '')) === '') {
            $status = 'invalid';
            $reasonCode = 'organization_required';
            $reason = 'organization_name est obligatoire.';
        } elseif ($primaryEmail === '') {
            $status = 'invalid';
            $reasonCode = 'primary_email_required';
            $reason = 'primary_email est obligatoire.';
        } elseif (! filter_var($primaryEmail, FILTER_VALIDATE_EMAIL)) {
            $status = 'invalid';
            $reasonCode = 'primary_email_invalid';
            $reason = 'primary_email doit être une adresse valide.';
        } elseif ($secondaryEmail !== '' && ! filter_var($secondaryEmail, FILTER_VALIDATE_EMAIL)) {
            $status = 'invalid';
            $reasonCode = 'secondary_email_invalid';
            $reason = 'secondary_email doit être une adresse valide.';
        } elseif ($secondaryEmail !== '' && $secondaryEmail === $primaryEmail) {
            $status = 'invalid';
            $reasonCode = 'secondary_email_same_as_primary';
            $reason = 'secondary_email doit être différente de primary_email.';
        } elseif (isset($existingEmails[$primaryEmail])) {
            $status = 'duplicate_existing';
            $reasonCode = 'primary_email_duplicate_existing';
            $reason = 'Un contact existe déjà avec ce primary_email.';
        } elseif (isset($seenPrimaryEmails[$primaryEmail])) {
            $status = 'duplicate_in_file';
            $reasonCode = 'primary_email_duplicate_in_file';
            $reason = 'Ce primary_email apparaît plusieurs fois dans le fichier.';
        } elseif ($secondaryEmail !== '' && isset($existingEmails[$secondaryEmail])) {
            $status = 'invalid';
            $reasonCode = 'secondary_email_duplicate_existing';
            $reason = 'secondary_email est déjà utilisée par un autre contact.';
        } elseif ($secondaryEmail !== '' && isset($seenSecondaryEmails[$secondaryEmail])) {
            $status = 'invalid';
            $reasonCode = 'secondary_email_duplicate_in_file';
            $reason = 'secondary_email apparaît plusieurs fois dans le fichier.';
        }

        $organization = $this->resolveOrganizationPreview($row, $organizations);

        if (($organization['status'] ?? null) === 'invalid' && $status === 'valid') {
            $status = 'invalid';
            $reasonCode = $organization['reasonCode'];
            $reason = $organization['reason'];
        }

        if ($primaryEmail !== '' && $status === 'valid') {
            $seenPrimaryEmails[$primaryEmail] = true;
        }

        if ($secondaryEmail !== '' && $status === 'valid') {
            $seenSecondaryEmails[$secondaryEmail] = true;
        }

        return [
            'lineNumber' => $lineNumber,
            'status' => $status,
            'reasonCode' => $reasonCode,
            'reason' => $reason,
            'organization' => $organization,
            'contact' => [
                'firstName' => $this->nullableString($row['contact_first_name'] ?? null),
                'lastName' => $this->nullableString($row['contact_last_name'] ?? null),
                'fullName' => $this->resolvedFullName($row),
                'jobTitle' => $this->nullableString($row['job_title'] ?? null),
                'primaryEmail' => $primaryEmail !== '' ? $primaryEmail : null,
                'secondaryEmail' => $secondaryEmail !== '' ? $secondaryEmail : null,
                'phone' => $this->nullableString($row['phone'] ?? null),
                'linkedinUrl' => $this->nullableString($row['linkedin_url'] ?? null),
                'country' => $this->nullableString($row['country'] ?? null),
                'city' => $this->nullableString($row['city'] ?? null),
                'tags' => $this->parseTags($row['tags'] ?? null),
                'notes' => $this->nullableString($row['notes'] ?? null),
            ],
            'raw' => $row,
        ];
    }

    private function persistPreviewRow(array $row): array
    {
        $primaryEmail = Str::lower((string) ($row['contact']['primaryEmail'] ?? ''));

        if ($primaryEmail === '' || ContactEmail::query()->where('email', $primaryEmail)->exists()) {
            return [
                'resultStatus' => 'duplicate_existing',
                'resultMessage' => 'Le primary_email est déjà présent au moment de la confirmation.',
            ];
        }

        $contact = DB::transaction(function () use ($row, $primaryEmail): Contact {
            $organization = $this->resolveOrganizationForImport($row['raw'] ?? []);
            $contact = Contact::query()->create([
                'organization_id' => $organization->id,
                'first_name' => $row['contact']['firstName'],
                'last_name' => $row['contact']['lastName'],
                'full_name' => $row['contact']['fullName'],
                'job_title' => $row['contact']['jobTitle'],
                'phone' => $row['contact']['phone'],
                'linkedin_url' => $row['contact']['linkedinUrl'],
                'country' => $row['contact']['country'],
                'city' => $row['contact']['city'],
                'tags_json' => $row['contact']['tags'],
                'notes' => $row['contact']['notes'],
                'status' => null,
            ]);

            $contact->contactEmails()->create([
                'email' => $primaryEmail,
                'is_primary' => true,
            ]);

            if (filled($row['contact']['secondaryEmail'])) {
                $contact->contactEmails()->create([
                    'email' => Str::lower((string) $row['contact']['secondaryEmail']),
                    'is_primary' => false,
                ]);
            }

            return $contact->fresh([
                'organization:id,name,domain',
                'contactEmails' => fn ($query) => $query
                    ->where('is_primary', true)
                    ->orderByDesc('is_primary')
                    ->orderBy('id'),
            ]);
        });

        return [
            'resultStatus' => 'imported',
            'resultMessage' => 'Contact importé.',
            'contact' => $this->serializeAudienceContact($contact),
        ];
    }

    private function resolveOrganizationPreview(array $row, Collection $organizations): array
    {
        $organizationName = trim((string) ($row['organization_name'] ?? ''));
        $normalizedName = $this->normalizeOrganizationName($organizationName);
        $domain = $this->normalizeDomain($row['organization_domain'] ?? null, $row['organization_website'] ?? null);

        if ($domain !== null) {
            $domainMatches = $organizations->filter(fn (Organization $organization) => Str::lower((string) $organization->domain) === $domain)->values();

            if ($domainMatches->count() === 1) {
                /** @var Organization $organization */
                $organization = $domainMatches->first();

                return [
                    'status' => 'resolved',
                    'action' => 'match_domain',
                    'organizationId' => $organization->id,
                    'organizationName' => $organization->name,
                    'domain' => $organization->domain,
                ];
            }

            if ($domainMatches->count() > 1) {
                return [
                    'status' => 'invalid',
                    'action' => 'ambiguous',
                    'reasonCode' => 'organization_domain_ambiguous',
                    'reason' => 'organization_domain correspond à plusieurs organisations existantes.',
                ];
            }
        }

        $nameMatches = $organizations->filter(function (Organization $organization) use ($normalizedName): bool {
            return $this->normalizeOrganizationName($organization->name) === $normalizedName;
        })->values();

        if ($nameMatches->count() === 1) {
            /** @var Organization $organization */
            $organization = $nameMatches->first();

            return [
                'status' => 'resolved',
                'action' => 'match_name',
                'organizationId' => $organization->id,
                'organizationName' => $organization->name,
                'domain' => $organization->domain,
            ];
        }

        if ($nameMatches->count() > 1) {
            return [
                'status' => 'invalid',
                'action' => 'ambiguous',
                'reasonCode' => 'organization_name_ambiguous',
                'reason' => 'organization_name correspond à plusieurs organisations existantes.',
            ];
        }

        return [
            'status' => 'resolved',
            'action' => 'create',
            'organizationId' => null,
            'organizationName' => $organizationName,
            'domain' => $domain,
        ];
    }

    private function resolveOrganizationForImport(array $row): Organization
    {
        $organizationName = trim((string) ($row['organization_name'] ?? ''));
        $domain = $this->normalizeDomain($row['organization_domain'] ?? null, $row['organization_website'] ?? null);

        $organization = null;

        if ($domain !== null) {
            $organization = Organization::query()->where('domain', $domain)->first();
        }

        if ($organization === null) {
            $organization = Organization::query()
                ->get(['id', 'name', 'domain', 'website'])
                ->first(fn (Organization $candidate) => $this->normalizeOrganizationName($candidate->name) === $this->normalizeOrganizationName($organizationName));
        }

        if ($organization !== null) {
            if ($organization->domain === null && $domain !== null) {
                $organization->forceFill(['domain' => $domain])->save();
            }

            if ($organization->website === null && filled($row['organization_website'] ?? null)) {
                $organization->forceFill(['website' => $this->nullableString($row['organization_website'])])->save();
            }

            return $organization->refresh();
        }

        return Organization::query()->create([
            'name' => $organizationName,
            'domain' => $domain,
            'website' => $this->nullableString($row['organization_website'] ?? null),
            'notes' => null,
        ]);
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

    private function mapRowToTemplate(array $normalizedHeader, array $row): array
    {
        $mapped = [];

        foreach (self::TEMPLATE_COLUMNS as $column) {
            $index = array_search($column, $normalizedHeader, true);
            $mapped[$column] = $index === false ? null : ($row[$index] ?? null);
        }

        return $mapped;
    }

    private function rowIsEmpty(array $row): bool
    {
        return collect($row)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->isEmpty();
    }

    private function normalizeHeader(string $value): string
    {
        return Str::snake(trim(Str::lower($value)));
    }

    private function normalizeOrganizationName(?string $value): string
    {
        return preg_replace('/\s+/', ' ', Str::lower(trim((string) $value))) ?: '';
    }

    private function normalizeDomain(?string $domain, ?string $website = null): ?string
    {
        $normalized = Str::lower(trim((string) $domain));

        if ($normalized !== '') {
            return preg_replace('/^www\./', '', $normalized) ?: null;
        }

        $websiteValue = trim((string) $website);

        if ($websiteValue === '') {
            return null;
        }

        $host = parse_url($websiteValue, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', Str::lower($host)) ?: null;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function parseTags(mixed $value): array
    {
        return collect(explode(';', (string) $value))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolvedFullName(array $row): ?string
    {
        $fullName = $this->nullableString($row['contact_full_name'] ?? null);

        if ($fullName !== null) {
            return $fullName;
        }

        $firstName = trim((string) ($row['contact_first_name'] ?? ''));
        $lastName = trim((string) ($row['contact_last_name'] ?? ''));
        $combined = trim($firstName.' '.$lastName);

        return $combined !== '' ? $combined : null;
    }

    private function previewCacheKey(string $previewToken): string
    {
        return self::CACHE_PREFIX.$previewToken;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readRows(UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension() ?: '');

        return match ($extension) {
            'xlsx' => $this->readXlsxRows($file),
            default => $this->readCsvRows($file),
        };
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsvRows(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => ['Le fichier CSV ne peut pas être ouvert.'],
            ]);
        }

        while (($row = fgetcsv($handle, separator: ',', escape: '\\')) !== false) {
            $rows[] = array_map(fn ($value): string => trim((string) $value), $row);
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
            throw ValidationException::withMessages([
                'file' => ['Le fichier XLSX ne peut pas être ouvert.'],
            ]);
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $relationships = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));

        if (! $workbook instanceof SimpleXMLElement || ! $relationships instanceof SimpleXMLElement) {
            $zip->close();

            throw ValidationException::withMessages([
                'file' => ['Le fichier XLSX est invalide.'],
            ]);
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

            throw ValidationException::withMessages([
                'file' => ['Impossible de localiser la première feuille XLSX.'],
            ]);
        }

        $sheetXml = simplexml_load_string((string) $zip->getFromName('xl/'.ltrim($target, '/')));
        $zip->close();

        if (! $sheetXml instanceof SimpleXMLElement) {
            throw ValidationException::withMessages([
                'file' => ['La feuille XLSX ne peut pas être lue.'],
            ]);
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

            $text = collect($stringItem->r ?? [])
                ->map(fn ($run): string => (string) ($run->t ?? ''))
                ->implode('');

            $values[] = $text;
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
