<?php

namespace Tests\Feature;

use App\Models\MailboxAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_default_settings_snapshot(): void
    {
        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonPath('mail.provider', 'ovh_mx_plan')
            ->assertJsonPath('mail.mailbox_password_configured', false)
            ->assertJsonPath('general.daily_limit_default', 100)
            ->assertJsonPath('deliverability.tracking_opens_enabled', true);
    }

    public function test_it_updates_mail_settings_and_keeps_a_single_mailbox_account(): void
    {
        $payload = $this->validMailPayload();

        $this->putJson('/api/settings/mail', $payload)
            ->assertOk()
            ->assertJsonPath('mail.provider', 'ovh_mx_plan')
            ->assertJsonPath('mail.mailbox_password_configured', true)
            ->assertJsonPath('mail.sender_email', 'ops@aegis-mail.test')
            ->assertJsonPath('mail.send_window_start', '08:00');

        $this->putJson('/api/settings/mail', array_merge($payload, [
            'sender_name' => 'AEGIS Delivery',
            'mailbox_password' => null,
        ]))->assertOk()->assertJsonPath('mail.sender_name', 'AEGIS Delivery');

        $this->assertDatabaseCount('mailbox_accounts', 1);
        $this->assertDatabaseHas('mailbox_accounts', [
            'provider' => 'ovh_mx_plan',
            'email' => 'ops@aegis-mail.test',
            'display_name' => 'AEGIS Delivery',
            'imap_host' => 'imap.mail.ovh.net',
            'smtp_host' => 'smtp.mail.ovh.net',
            'sync_enabled' => 1,
            'send_enabled' => 1,
        ]);
        $this->assertDatabaseHas('settings', ['key' => 'mail']);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'settings.mail.updated']);

        $mailbox = MailboxAccount::query()->firstOrFail();

        $this->assertNotSame('super-secret-password', $mailbox->getRawOriginal('password_encrypted'));
        $this->assertSame('super-secret-password', $mailbox->password_encrypted);
    }

    public function test_mail_settings_preserve_existing_signature_when_standard_save_sends_null_fields(): void
    {
        $payload = $this->validMailPayload();

        $this->putJson('/api/settings/mail', $payload)->assertOk();

        $this->putJson('/api/settings/mail', array_merge($payload, [
            'sender_name' => 'AEGIS Delivery',
            'mailbox_password' => null,
            'global_signature_html' => null,
            'global_signature_text' => null,
        ]))->assertOk()
            ->assertJsonPath('mail.sender_name', 'AEGIS Delivery')
            ->assertJsonPath('mail.global_signature_html', '<p>Cordialement,<br>AEGIS</p>')
            ->assertJsonPath('mail.global_signature_text', "Cordialement,\nAEGIS");
    }

    public function test_mail_settings_can_explicitly_clear_signature_when_requested(): void
    {
        $payload = $this->validMailPayload();

        $this->putJson('/api/settings/mail', $payload)->assertOk();

        $this->putJson('/api/settings/mail', array_merge($payload, [
            'mailbox_password' => null,
            'global_signature_html' => null,
            'global_signature_text' => null,
            'clear_signature' => true,
        ]))->assertOk()
            ->assertJsonPath('mail.global_signature_html', null)
            ->assertJsonPath('mail.global_signature_text', null);
    }

    public function test_it_validates_mail_settings_payload(): void
    {
        $response = $this->putJson('/api/settings/mail', [
            'sender_email' => 'invalid-email',
            'sender_name' => '',
            'mailbox_username' => '',
            'imap_host' => '',
            'imap_port' => 70000,
            'imap_secure' => 'nope',
            'smtp_host' => '',
            'smtp_port' => 0,
            'smtp_secure' => 'nope',
            'sync_enabled' => 'nope',
            'send_enabled' => 'nope',
            'send_window_start' => '25:00',
            'send_window_end' => 'bad',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sender_email',
                'sender_name',
                'mailbox_username',
                'imap_host',
                'imap_port',
                'imap_secure',
                'smtp_host',
                'smtp_port',
                'smtp_secure',
                'sync_enabled',
                'send_enabled',
                'send_window_start',
                'send_window_end',
            ]);

        $this->assertStringContainsString('Le champ l’adresse d’envoi doit être une adresse e-mail valide.', $response->json('message'));
        $this->assertStringContainsString('Le champ le nom d’expéditeur est obligatoire.', $response->json('message'));
    }

    public function test_it_updates_general_and_deliverability_settings(): void
    {
        $this->putJson('/api/settings/general', [
            'daily_limit_default' => 150,
            'hourly_limit_default' => 15,
            'min_delay_seconds' => 90,
            'jitter_min_seconds' => 10,
            'jitter_max_seconds' => 30,
            'slow_mode_enabled' => true,
            'stop_on_consecutive_failures' => 4,
            'stop_on_hard_bounce_threshold' => 2,
            'open_points' => 1,
            'click_points' => 3,
            'reply_points' => 10,
            'auto_reply_points' => 0,
            'soft_bounce_points' => -5,
            'hard_bounce_points' => -20,
            'unsubscribe_points' => -25,
            'inactivity_decay_days' => 21,
        ])->assertOk()->assertJsonPath('general.daily_limit_default', 150);

        $this->putJson('/api/settings/deliverability', [
            'tracking_opens_enabled' => true,
            'tracking_clicks_enabled' => false,
            'max_links_warning_threshold' => 6,
            'max_remote_images_warning_threshold' => 2,
            'html_size_warning_kb' => 120,
            'attachment_size_warning_mb' => 8,
        ])->assertOk()->assertJsonPath('deliverability.tracking_clicks_enabled', false);

        $this->assertDatabaseHas('settings', ['key' => 'general']);
        $this->assertDatabaseHas('settings', ['key' => 'deliverability']);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'settings.general.updated']);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'settings.deliverability.updated']);
    }

    public function test_it_runs_imap_and_smtp_tests_against_the_stub_gateway(): void
    {
        $this->putJson('/api/settings/mail', $this->validMailPayload())->assertOk();

        $this->postJson('/api/settings/mail/test-imap')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('protocol', 'imap')
            ->assertJsonPath('driver', 'stub');

        $this->postJson('/api/settings/mail/test-smtp')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('protocol', 'smtp')
            ->assertJsonPath('driver', 'stub');

        $this->assertDatabaseHas('mail_events', ['event_type' => 'mailbox.test_imap_succeeded']);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'mailbox.test_smtp_succeeded']);
        $this->assertDatabaseHas('mailbox_accounts', ['health_status' => 'healthy']);
    }

    public function test_smtp_test_returns_precise_validation_messages_for_missing_fields(): void
    {
        $response = $this->postJson('/api/settings/mail/test-smtp', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sender_email',
                'mailbox_username',
                'mailbox_password',
                'smtp_host',
                'smtp_port',
                'smtp_secure',
            ]);

        $this->assertStringContainsString('L’adresse d’envoi est requise pour tester la connexion SMTP.', $response->json('message'));
        $this->assertStringContainsString('L’identifiant de la boîte mail est requis pour tester la connexion SMTP.', $response->json('message'));
        $this->assertStringContainsString('Le mot de passe de la boîte mail est requis pour tester la connexion SMTP.', $response->json('message'));
    }

    public function test_imap_test_returns_precise_validation_messages_for_missing_fields(): void
    {
        $response = $this->postJson('/api/settings/mail/test-imap', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sender_email',
                'mailbox_username',
                'mailbox_password',
                'imap_host',
                'imap_port',
                'imap_secure',
            ]);

        $this->assertStringContainsString('L’adresse d’envoi est requise pour tester la connexion IMAP.', $response->json('message'));
        $this->assertStringContainsString('L’hôte IMAP est requis pour tester la connexion IMAP.', $response->json('message'));
    }

    public function test_smtp_and_imap_tests_can_run_from_unsaved_overrides_only(): void
    {
        $smtpPayload = [
            'sender_email' => 'ops@aegis-mail.test',
            'mailbox_username' => 'ops@aegis-mail.test',
            'mailbox_password' => 'secret-pass',
            'smtp_host' => 'smtp.mail.ovh.net',
            'smtp_port' => 465,
            'smtp_secure' => true,
        ];

        $imapPayload = [
            'sender_email' => 'ops@aegis-mail.test',
            'mailbox_username' => 'ops@aegis-mail.test',
            'mailbox_password' => 'secret-pass',
            'imap_host' => 'imap.mail.ovh.net',
            'imap_port' => 993,
            'imap_secure' => true,
        ];

        $this->postJson('/api/settings/mail/test-smtp', $smtpPayload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('protocol', 'smtp');

        $this->postJson('/api/settings/mail/test-imap', $imapPayload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('protocol', 'imap');

        $this->assertDatabaseMissing('mailbox_accounts', ['provider' => 'ovh_mx_plan']);
    }

    private function validMailPayload(): array
    {
        return [
            'sender_email' => 'ops@aegis-mail.test',
            'sender_name' => 'AEGIS Ops',
            'global_signature_html' => '<p>Cordialement,<br>AEGIS</p>',
            'global_signature_text' => "Cordialement,\nAEGIS",
            'mailbox_username' => 'ops@aegis-mail.test',
            'mailbox_password' => 'super-secret-password',
            'imap_host' => 'imap.mail.ovh.net',
            'imap_port' => 993,
            'imap_secure' => true,
            'smtp_host' => 'smtp.mail.ovh.net',
            'smtp_port' => 465,
            'smtp_secure' => true,
            'sync_enabled' => true,
            'send_enabled' => true,
            'send_window_start' => '08:00',
            'send_window_end' => '18:00',
        ];
    }
}
