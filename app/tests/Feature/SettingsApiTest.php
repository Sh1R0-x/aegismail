<?php

namespace Tests\Feature;

use App\Models\MailboxAccount;
use App\Models\SmtpProviderAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_default_settings_snapshot(): void
    {
        config(['app.url' => 'https://mail.example.com']);

        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonPath('mail.mailbox_provider', 'ovh_mx_plan')
            ->assertJsonPath('mail.active_provider', 'ovh_mx_plan')
            ->assertJsonPath('mail.mailbox_password_configured', false)
            ->assertJsonPath('mail.providers.ovh_mx_plan.label', 'OVH MX Plan')
            ->assertJsonPath('mail.providers.ovh_mx_plan.activatable', false)
            ->assertJsonPath('mail.providers.smtp2go.label', 'SMTP2GO')
            ->assertJsonPath('mail.providers.smtp2go.smtp_password_configured', false)
            ->assertJsonPath('general.daily_limit_default', 100)
            ->assertJsonPath('deliverability.tracking_opens_enabled', true)
            ->assertJsonPath('deliverability.publicBaseUrl', 'https://mail.example.com')
            ->assertJsonPath('deliverability.publicBaseUrlStatus', 'valid');
    }

    public function test_it_updates_mail_settings_and_keeps_a_single_mailbox_account(): void
    {
        $payload = $this->validMailPayload();

        $this->putJson('/api/settings/mail', $payload)
            ->assertOk()
            ->assertJsonPath('mail.mailbox_provider', 'ovh_mx_plan')
            ->assertJsonPath('mail.active_provider', 'ovh_mx_plan')
            ->assertJsonPath('mail.mailbox_password_configured', true)
            ->assertJsonPath('mail.sender_email', 'ops@aegis-mail.test')
            ->assertJsonPath('mail.providers.ovh_mx_plan.smtp_host', 'smtp.mail.ovh.net')
            ->assertJsonPath('mail.providers.ovh_mx_plan.activatable', true)
            ->assertJsonPath('mail.send_window_start', '08:00');

        $this->putJson('/api/settings/mail', array_replace_recursive($payload, [
            'sender_name' => 'AEGIS Delivery',
            'mailbox_password' => null,
            'send_enabled' => false,
        ]))->assertOk()
            ->assertJsonPath('mail.sender_name', 'AEGIS Delivery')
            ->assertJsonPath('mail.active_provider', 'ovh_mx_plan')
            ->assertJsonPath('mail.send_enabled', false)
            ->assertJsonPath('mail.providers.ovh_mx_plan.activatable', true)
            ->assertJsonPath('mail.providers.ovh_mx_plan.ready', false);

        $this->assertDatabaseCount('mailbox_accounts', 1);
        $this->assertDatabaseCount('smtp_provider_accounts', 0);
        $this->assertDatabaseHas('mailbox_accounts', [
            'provider' => 'ovh_mx_plan',
            'email' => 'ops@aegis-mail.test',
            'display_name' => 'AEGIS Delivery',
            'imap_host' => 'imap.mail.ovh.net',
            'smtp_host' => 'smtp.mail.ovh.net',
            'sync_enabled' => 1,
            'send_enabled' => 0,
        ]);
        $this->assertDatabaseHas('settings', ['key' => 'mail']);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'settings.mail.updated']);

        $mailbox = MailboxAccount::query()->firstOrFail();

        $this->assertNotSame('super-secret-password', $mailbox->getRawOriginal('password_encrypted'));
        $this->assertSame('super-secret-password', $mailbox->password_encrypted);
    }

    public function test_it_stores_smtp2go_separately_and_can_activate_it(): void
    {
        $payload = $this->validMailPayload([
            'active_provider' => 'smtp2go',
            'providers' => [
                'smtp2go' => [
                    'smtp_host' => 'mail.smtp2go.com',
                    'smtp_port' => 2525,
                    'smtp_secure' => false,
                    'smtp_username' => 'smtp2go-user',
                    'smtp_password' => 'smtp2go-secret',
                    'send_enabled' => true,
                ],
            ],
        ]);

        $this->putJson('/api/settings/mail', $payload)
            ->assertOk()
            ->assertJsonPath('mail.active_provider', 'smtp2go')
            ->assertJsonPath('mail.active_provider_label', 'SMTP2GO')
            ->assertJsonPath('mail.providers.ovh_mx_plan.smtp_username', 'ops@aegis-mail.test')
            ->assertJsonPath('mail.providers.smtp2go.smtp_host', 'mail.smtp2go.com')
            ->assertJsonPath('mail.providers.smtp2go.smtp_port', 2525)
            ->assertJsonPath('mail.providers.smtp2go.smtp_username', 'smtp2go-user')
            ->assertJsonPath('mail.providers.smtp2go.smtp_password_configured', true)
            ->assertJsonPath('mail.providers.smtp2go.activatable', true)
            ->assertJsonPath('mail.providers.smtp2go.ready', true);

        $this->assertDatabaseCount('mailbox_accounts', 1);
        $this->assertDatabaseCount('smtp_provider_accounts', 1);
        $this->assertDatabaseHas('smtp_provider_accounts', [
            'provider' => 'smtp2go',
            'username' => 'smtp2go-user',
            'smtp_host' => 'mail.smtp2go.com',
            'smtp_port' => 2525,
            'smtp_secure' => 0,
            'send_enabled' => 1,
        ]);

        $account = SmtpProviderAccount::query()->where('provider', 'smtp2go')->firstOrFail();
        $this->assertNotSame('smtp2go-secret', $account->getRawOriginal('password_encrypted'));
        $this->assertSame('smtp2go-secret', $account->password_encrypted);
    }

    public function test_it_blocks_activation_of_an_incomplete_provider(): void
    {
        $response = $this->putJson('/api/settings/mail', $this->validMailPayload([
            'active_provider' => 'smtp2go',
            'providers' => [
                'smtp2go' => [
                    'smtp_host' => 'mail.smtp2go.com',
                    'smtp_port' => 587,
                    'smtp_secure' => false,
                    'smtp_username' => null,
                    'smtp_password' => null,
                    'send_enabled' => true,
                ],
            ],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['active_provider']);

        $this->assertStringContainsString('Impossible d’activer SMTP2GO', $response->json('message'));
        $this->assertDatabaseCount('smtp_provider_accounts', 0);
    }

    public function test_mail_settings_preserve_existing_signature_when_standard_save_sends_null_fields(): void
    {
        $payload = $this->validMailPayload();

        $this->putJson('/api/settings/mail', $payload)->assertOk();

        $this->putJson('/api/settings/mail', array_replace_recursive($payload, [
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

        $this->putJson('/api/settings/mail', array_replace_recursive($payload, [
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
            'active_provider' => 'unknown',
            'sender_email' => 'invalid-email',
            'sender_name' => '',
            'mailbox_username' => '',
            'imap_host' => '',
            'imap_port' => 70000,
            'imap_secure' => 'nope',
            'sync_enabled' => 'nope',
            'send_enabled' => 'nope',
            'send_window_start' => '25:00',
            'send_window_end' => 'bad',
            'providers' => [
                'ovh_mx_plan' => [
                    'smtp_host' => '',
                    'smtp_port' => 0,
                    'smtp_secure' => 'nope',
                ],
                'smtp2go' => [
                    'send_enabled' => 'nope',
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'active_provider',
                'sender_email',
                'sender_name',
                'mailbox_username',
                'imap_host',
                'imap_port',
                'imap_secure',
                'sync_enabled',
                'send_enabled',
                'send_window_start',
                'send_window_end',
                'providers.ovh_mx_plan.smtp_host',
                'providers.ovh_mx_plan.smtp_port',
                'providers.ovh_mx_plan.smtp_secure',
                'providers.smtp2go.send_enabled',
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
            'public_base_url' => 'https://mail.example.com',
            'tracking_base_url' => 'https://track.example.com',
        ])->assertOk()
            ->assertJsonPath('deliverability.tracking_clicks_enabled', false)
            ->assertJsonPath('deliverability.publicBaseUrl', 'https://mail.example.com')
            ->assertJsonPath('deliverability.trackingBaseUrl', 'https://track.example.com');

        $this->assertDatabaseHas('settings', ['key' => 'general']);
        $this->assertDatabaseHas('settings', ['key' => 'deliverability']);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'settings.general.updated']);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'settings.deliverability.updated']);
    }

    public function test_it_runs_imap_and_smtp_tests_against_the_stub_gateway(): void
    {
        $this->putJson('/api/settings/mail', $this->validMailPayload())->assertOk();

        $this->postJson('/api/settings/mail/test-imap', [
            'provider' => 'ovh_mx_plan',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('protocol', 'imap')
            ->assertJsonPath('provider', 'ovh_mx_plan')
            ->assertJsonPath('driver', 'stub')
            ->assertJsonPath('message', 'Test IMAP réussi. La connexion OVH MX Plan a bien été établie.');

        $this->postJson('/api/settings/mail/test-smtp', [
            'provider' => 'ovh_mx_plan',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('protocol', 'smtp')
            ->assertJsonPath('provider', 'ovh_mx_plan')
            ->assertJsonPath('driver', 'stub')
            ->assertJsonPath('message', 'Test SMTP réussi. La connexion OVH MX Plan a bien été établie.');

        $this->assertDatabaseHas('mail_events', ['event_type' => 'mailbox.test_imap_succeeded']);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'mailbox.test_smtp_succeeded']);
        $this->assertDatabaseHas('mailbox_accounts', ['health_status' => 'healthy']);
    }

    public function test_it_rejects_imap_tests_for_providers_without_imap_support(): void
    {
        $response = $this->postJson('/api/settings/mail/test-imap', [
            'provider' => 'smtp2go',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provider']);

        $this->assertStringContainsString('ne supporte pas l’IMAP', $response->json('message'));
    }

    public function test_imap_test_returns_french_operator_message_for_invalid_host(): void
    {
        $response = $this->postJson('/api/settings/mail/test-imap', [
            'provider' => 'ovh_mx_plan',
            'sender_email' => 'ops@aegis-mail.test',
            'mailbox_username' => 'ops@aegis-mail.test',
            'mailbox_password' => 'secret-pass',
            'imap_host' => 'invalid.mail.ovh.net',
            'imap_port' => 993,
            'imap_secure' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('protocol', 'imap')
            ->assertJsonPath('provider', 'ovh_mx_plan')
            ->assertJsonPath('message', 'La connexion IMAP a échoué : l’hôte ou le port semble incorrect.');
    }

    public function test_smtp_test_returns_precise_validation_messages_for_missing_fields(): void
    {
        $response = $this->postJson('/api/settings/mail/test-smtp', [
            'provider' => 'smtp2go',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sender_email',
                'smtp_username',
                'smtp_password',
            ]);

        $this->assertStringContainsString('L’adresse d’envoi est requise pour tester la connexion SMTP SMTP2GO.', $response->json('message'));
        $this->assertStringContainsString('L’identifiant SMTP est requis pour tester la connexion SMTP SMTP2GO.', $response->json('message'));
        $this->assertStringContainsString('Le mot de passe SMTP est requis pour tester la connexion SMTP SMTP2GO.', $response->json('message'));
    }

    public function test_imap_test_returns_precise_validation_messages_for_missing_fields(): void
    {
        $response = $this->postJson('/api/settings/mail/test-imap', [
            'provider' => 'ovh_mx_plan',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sender_email',
                'mailbox_username',
                'mailbox_password',
                'imap_host',
                'imap_port',
                'imap_secure',
            ]);

        $this->assertStringContainsString('L’adresse d’envoi est requise pour tester la connexion IMAP OVH MX Plan.', $response->json('message'));
        $this->assertStringContainsString('L’hôte IMAP est requis pour tester la connexion IMAP OVH MX Plan.', $response->json('message'));
    }

    public function test_smtp_and_imap_tests_can_run_from_unsaved_overrides_only(): void
    {
        $smtpPayload = [
            'provider' => 'smtp2go',
            'sender_email' => 'ops@aegis-mail.test',
            'smtp_host' => 'mail.smtp2go.com',
            'smtp_port' => 587,
            'smtp_secure' => false,
            'smtp_username' => 'smtp2go-user',
            'smtp_password' => 'secret-pass',
        ];

        $imapPayload = [
            'provider' => 'ovh_mx_plan',
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
            ->assertJsonPath('protocol', 'smtp')
            ->assertJsonPath('provider', 'smtp2go');

        $this->postJson('/api/settings/mail/test-imap', $imapPayload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('protocol', 'imap')
            ->assertJsonPath('provider', 'ovh_mx_plan');

        $this->assertDatabaseMissing('mailbox_accounts', ['provider' => 'ovh_mx_plan']);
        $this->assertDatabaseMissing('smtp_provider_accounts', ['provider' => 'smtp2go']);
    }

    public function test_smtp_test_does_not_fallback_to_ovh_credentials_when_smtp2go_is_selected(): void
    {
        $this->putJson('/api/settings/mail', $this->validMailPayload())->assertOk();

        $response = $this->postJson('/api/settings/mail/test-smtp', [
            'provider' => 'smtp2go',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'smtp_username',
                'smtp_password',
            ]);

        $this->assertStringContainsString('SMTP2GO', $response->json('message'));
    }

    private function validMailPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'active_provider' => 'ovh_mx_plan',
            'sender_email' => 'ops@aegis-mail.test',
            'sender_name' => 'AEGIS Ops',
            'global_signature_html' => '<p>Cordialement,<br>AEGIS</p>',
            'global_signature_text' => "Cordialement,\nAEGIS",
            'mailbox_username' => 'ops@aegis-mail.test',
            'mailbox_password' => 'super-secret-password',
            'imap_host' => 'imap.mail.ovh.net',
            'imap_port' => 993,
            'imap_secure' => true,
            'sync_enabled' => true,
            'send_enabled' => true,
            'send_window_start' => '08:00',
            'send_window_end' => '18:00',
            'providers' => [
                'ovh_mx_plan' => [
                    'smtp_host' => 'smtp.mail.ovh.net',
                    'smtp_port' => 465,
                    'smtp_secure' => true,
                ],
                'smtp2go' => [
                    'smtp_host' => null,
                    'smtp_port' => null,
                    'smtp_secure' => false,
                    'smtp_username' => null,
                    'smtp_password' => null,
                    'send_enabled' => true,
                ],
            ],
        ], $overrides);
    }
}
