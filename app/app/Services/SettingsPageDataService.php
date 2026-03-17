<?php

namespace App\Services;

use App\Services\Mailing\MailboxSettingsService;

class SettingsPageDataService
{
    public function __construct(
        private readonly SettingsStore $settingsStore,
        private readonly MailboxSettingsService $mailboxSettingsService,
    ) {
    }

    public function page(): array
    {
        $general = $this->settingsStore->get('general', config('mailing.defaults.general', []));
        $mail = $this->mailboxSettingsService->getSettings();
        $deliverability = $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', []));

        return [
            'settings' => [
                'mail' => $mail,
                'deliverability' => array_merge($deliverability, [
                    'spfValid' => false,
                    'dkimValid' => false,
                    'dmarcValid' => false,
                    'trackOpens' => (bool) ($deliverability['tracking_opens_enabled'] ?? true),
                    'trackClicks' => (bool) ($deliverability['tracking_clicks_enabled'] ?? true),
                    'maxLinks' => (int) ($deliverability['max_links_warning_threshold'] ?? 8),
                    'maxImages' => (int) ($deliverability['max_remote_images_warning_threshold'] ?? 3),
                    'maxHtmlSizeKb' => (int) ($deliverability['html_size_warning_kb'] ?? 100),
                    'maxAttachmentSizeMb' => (int) ($deliverability['attachment_size_warning_mb'] ?? 10),
                    'maxConsecutiveHardBounces' => (int) ($general['stop_on_hard_bounce_threshold'] ?? 3),
                    'bounceWarningThreshold' => null,
                    'bounceCriticalThreshold' => null,
                ]),
                'cadence' => [
                    'dailyLimit' => (int) ($general['daily_limit_default'] ?? 100),
                    'hourlyLimit' => (int) ($general['hourly_limit_default'] ?? 12),
                    'minDelay' => (int) ($general['min_delay_seconds'] ?? 60),
                    'maxJitter' => (int) ($general['jitter_max_seconds'] ?? 20),
                    'slowModeEnabled' => (bool) ($general['slow_mode_enabled'] ?? false),
                    'slowModeFactor' => (bool) ($general['slow_mode_enabled'] ?? false) ? 2 : null,
                    'bounceStopThreshold' => (int) ($general['stop_on_hard_bounce_threshold'] ?? 3),
                    'maxConsecutiveErrors' => (int) ($general['stop_on_consecutive_failures'] ?? 5),
                ],
                'scoring' => [
                    'openPoints' => (int) ($general['open_points'] ?? 1),
                    'clickPoints' => (int) ($general['click_points'] ?? 2),
                    'replyPoints' => (int) ($general['reply_points'] ?? 8),
                    'bouncePoints' => (int) ($general['hard_bounce_points'] ?? -15),
                    'unsubscribePoints' => (int) ($general['unsubscribe_points'] ?? -20),
                    'inactivityDecayDays' => (int) ($general['inactivity_decay_days'] ?? 30),
                    'inactivityDecayPoints' => -1,
                ],
                'signature' => [
                    'global_signature_html' => $mail['global_signature_html'] ?? null,
                    'global_signature_text' => $mail['global_signature_text'] ?? null,
                    'html' => $mail['global_signature_html'] ?? null,
                    'text' => $mail['global_signature_text'] ?? null,
                ],
            ],
        ];
    }
}
