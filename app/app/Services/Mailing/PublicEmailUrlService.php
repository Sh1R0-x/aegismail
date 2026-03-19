<?php

namespace App\Services\Mailing;

use App\Services\SettingsStore;
use Illuminate\Support\Str;

class PublicEmailUrlService
{
    private const NON_HTTP_PREFIXES = ['mailto:', 'tel:', 'cid:', 'data:', 'javascript:'];

    public function __construct(
        private readonly SettingsStore $settingsStore,
    ) {}

    public function publicBaseReport(): array
    {
        $settings = $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', []));
        $configured = $this->trimToNull($settings['public_base_url'] ?? null);

        if ($configured !== null) {
            return $this->reportForBaseUrl($configured, 'settings.deliverability.public_base_url');
        }

        $appUrl = $this->trimToNull(config('app.url'));

        if ($appUrl !== null) {
            return $this->reportForBaseUrl($appUrl, 'app.url');
        }

        return $this->missingReport('public_base_url_missing', 'Aucune URL publique email n’est configurée.', 'app.url');
    }

    public function trackingBaseReport(): array
    {
        $settings = $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', []));
        $configured = $this->trimToNull($settings['tracking_base_url'] ?? null);

        if ($configured !== null) {
            return $this->reportForBaseUrl($configured, 'settings.deliverability.tracking_base_url');
        }

        $publicBase = $this->publicBaseReport();

        if ($publicBase['resolved'] !== null) {
            return [
                'configured' => $publicBase['resolved'],
                'resolved' => $publicBase['resolved'],
                'source' => 'public_base_url',
                'status' => 'valid',
                'issue' => null,
                'message' => null,
            ];
        }

        return [
            'configured' => $publicBase['configured'],
            'resolved' => null,
            'source' => $publicBase['source'],
            'status' => $publicBase['status'],
            'issue' => $publicBase['issue'],
            'message' => $publicBase['message'],
        ];
    }

    public function publicBaseUrl(): ?string
    {
        return $this->publicBaseReport()['resolved'];
    }

    public function trackingBaseUrl(): ?string
    {
        return $this->trackingBaseReport()['resolved'];
    }

    public function trackingUrl(string $path): ?string
    {
        $baseUrl = $this->trackingBaseUrl();

        if ($baseUrl === null) {
            return null;
        }

        return $this->joinPath($baseUrl, $path);
    }

    public function normalizeContentUrl(string $value): array
    {
        $raw = trim(html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        if ($raw === '' || $this->shouldIgnoreForEmail($raw)) {
            return [
                'original' => $value,
                'normalized' => $raw,
                'issue' => null,
                'skip' => true,
                'was_relative' => false,
            ];
        }

        $candidate = str_starts_with($raw, '//') ? 'https:'.$raw : $raw;
        $wasRelative = $this->isRelativeUrl($candidate);

        if ($wasRelative) {
            $baseUrl = $this->publicBaseUrl();

            if ($baseUrl === null) {
                return [
                    'original' => $value,
                    'normalized' => null,
                    'issue' => 'requires_public_base',
                    'skip' => false,
                    'was_relative' => true,
                ];
            }

            $candidate = $this->joinPath($baseUrl, $candidate);
        }

        return [
            'original' => $value,
            'normalized' => $candidate,
            'issue' => $this->classifyPublicHttpsUrl($candidate),
            'skip' => false,
            'was_relative' => $wasRelative,
        ];
    }

    public function classifyPublicHttpsUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return 'not_public';
        }

        if (Str::lower((string) $parts['scheme']) !== 'https') {
            return 'not_https';
        }

        return $this->classifyPublicHost((string) $parts['host']);
    }

    public function issueMessage(string $kind, string $issue, int $count, array $sampleUrls = []): string
    {
        $subject = $kind === 'image' ? 'image distante' : 'lien';
        $pluralizedSubject = $count > 1 ? "{$subject}s" : $subject;
        $samples = $sampleUrls !== [] ? ' Exemples : '.implode(', ', $sampleUrls).'.' : '';

        return match ($issue) {
            'requires_public_base' => "Le message contient {$count} {$pluralizedSubject} relatif(s), mais aucune URL publique HTTPS n’est configurée pour les emails.".$samples,
            'not_https' => "Le message contient {$count} {$pluralizedSubject} non HTTPS.".$samples,
            default => "Le message contient {$count} {$pluralizedSubject} local(aux), privé(s) ou non public(s).".$samples,
        };
    }

    private function reportForBaseUrl(string $candidate, string $source): array
    {
        $normalized = $this->trimToNull($candidate);

        if ($normalized === null) {
            return $this->missingReport('public_base_url_missing', 'Aucune URL publique email n’est configurée.', $source);
        }

        $issue = $this->classifyPublicHttpsUrl($normalized);

        if ($issue === null) {
            return [
                'configured' => $candidate,
                'resolved' => rtrim($normalized, '/'),
                'source' => $source,
                'status' => 'valid',
                'issue' => null,
                'message' => null,
            ];
        }

        return [
            'configured' => $candidate,
            'resolved' => null,
            'source' => $source,
            'status' => 'invalid',
            'issue' => $issue === 'not_https' ? 'public_base_url_not_https' : 'public_base_url_not_public',
            'message' => $issue === 'not_https'
                ? 'L’URL publique email doit être absolue et en HTTPS.'
                : 'L’URL publique email pointe vers un hôte local, privé ou non public.',
        ];
    }

    private function missingReport(string $issue, string $message, string $source): array
    {
        return [
            'configured' => null,
            'resolved' => null,
            'source' => $source,
            'status' => 'missing',
            'issue' => $issue,
            'message' => $message,
        ];
    }

    private function classifyPublicHost(string $host): ?string
    {
        $host = Str::lower(trim($host, '.'));

        if ($host === '' || $host === 'localhost' || ! str_contains($host, '.')) {
            return 'not_public';
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                ? null
                : 'not_public';
        }

        foreach (['.localhost', '.local', '.localdomain', '.internal', '.lan', '.home.arpa'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return 'not_public';
            }
        }

        $tld = Str::afterLast($host, '.');

        if (in_array($tld, ['test', 'invalid', 'localhost', 'local'], true)) {
            return 'not_public';
        }

        return null;
    }

    private function shouldIgnoreForEmail(string $value): bool
    {
        if (str_starts_with($value, '#')) {
            return true;
        }

        return collect(self::NON_HTTP_PREFIXES)
            ->contains(fn (string $prefix): bool => Str::startsWith(Str::lower($value), $prefix));
    }

    private function isRelativeUrl(string $value): bool
    {
        return parse_url($value, PHP_URL_SCHEME) === null;
    }

    private function joinPath(string $baseUrl, string $path): string
    {
        if ($path === '') {
            return rtrim($baseUrl, '/');
        }

        if (str_starts_with($path, '?') || str_starts_with($path, '#')) {
            return rtrim($baseUrl, '/').$path;
        }

        $normalized = preg_replace('#^(\./|\../)+#', '', $path) ?? $path;

        return rtrim($baseUrl, '/').'/'.ltrim($normalized, '/');
    }

    private function trimToNull(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
