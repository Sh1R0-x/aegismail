<?php

namespace App\Services\Mailing;

use App\Services\SettingsStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DeliverabilityDomainCheckService
{
    public function __construct(
        private readonly SettingsStore $settingsStore,
        private readonly MailboxSettingsService $mailboxSettingsService,
        private readonly MailEventLogger $eventLogger,
        private readonly PublicEmailUrlService $publicEmailUrlService,
    ) {}

    public function payload(): array
    {
        $settings = $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', []));
        $mailSettings = $this->mailboxSettingsService->getSettings();

        return $this->composePayload($settings, $mailSettings);
    }

    public function refresh(?array $mechanisms = null, ?int $updatedBy = null): array
    {
        $settings = $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', []));
        $mailSettings = $this->mailboxSettingsService->getSettings();
        $domain = $this->resolveDomain($settings, $mailSettings);
        $mechanisms = $mechanisms !== null && $mechanisms !== [] ? array_values(array_unique($mechanisms)) : ['spf', 'dkim', 'dmarc'];
        $checks = $this->normalizeChecks($settings['checks'] ?? []);

        foreach ($mechanisms as $mechanism) {
            $checks[$mechanism] = $domain === null
                ? $this->missingDomainCheck($mechanism)
                : match ($mechanism) {
                    'spf' => $this->runSpfCheck($domain),
                    'dkim' => $this->runDkimCheck($domain, $this->dkimSelectors($settings)),
                    'dmarc' => $this->runDmarcCheck($domain),
                };
        }

        $settings['checks'] = $checks;
        $stored = $this->settingsStore->put('deliverability', $settings, $updatedBy);

        $this->eventLogger->log(
            'settings.deliverability.checks_refreshed',
            [
                'domain' => $domain,
                'mechanisms' => $mechanisms,
                'checks' => Arr::map($checks, fn (array $check): array => Arr::only($check, ['status', 'diagnostic_message', 'checked_at'])),
            ],
        );

        return $this->composePayload($stored->value_json ?? $settings, $mailSettings);
    }

    public function composePayload(array $settings, array $mailSettings): array
    {
        $checks = $this->normalizeChecks($settings['checks'] ?? []);
        $domain = $this->resolveDomain($settings, $mailSettings);
        $publicBase = $this->publicEmailUrlService->publicBaseReport();
        $trackingBase = $this->publicEmailUrlService->trackingBaseReport();

        return array_merge($settings, [
            'domain' => $domain,
            'dkimSelectors' => $this->dkimSelectors($settings),
            'checks' => $checks,
            'refreshEndpoint' => '/api/settings/deliverability/checks/refresh',
            'spfValid' => ($checks['spf']['status'] ?? null) === 'pass',
            'dkimValid' => ($checks['dkim']['status'] ?? null) === 'pass',
            'dmarcValid' => ($checks['dmarc']['status'] ?? null) === 'pass',
            'trackOpens' => (bool) ($settings['tracking_opens_enabled'] ?? true),
            'trackClicks' => (bool) ($settings['tracking_clicks_enabled'] ?? true),
            'maxLinks' => (int) ($settings['max_links_warning_threshold'] ?? 8),
            'maxImages' => (int) ($settings['max_remote_images_warning_threshold'] ?? 3),
            'maxHtmlSizeKb' => (int) ($settings['html_size_warning_kb'] ?? 100),
            'maxAttachmentSizeMb' => (int) ($settings['attachment_size_warning_mb'] ?? 10),
            'publicBaseUrl' => $publicBase['resolved'],
            'trackingBaseUrl' => $trackingBase['resolved'],
            'publicBaseUrlStatus' => $publicBase['status'],
            'trackingBaseUrlStatus' => $trackingBase['status'],
            'publicBaseUrlIssue' => $publicBase['issue'],
            'trackingBaseUrlIssue' => $trackingBase['issue'],
        ]);
    }

    private function normalizeChecks(array $checks): array
    {
        return [
            'spf' => $this->normalizeCheck($checks['spf'] ?? [], 'SPF'),
            'dkim' => $this->normalizeCheck($checks['dkim'] ?? [], 'DKIM'),
            'dmarc' => $this->normalizeCheck($checks['dmarc'] ?? [], 'DMARC'),
        ];
    }

    private function normalizeCheck(array $check, string $label): array
    {
        return [
            'status' => $check['status'] ?? 'not_detected',
            'detected_value' => $check['detected_value'] ?? null,
            'checked_at' => $check['checked_at'] ?? null,
            'diagnostic_message' => $check['diagnostic_message'] ?? "{$label} non testé.",
            'logs' => is_array($check['logs'] ?? null) ? $check['logs'] : [],
        ];
    }

    private function missingDomainCheck(string $mechanism): array
    {
        return [
            'status' => 'not_detected',
            'detected_value' => null,
            'checked_at' => Carbon::now()->toIso8601String(),
            'diagnostic_message' => 'Aucun domaine expéditeur n’est disponible. Configurez sender_email ou domain_override avant de relancer le contrôle.',
            'logs' => [
                $this->logEntry('warning', strtoupper($mechanism).' impossible à tester: domaine expéditeur absent.', []),
            ],
        ];
    }

    private function runSpfCheck(string $domain): array
    {
        $lookup = $this->lookupTxtRecords($domain);
        $records = array_values(array_filter($lookup['records'], fn (string $record): bool => Str::startsWith(Str::lower(trim($record)), 'v=spf1')));
        $logs = array_merge($lookup['logs'], [
            $this->logEntry('info', 'Enregistrements SPF candidats détectés.', ['count' => count($records)]),
        ]);

        if ($records === []) {
            return $this->finalizeCheck('not_detected', null, 'Aucun enregistrement SPF n’a été détecté pour le domaine.', $logs);
        }

        if (count($records) > 1) {
            return $this->finalizeCheck('fail', implode(' | ', $records), 'Plusieurs enregistrements SPF ont été détectés. Un seul SPF doit être publié.', $logs);
        }

        $record = $records[0];

        if (! preg_match('/\s[~+\-?]?all\b/i', $record)) {
            return $this->finalizeCheck('warning', $record, 'Un SPF a été détecté, mais il ne contient pas de mécanisme all explicite.', $logs);
        }

        return $this->finalizeCheck('pass', $record, 'SPF détecté et exploitable.', $logs);
    }

    private function runDkimCheck(string $domain, array $selectors): array
    {
        $logs = [];
        $validRecords = [];
        $invalidHosts = [];

        foreach ($selectors as $selector) {
            $host = "{$selector}._domainkey.{$domain}";
            $lookup = $this->lookupTxtRecords($host);
            $logs = array_merge($logs, $lookup['logs']);
            $records = array_values(array_filter($lookup['records'], fn (string $record): bool => Str::contains(Str::lower($record), 'v=dkim1')));

            if ($records === []) {
                $invalidHosts[] = $host;

                continue;
            }

            foreach ($records as $record) {
                $validRecords[] = [
                    'selector' => $selector,
                    'host' => $host,
                    'record' => $record,
                ];
            }
        }

        if ($validRecords === []) {
            $logs[] = $this->logEntry('warning', 'Aucun enregistrement DKIM exploitable trouvé sur les sélecteurs testés.', [
                'selectors' => $selectors,
                'hosts' => $invalidHosts,
            ]);

            return $this->finalizeCheck('not_detected', null, 'Aucun DKIM détecté avec les sélecteurs testés. Vérifiez les sélecteurs OVH configurés.', $logs);
        }

        $record = $validRecords[0]['record'];

        if (! Str::contains(Str::lower($record), 'p=')) {
            return $this->finalizeCheck('fail', $record, 'Un enregistrement DKIM a été trouvé, mais la clé publique p= est absente.', $logs);
        }

        $selectorList = collect($validRecords)->pluck('selector')->unique()->values()->all();

        return $this->finalizeCheck('pass', $record, 'DKIM détecté sur le ou les sélecteurs '.implode(', ', $selectorList).'.', $logs);
    }

    private function runDmarcCheck(string $domain): array
    {
        $host = "_dmarc.{$domain}";
        $lookup = $this->lookupTxtRecords($host);
        $records = array_values(array_filter($lookup['records'], fn (string $record): bool => Str::startsWith(Str::upper(trim($record)), 'V=DMARC1')));
        $logs = array_merge($lookup['logs'], [
            $this->logEntry('info', 'Enregistrements DMARC candidats détectés.', ['count' => count($records)]),
        ]);

        if ($records === []) {
            return $this->finalizeCheck('not_detected', null, 'Aucun enregistrement DMARC n’a été détecté.', $logs);
        }

        if (count($records) > 1) {
            return $this->finalizeCheck('fail', implode(' | ', $records), 'Plusieurs enregistrements DMARC ont été détectés.', $logs);
        }

        $record = $records[0];

        if (! preg_match('/\bp=([a-z_]+)\b/i', $record, $matches)) {
            return $this->finalizeCheck('fail', $record, 'Un DMARC a été détecté, mais la politique p= est absente.', $logs);
        }

        if (Str::lower($matches[1]) === 'none') {
            return $this->finalizeCheck('warning', $record, 'DMARC est détecté avec une politique p=none.', $logs);
        }

        return $this->finalizeCheck('pass', $record, 'DMARC détecté et exploitable.', $logs);
    }

    private function finalizeCheck(string $status, ?string $detectedValue, string $diagnostic, array $logs): array
    {
        return [
            'status' => $status,
            'detected_value' => $detectedValue,
            'checked_at' => Carbon::now()->toIso8601String(),
            'diagnostic_message' => $diagnostic,
            'logs' => $logs,
        ];
    }

    private function resolveDomain(array $settings, array $mailSettings): ?string
    {
        $override = trim((string) ($settings['domain_override'] ?? ''));

        if ($override !== '') {
            return $this->sanitizeDomain($override);
        }

        $senderEmail = trim((string) ($mailSettings['sender_email'] ?? ''));

        if ($senderEmail !== '' && Str::contains($senderEmail, '@')) {
            return $this->sanitizeDomain((string) Str::after($senderEmail, '@'));
        }

        return null;
    }

    private function dkimSelectors(array $settings): array
    {
        $configured = $settings['dkim_selectors'] ?? config('mailing.defaults.deliverability.dkim_selectors', []);

        return collect(is_array($configured) ? $configured : [])
            ->map(fn ($selector): string => trim((string) $selector))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function sanitizeDomain(string $value): ?string
    {
        $domain = preg_replace('/^www\./', '', Str::lower(trim($value))) ?: null;

        return $domain !== '' ? $domain : null;
    }

    private function logEntry(string $level, string $message, array $context): array
    {
        return [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'recordedAt' => Carbon::now()->toIso8601String(),
        ];
    }

    protected function lookupTxtRecords(string $host): array
    {
        $startedAt = microtime(true);
        $warning = null;

        set_error_handler(function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        });

        try {
            $rawRecords = dns_get_record($host, DNS_TXT);
        } catch (\Throwable $exception) {
            restore_error_handler();

            return [
                'records' => [],
                'logs' => [
                    $this->logEntry('error', 'La résolution DNS a levé une exception.', [
                        'host' => $host,
                        'exception' => $exception->getMessage(),
                    ]),
                ],
            ];
        }

        restore_error_handler();

        $records = collect(is_array($rawRecords) ? $rawRecords : [])
            ->map(function (array $record): string {
                if (isset($record['txt']) && is_string($record['txt'])) {
                    return $record['txt'];
                }

                if (isset($record['entries']) && is_array($record['entries'])) {
                    return implode('', $record['entries']);
                }

                return '';
            })
            ->filter()
            ->values()
            ->all();

        $logs = [
            $this->logEntry('info', 'Résolution TXT terminée.', [
                'host' => $host,
                'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
                'recordCount' => count($records),
            ]),
        ];

        if ($warning !== null) {
            $logs[] = $this->logEntry('warning', 'Le résolveur DNS a renvoyé un avertissement.', [
                'host' => $host,
                'warning' => $warning,
            ]);
        }

        return [
            'records' => $records,
            'logs' => $logs,
        ];
    }
}
