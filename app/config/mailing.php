<?php

return [
    'provider' => 'ovh_mx_plan',

    // All outbound sends share the same queue in V1, whether single or bulk.
    'queues' => [
        'outbound' => env('MAIL_OUTBOUND_QUEUE', 'mail-outbound'),
        'sync' => env('MAIL_SYNC_QUEUE', 'mail-sync'),
    ],

    'gateway' => [
        'driver' => env('MAIL_GATEWAY_DRIVER', 'stub'),
    ],

    'defaults' => [
        'general' => [
            'daily_limit_default' => 100,
            'hourly_limit_default' => 12,
            'min_delay_seconds' => 60,
            'jitter_min_seconds' => 5,
            'jitter_max_seconds' => 20,
            'slow_mode_enabled' => false,
            'stop_on_consecutive_failures' => 5,
            'stop_on_hard_bounce_threshold' => 3,
            'open_points' => 1,
            'click_points' => 2,
            'reply_points' => 8,
            'auto_reply_points' => 0,
            'soft_bounce_points' => -5,
            'hard_bounce_points' => -15,
            'unsubscribe_points' => -20,
            'inactivity_decay_days' => 30,
        ],
        'mail' => [
            'provider' => 'ovh_mx_plan',
            'sender_email' => '',
            'sender_name' => '',
            'global_signature_html' => null,
            'global_signature_text' => null,
            'mailbox_username' => '',
            'mailbox_password_configured' => false,
            'imap_host' => '',
            'imap_port' => 993,
            'imap_secure' => true,
            'smtp_host' => '',
            'smtp_port' => 465,
            'smtp_secure' => true,
            'sync_enabled' => true,
            'send_enabled' => true,
            'send_window_start' => '09:00',
            'send_window_end' => '18:00',
            'health_status' => 'unknown',
            'health_message' => null,
            'last_sync_at' => null,
        ],
        'deliverability' => [
            'tracking_opens_enabled' => true,
            'tracking_clicks_enabled' => true,
            'max_links_warning_threshold' => 8,
            'max_remote_images_warning_threshold' => 3,
            'html_size_warning_kb' => 100,
            'attachment_size_warning_mb' => 10,
            'domain_override' => null,
            'dkim_selectors' => ['selector1', 'selector2', 'default', 'mail'],
            'checks' => [],
        ],
    ],
];
