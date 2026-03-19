<?php

namespace App\Services\Mailing;

use DOMDocument;
use DOMElement;

class EmailContentService
{
    public function __construct(
        private readonly PublicEmailUrlService $publicEmailUrlService,
    ) {}

    public function prepareBodies(
        ?string $htmlBody,
        ?string $textBody,
        ?string $signatureHtml = null,
        ?string $signatureText = null,
    ): array {
        $bodyText = $this->trimToNull($textBody);
        $bodyHtml = $this->trimToNull($htmlBody);

        if ($bodyHtml === null && $bodyText !== null) {
            $bodyHtml = $this->textToHtml($bodyText);
        }

        $html = $this->joinHtml([$bodyHtml, $signatureHtml]);
        $effectiveSignatureText = $this->trimToNull($signatureText) ?? $this->htmlToText($signatureHtml);
        $text = $bodyText !== null ? $this->joinText([$bodyText, $effectiveSignatureText]) : null;

        $htmlAnalysis = $this->normalizeHtml($html);
        $html = $htmlAnalysis['html_body'];

        $textSynthesized = false;

        if ($text === null && $html !== null) {
            $text = $this->htmlToText($html);
            $textSynthesized = true;
        }

        $textAnalysis = $this->analyzeTextLinks($text);

        return [
            'html_body' => $html,
            'text_body' => $text,
            'analysis' => [
                'hasHtmlVersion' => $html !== null,
                'hasTextVersion' => $text !== null,
                'textSynthesized' => $textSynthesized,
                'linkCount' => $htmlAnalysis['linkCount'] + $textAnalysis['linkCount'],
                'remoteImageCount' => $htmlAnalysis['remoteImageCount'],
                'issues' => [...$htmlAnalysis['issues'], ...$textAnalysis['issues']],
            ],
        ];
    }

    public function textToHtml(?string $textBody): ?string
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", (string) $textBody));

        if ($normalized === '') {
            return null;
        }

        $paragraphs = preg_split("/\n{2,}/", $normalized) ?: [];

        return collect($paragraphs)
            ->map(function (string $paragraph): string {
                $escaped = htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $withBreaks = str_replace("<br>\n", '<br>', nl2br($escaped, false));

                return '<p>'.$withBreaks.'</p>';
            })
            ->filter()
            ->implode("\n");
    }

    public function htmlToText(?string $html): ?string
    {
        $html = $this->trimToNull($html);

        if ($html === null) {
            return null;
        }

        $withLinks = preg_replace_callback(
            '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
            function (array $matches): string {
                $label = trim(html_entity_decode(strip_tags($matches[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $url = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

                if ($label === '' || $label === $url) {
                    return $url;
                }

                return $label.' ('.$url.')';
            },
            $html,
        ) ?? $html;

        $normalized = str_ireplace(
            ['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>', '</tr>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>'],
            ["\n", "\n", "\n", "\n\n", "\n", "\n", "\n", "\n\n", "\n\n", "\n\n", "\n\n", "\n\n", "\n\n"],
            $withLinks,
        );

        $text = html_entity_decode(strip_tags($normalized), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return $this->trimToNull($text);
    }

    private function normalizeHtml(?string $html): array
    {
        $html = $this->trimToNull($html);

        if ($html === null) {
            return [
                'html_body' => null,
                'linkCount' => 0,
                'remoteImageCount' => 0,
                'issues' => [],
            ];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $wrappedHtml = '<!DOCTYPE html><html><body>'.$html.'</body></html>';

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [
                'html_body' => $html,
                'linkCount' => 0,
                'remoteImageCount' => 0,
                'issues' => [],
            ];
        }

        $issues = [];
        $linkCount = 0;
        $remoteImageCount = 0;

        foreach ($document->getElementsByTagName('a') as $anchor) {
            if (! $anchor instanceof DOMElement || ! $anchor->hasAttribute('href')) {
                continue;
            }

            $result = $this->publicEmailUrlService->normalizeContentUrl($anchor->getAttribute('href'));

            if ($result['skip']) {
                continue;
            }

            $linkCount++;

            if ($result['normalized'] !== null) {
                $anchor->setAttribute('href', $result['normalized']);
            }

            if ($result['issue'] !== null) {
                $issues[] = [
                    'code' => 'link_'.$result['issue'],
                    'url' => $result['normalized'] ?? $result['original'],
                    'surface' => 'html',
                ];
            }
        }

        foreach ($document->getElementsByTagName('img') as $image) {
            if (! $image instanceof DOMElement || ! $image->hasAttribute('src')) {
                continue;
            }

            $result = $this->publicEmailUrlService->normalizeContentUrl($image->getAttribute('src'));

            if ($result['skip']) {
                continue;
            }

            $remoteImageCount++;

            if ($result['normalized'] !== null) {
                $image->setAttribute('src', $result['normalized']);
            }

            if ($result['issue'] !== null) {
                $issues[] = [
                    'code' => 'image_'.$result['issue'],
                    'url' => $result['normalized'] ?? $result['original'],
                    'surface' => 'html',
                ];
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        $normalizedHtml = '';

        if ($body !== null) {
            foreach ($body->childNodes as $childNode) {
                $normalizedHtml .= $document->saveHTML($childNode);
            }
        }

        return [
            'html_body' => $this->trimToNull($normalizedHtml) ?? $html,
            'linkCount' => $linkCount,
            'remoteImageCount' => $remoteImageCount,
            'issues' => $issues,
        ];
    }

    private function analyzeTextLinks(?string $text): array
    {
        $text = $this->trimToNull($text);

        if ($text === null) {
            return [
                'linkCount' => 0,
                'issues' => [],
            ];
        }

        preg_match_all('/https?:\/\/[^\s<>"\')]+/i', $text, $matches);

        $issues = [];
        $linkCount = 0;

        foreach ($matches[0] ?? [] as $url) {
            $linkCount++;
            $issue = $this->publicEmailUrlService->classifyPublicHttpsUrl($url);

            if ($issue !== null) {
                $issues[] = [
                    'code' => 'link_'.$issue,
                    'url' => $url,
                    'surface' => 'text',
                ];
            }
        }

        return [
            'linkCount' => $linkCount,
            'issues' => $issues,
        ];
    }

    private function joinHtml(array $parts): ?string
    {
        $normalized = collect($parts)
            ->map(fn (mixed $part): ?string => $this->trimToNull($part))
            ->filter()
            ->values();

        return $normalized->isEmpty() ? null : $normalized->implode("\n\n");
    }

    private function joinText(array $parts): ?string
    {
        $normalized = collect($parts)
            ->map(fn (mixed $part): ?string => $this->trimToNull($part))
            ->filter()
            ->values();

        return $normalized->isEmpty() ? null : $normalized->implode("\n\n");
    }

    private function trimToNull(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
