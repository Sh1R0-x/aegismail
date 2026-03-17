<?php

namespace Tests\Feature;

use App\Models\MailboxAccount;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsPagePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_exposes_default_payload_shape(): void
    {
        $this->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->has('settings')
                ->where('settings.mail.provider', 'ovh_mx_plan')
                ->where('settings.cadence.dailyLimit', 100)
                ->where('settings.scoring.replyPoints', 8)
                ->where('settings.signature.global_signature_html', null)
                ->etc()
            );
    }

    public function test_settings_page_exposes_mail_deliverability_cadence_scoring_and_signature_payloads(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'general'],
            ['value_json' => array_replace(config('mailing.defaults.general', []), [
                'daily_limit_default' => 150,
                'hourly_limit_default' => 10,
                'min_delay_seconds' => 45,
                'jitter_max_seconds' => 12,
                'slow_mode_enabled' => true,
                'stop_on_consecutive_failures' => 4,
                'stop_on_hard_bounce_threshold' => 2,
                'open_points' => 2,
                'click_points' => 4,
                'reply_points' => 9,
                'hard_bounce_points' => -25,
                'unsubscribe_points' => -30,
                'inactivity_decay_days' => 21,
            ])],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'deliverability'],
            ['value_json' => array_replace(config('mailing.defaults.deliverability', []), [
                'tracking_opens_enabled' => false,
                'tracking_clicks_enabled' => true,
                'max_links_warning_threshold' => 6,
                'max_remote_images_warning_threshold' => 2,
                'html_size_warning_kb' => 80,
                'attachment_size_warning_mb' => 5,
            ])],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'mail'],
            ['value_json' => [
                'global_signature_html' => '<p>Cordialement,<br>AEGIS</p>',
                'global_signature_text' => "Cordialement,\nAEGIS",
                'send_window_start' => '08:00',
                'send_window_end' => '18:00',
            ]],
        );

        MailboxAccount::query()->create([
            'provider' => 'ovh_mx_plan',
            'email' => 'ops@aegis.test',
            'display_name' => 'AEGIS Ops',
            'username' => 'ops@aegis.test',
            'password_encrypted' => 'secret',
            'imap_host' => 'imap.mail.ovh.net',
            'imap_port' => 993,
            'imap_secure' => true,
            'smtp_host' => 'smtp.mail.ovh.net',
            'smtp_port' => 465,
            'smtp_secure' => true,
            'sync_enabled' => true,
            'send_enabled' => true,
            'health_status' => 'healthy',
        ]);

        $this->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('settings.mail.sender_email', 'ops@aegis.test')
                ->where('settings.mail.send_window_start', '08:00')
                ->where('settings.deliverability.trackOpens', false)
                ->where('settings.deliverability.maxLinks', 6)
                ->where('settings.cadence.dailyLimit', 150)
                ->where('settings.cadence.maxConsecutiveErrors', 4)
                ->where('settings.scoring.replyPoints', 9)
                ->where('settings.scoring.bouncePoints', -25)
                ->where('settings.signature.global_signature_html', '<p>Cordialement,<br>AEGIS</p>')
                ->where('settings.signature.text', "Cordialement,\nAEGIS")
                ->etc()
            );
    }
}
