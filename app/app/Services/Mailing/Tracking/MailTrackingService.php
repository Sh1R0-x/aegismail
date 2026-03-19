<?php

namespace App\Services\Mailing\Tracking;

use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Services\Mailing\MailEventLogger;
use App\Services\Mailing\PublicEmailUrlService;
use App\Services\SettingsStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MailTrackingService
{
    public function __construct(
        private readonly SettingsStore $settingsStore,
        private readonly MailEventLogger $eventLogger,
        private readonly PublicEmailUrlService $publicEmailUrlService,
    ) {}

    public function prepareOutboundBodies(?string $htmlBody, ?string $textBody, string $trackingId): array
    {
        $deliverability = $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', []));
        $trackOpens = (bool) ($deliverability['tracking_opens_enabled'] ?? true);
        $trackClicks = (bool) ($deliverability['tracking_clicks_enabled'] ?? true);

        $tracking = [
            'open' => null,
            'clicks' => [],
        ];

        if ($trackClicks) {
            $clickIndex = 0;

            [$htmlBody, $htmlClicks] = $this->rewriteHtmlLinks($htmlBody, $trackingId, $clickIndex);
            [$textBody, $textClicks] = $this->rewriteTextLinks($textBody, $trackingId, $clickIndex);

            $tracking['clicks'] = [...$htmlClicks, ...$textClicks];
        }

        if ($trackOpens && filled(trim((string) $htmlBody))) {
            $tracking['open'] = [
                'token' => $trackingId,
                'url' => $this->openTrackingUrl($trackingId),
            ];

            $htmlBody = $this->injectOpenPixel((string) $htmlBody, $tracking['open']['url']);
        }

        return [
            'html_body' => $htmlBody,
            'text_body' => $textBody,
            'tracking' => $tracking,
        ];
    }

    public function registerOpen(string $token): bool
    {
        if (! $this->openTrackingEnabled()) {
            return false;
        }

        $message = MailMessage::query()
            ->with('recipient')
            ->where('aegis_tracking_id', $token)
            ->first();

        if ($message === null || ! $this->hasOpenTrackingMetadata($message, $token)) {
            return false;
        }

        $occurredAt = now();
        $isFirstOpen = $message->opened_first_at === null;

        DB::transaction(function () use ($message, $occurredAt, $isFirstOpen): void {
            if ($isFirstOpen) {
                $message->forceFill([
                    'opened_first_at' => $occurredAt,
                ])->save();
            }

            $this->applyRecipientTrackingOutcome(
                $message->recipient,
                $occurredAt,
                fn (string $currentStatus): string => match ($currentStatus) {
                    'sent', 'delivered_if_known' => 'opened',
                    default => $currentStatus,
                },
                'warm',
            );
        });

        if ($isFirstOpen) {
            $this->eventLogger->log(
                'mail_message.opened',
                [
                    'mail_message_id' => $message->id,
                    'tracking_token' => $token,
                ],
                $this->messageRelations($message),
                'mail_message.opened.'.$message->id,
            );
        }

        return true;
    }

    public function registerClickAndResolveRedirect(string $token): ?string
    {
        if (! $this->clickTrackingEnabled()) {
            return null;
        }

        [$trackingId, $index, $signature] = $this->parseClickToken($token);

        if ($trackingId === null || $index === null || $signature === null) {
            return null;
        }

        $message = MailMessage::query()
            ->with('recipient')
            ->where('aegis_tracking_id', $trackingId)
            ->first();

        if ($message === null) {
            return null;
        }

        $trackedLink = collect(data_get($message->headers_json, 'tracking.clicks', []))
            ->first(fn (mixed $link) => is_array($link) && (int) ($link['index'] ?? -1) === $index);

        if (! is_array($trackedLink)) {
            return null;
        }

        $url = (string) ($trackedLink['url'] ?? '');

        if ($url === '' || ! hash_equals($this->clickSignature($trackingId, $index, $url), $signature)) {
            return null;
        }

        $occurredAt = now();
        $isFirstClick = $message->clicked_first_at === null;

        DB::transaction(function () use ($message, $occurredAt): void {
            $message->forceFill([
                'opened_first_at' => $message->opened_first_at ?? $occurredAt,
                'clicked_first_at' => $message->clicked_first_at ?? $occurredAt,
            ])->save();

            $this->applyRecipientTrackingOutcome(
                $message->recipient,
                $occurredAt,
                fn (string $currentStatus): string => match ($currentStatus) {
                    'sent', 'delivered_if_known', 'opened' => 'clicked',
                    default => $currentStatus,
                },
                'interested',
            );
        });

        if ($isFirstClick) {
            $this->eventLogger->log(
                'mail_message.clicked',
                [
                    'mail_message_id' => $message->id,
                    'tracking_token' => $token,
                    'link_index' => $index,
                    'url' => $url,
                ],
                $this->messageRelations($message),
                'mail_message.clicked.'.$message->id.'.'.$index,
            );
        }

        return $url;
    }

    private function rewriteHtmlLinks(?string $htmlBody, string $trackingId, int &$clickIndex): array
    {
        if (! filled(trim((string) $htmlBody))) {
            return [$htmlBody, []];
        }

        $trackedLinks = [];

        $rewritten = preg_replace_callback(
            '/href\s*=\s*(["\'])(https?:\/\/[^"\']+)\1/i',
            function (array $matches) use ($trackingId, &$clickIndex, &$trackedLinks): string {
                $clickIndex++;
                $originalUrl = html_entity_decode($matches[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $token = $this->clickToken($trackingId, $clickIndex, $originalUrl);

                $trackedLinks[] = [
                    'index' => $clickIndex,
                    'token' => $token,
                    'url' => $originalUrl,
                    'tracked_url' => $this->clickTrackingUrl($token),
                    'surface' => 'html',
                ];

                return 'href='.$matches[1].e($this->clickTrackingUrl($token), false).$matches[1];
            },
            (string) $htmlBody,
        );

        return [$rewritten, $trackedLinks];
    }

    private function rewriteTextLinks(?string $textBody, string $trackingId, int &$clickIndex): array
    {
        if (! filled(trim((string) $textBody))) {
            return [$textBody, []];
        }

        $trackedLinks = [];

        $rewritten = preg_replace_callback(
            '/https?:\/\/[^\s<>"\')]+/i',
            function (array $matches) use ($trackingId, &$clickIndex, &$trackedLinks): string {
                $clickIndex++;
                $originalUrl = $matches[0];
                $token = $this->clickToken($trackingId, $clickIndex, $originalUrl);

                $trackedLinks[] = [
                    'index' => $clickIndex,
                    'token' => $token,
                    'url' => $originalUrl,
                    'tracked_url' => $this->clickTrackingUrl($token),
                    'surface' => 'text',
                ];

                return $this->clickTrackingUrl($token);
            },
            (string) $textBody,
        );

        return [$rewritten, $trackedLinks];
    }

    private function injectOpenPixel(string $htmlBody, string $pixelUrl): string
    {
        $pixel = '<img src="'.e($pixelUrl, false).'" alt="" width="1" height="1" style="display:block;border:0;outline:none;text-decoration:none;width:1px;height:1px;" />';

        if (stripos($htmlBody, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $pixel.'</body>', $htmlBody, 1) ?? $htmlBody.$pixel;
        }

        return rtrim($htmlBody)."\n".$pixel;
    }

    private function parseClickToken(string $token): array
    {
        $parts = explode('.', $token, 3);

        if (count($parts) !== 3) {
            return [null, null, null];
        }

        return [
            Str::isUuid($parts[0]) ? $parts[0] : null,
            ctype_digit($parts[1]) ? (int) $parts[1] : null,
            ctype_xdigit($parts[2]) ? strtolower($parts[2]) : null,
        ];
    }

    private function clickToken(string $trackingId, int $index, string $url): string
    {
        return implode('.', [
            $trackingId,
            $index,
            $this->clickSignature($trackingId, $index, $url),
        ]);
    }

    private function clickSignature(string $trackingId, int $index, string $url): string
    {
        return substr(hash_hmac('sha256', $trackingId.'|'.$index.'|'.$url, $this->trackingSecret()), 0, 32);
    }

    private function openTrackingEnabled(): bool
    {
        return (bool) ($this->trackingSettings()['tracking_opens_enabled'] ?? true);
    }

    private function clickTrackingEnabled(): bool
    {
        return (bool) ($this->trackingSettings()['tracking_clicks_enabled'] ?? true);
    }

    private function trackingSettings(): array
    {
        return $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', []));
    }

    private function hasOpenTrackingMetadata(MailMessage $message, string $token): bool
    {
        return data_get($message->headers_json, 'tracking.open.token') === $token;
    }

    private function trackingSecret(): string
    {
        $key = (string) config('app.key', 'aegis-mailing');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }

    private function applyRecipientTrackingOutcome(
        ?MailRecipient $recipient,
        Carbon $occurredAt,
        callable $statusResolver,
        string $scoreBucket,
    ): void {
        if ($recipient === null) {
            return;
        }

        $currentStatus = (string) $recipient->status;
        $nextStatus = $statusResolver($currentStatus);

        if ($nextStatus !== $currentStatus) {
            $recipient->status = $nextStatus;
        }

        if (in_array($recipient->status, ['opened', 'clicked'], true)) {
            $recipient->score_bucket = $scoreBucket;
        }

        $recipient->last_event_at = $occurredAt;
        $recipient->save();
    }

    private function openTrackingUrl(string $token): string
    {
        $url = $this->publicEmailUrlService->trackingUrl('/t/o/'.$token.'.gif');

        if ($url === null) {
            throw new \RuntimeException('A public HTTPS tracking base URL is required before injecting tracking pixels.');
        }

        return $url;
    }

    private function clickTrackingUrl(string $token): string
    {
        $url = $this->publicEmailUrlService->trackingUrl('/t/c/'.$token);

        if ($url === null) {
            throw new \RuntimeException('A public HTTPS tracking base URL is required before rewriting tracked links.');
        }

        return $url;
    }

    private function messageRelations(MailMessage $message): array
    {
        return [
            'mailbox_account_id' => $message->mailbox_account_id,
            'campaign_id' => $message->recipient?->campaign_id,
            'recipient_id' => $message->recipient_id,
            'thread_id' => $message->thread_id,
            'message_id' => $message->id,
        ];
    }
}
