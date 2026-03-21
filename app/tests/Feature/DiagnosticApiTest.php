<?php

namespace Tests\Feature;

use App\Models\MailboxAccount;
use App\Models\MailCampaign;
use App\Models\MailEvent;
use App\Models\MailRecipient;
use App\Services\Mailing\Contracts\MailGatewayClient;
use App\Services\Mailing\Gateway\StubMailGatewayClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DiagnosticApiTest extends TestCase
{
    use RefreshDatabase;

    // ── SMTP Test Diagnostic Enrichment ──────────────────────

    public function test_smtp_failure_returns_diagnostic_details(): void
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
            ->assertJsonStructure([
                'success',
                'protocol',
                'provider',
                'provider_label',
                'message',
                'tested_host',
                'tested_port',
                'tested_secure',
                'tested_at',
                'failure_stage',
            ]);

        $this->assertSame('invalid.mail.ovh.net', $response->json('tested_host'));
        $this->assertSame(993, $response->json('tested_port'));
        $this->assertTrue($response->json('tested_secure'));
        $this->assertSame('dns', $response->json('failure_stage'));
        $this->assertNotNull($response->json('tested_at'));
    }

    public function test_smtp_success_returns_diagnostic_details_without_failure_stage(): void
    {
        $response = $this->postJson('/api/settings/mail/test-smtp', [
            'provider' => 'ovh_mx_plan',
            'sender_email' => 'ops@aegis-mail.test',
            'mailbox_username' => 'ops@aegis-mail.test',
            'mailbox_password' => 'secret-pass',
            'smtp_host' => 'smtp.mail.ovh.net',
            'smtp_port' => 465,
            'smtp_secure' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('protocol', 'smtp')
            ->assertJsonPath('tested_host', 'smtp.mail.ovh.net')
            ->assertJsonPath('tested_port', 465)
            ->assertJsonPath('tested_secure', true)
            ->assertJsonPath('failure_stage', null)
            ->assertJsonPath('technical_detail', null);

        $this->assertNotNull($response->json('tested_at'));
    }

    public function test_diagnostic_response_never_exposes_secrets(): void
    {
        $response = $this->postJson('/api/settings/mail/test-smtp', [
            'provider' => 'ovh_mx_plan',
            'sender_email' => 'ops@aegis-mail.test',
            'mailbox_username' => 'ops@aegis-mail.test',
            'mailbox_password' => 'my-super-secret-password-123',
            'smtp_host' => 'smtp.mail.ovh.net',
            'smtp_port' => 465,
            'smtp_secure' => true,
        ]);

        $response->assertOk();
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('my-super-secret-password-123', $json);
    }

    public function test_smtp2go_test_returns_separate_diagnostics_from_ovh(): void
    {
        $response = $this->postJson('/api/settings/mail/test-smtp', [
            'provider' => 'smtp2go',
            'sender_email' => 'ops@aegis-mail.test',
            'smtp_host' => 'mail.smtp2go.com',
            'smtp_port' => 587,
            'smtp_secure' => false,
            'smtp_username' => 'smtp2go-user',
            'smtp_password' => 'smtp2go-secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('provider', 'smtp2go')
            ->assertJsonPath('provider_label', 'SMTP2GO')
            ->assertJsonPath('tested_host', 'mail.smtp2go.com')
            ->assertJsonPath('tested_port', 587)
            ->assertJsonPath('tested_secure', false);
    }

    public function test_gateway_exception_returns_gateway_failure_stage(): void
    {
        $this->app->bind(MailGatewayClient::class, fn () => new class implements MailGatewayClient
        {
            public function testImap(array $configuration): array
            {
                throw new \RuntimeException('Connection refused');
            }

            public function testSmtp(array $configuration): array
            {
                throw new \RuntimeException('Connection refused');
            }

            public function dispatchMessage(array $payload): array
            {
                return ['success' => true, 'driver' => 'test'];
            }

            public function syncMailbox(array $payload): array
            {
                return ['success' => true, 'driver' => 'test'];
            }
        });

        $response = $this->postJson('/api/settings/mail/test-smtp', [
            'provider' => 'ovh_mx_plan',
            'sender_email' => 'ops@aegis-mail.test',
            'mailbox_username' => 'ops@aegis-mail.test',
            'mailbox_password' => 'secret-pass',
            'smtp_host' => 'smtp.mail.ovh.net',
            'smtp_port' => 465,
            'smtp_secure' => true,
        ]);

        $response->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('failure_stage', 'gateway')
            ->assertJsonPath('tested_host', 'smtp.mail.ovh.net')
            ->assertJsonPath('tested_port', 465);
    }

    // ── Diagnostic Events API ──────────────────────────────

    public function test_diagnostic_events_returns_paginated_events(): void
    {
        MailEvent::query()->create([
            'event_type' => 'test.event',
            'event_payload' => ['foo' => 'bar'],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/diagnostic/events');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'event_type', 'event_payload', 'occurred_at']],
                'current_page',
                'last_page',
                'total',
            ]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_diagnostic_events_filter_by_type(): void
    {
        MailEvent::query()->create([
            'event_type' => 'mailbox.test_smtp_succeeded',
            'event_payload' => [],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        MailEvent::query()->create([
            'event_type' => 'mailbox.test_smtp_failed',
            'event_payload' => [],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/diagnostic/events?event_type=mailbox.test_smtp_failed');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('mailbox.test_smtp_failed', $response->json('data.0.event_type'));
    }

    public function test_diagnostic_events_filter_by_campaign_id(): void
    {
        $mailbox = MailboxAccount::query()->create([
            'provider' => 'ovh_mx_plan',
            'email' => 'ops@aegis-mail.test',
            'display_name' => 'AEGIS',
            'username' => 'ops@aegis-mail.test',
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

        $campaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'outbound_provider' => 'ovh_mx_plan',
            'name' => 'Test campaign',
            'mode' => 'campaign',
            'status' => 'draft',
            'last_edited_at' => now(),
        ]);

        MailEvent::query()->create([
            'event_type' => 'campaign.started',
            'event_payload' => [],
            'campaign_id' => $campaign->id,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        MailEvent::query()->create([
            'event_type' => 'other.event',
            'event_payload' => [],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/diagnostic/events?campaign_id={$campaign->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_diagnostic_events_scrub_secrets(): void
    {
        MailEvent::query()->create([
            'event_type' => 'mailbox.test_smtp_succeeded',
            'event_payload' => [
                'host' => 'smtp.mail.ovh.net',
                'password' => 'super-secret',
                'password_encrypted' => 'encrypted-val',
                'api_key' => 'key-123',
            ],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/diagnostic/events');

        $response->assertOk();
        $payload = $response->json('data.0.event_payload');
        $this->assertSame('smtp.mail.ovh.net', $payload['host']);
        $this->assertSame('[REDACTED]', $payload['password']);
        $this->assertSame('[REDACTED]', $payload['password_encrypted']);
        $this->assertSame('[REDACTED]', $payload['api_key']);
    }

    public function test_diagnostic_event_types_returns_grouped_counts(): void
    {
        MailEvent::query()->create([
            'event_type' => 'mailbox.test_smtp_succeeded',
            'event_payload' => [],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        MailEvent::query()->create([
            'event_type' => 'mailbox.test_smtp_succeeded',
            'event_payload' => [],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        MailEvent::query()->create([
            'event_type' => 'settings.mail.updated',
            'event_payload' => [],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/diagnostic/event-types');

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(2, $data['mailbox.test_smtp_succeeded']);
        $this->assertSame(1, $data['settings.mail.updated']);
    }

    // ── Health Check ──────────────────────────────────────

    public function test_health_endpoint_returns_system_status(): void
    {
        $response = $this->getJson('/api/diagnostic/health');

        $response->assertOk()
            ->assertJsonStructure([
                'gateway_driver',
                'mailbox_configured',
                'mailbox_health_status',
                'providers',
                'queue' => ['queued', 'sending', 'stuck'],
                'errors_last_24h',
                'last_event_at',
            ]);

        $this->assertSame('stub', $response->json('gateway_driver'));
        $this->assertFalse($response->json('mailbox_configured'));
    }

    public function test_health_shows_provider_status(): void
    {
        $mailbox = MailboxAccount::query()->create([
            'provider' => 'ovh_mx_plan',
            'email' => 'ops@aegis-mail.test',
            'display_name' => 'AEGIS',
            'username' => 'ops@aegis-mail.test',
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
            'health_message' => 'OK',
        ]);

        $response = $this->getJson('/api/diagnostic/health');

        $response->assertOk()
            ->assertJsonPath('mailbox_configured', true)
            ->assertJsonPath('mailbox_health_status', 'healthy');

        $providers = $response->json('providers');
        $ovhProvider = collect($providers)->firstWhere('provider', 'ovh_mx_plan');
        $this->assertSame('healthy', $ovhProvider['health_status']);
    }

    // ── Stuck Detection ──────────────────────────────────

    public function test_stuck_recipients_detected_after_threshold(): void
    {
        $mailbox = MailboxAccount::query()->create([
            'provider' => 'ovh_mx_plan',
            'email' => 'ops@aegis-mail.test',
            'display_name' => 'AEGIS',
            'username' => 'ops@aegis-mail.test',
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

        $campaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'outbound_provider' => 'ovh_mx_plan',
            'name' => 'Stuck test',
            'mode' => 'campaign',
            'status' => 'sending',
            'last_edited_at' => now(),
        ]);

        // Stuck recipient: scheduled 2 hours ago, still queued
        MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'email' => 'stuck@example.com',
            'status' => 'queued',
            'scheduled_for' => CarbonImmutable::now()->subHours(2),
        ]);

        // Fresh recipient: scheduled 5 minutes ago, not stuck yet
        MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'email' => 'fresh@example.com',
            'status' => 'queued',
            'scheduled_for' => CarbonImmutable::now()->subMinutes(5),
        ]);

        $healthResponse = $this->getJson('/api/diagnostic/health');
        $healthResponse->assertOk();
        $this->assertSame(1, $healthResponse->json('queue.stuck'));

        $stuckResponse = $this->getJson('/api/diagnostic/stuck-recipients');
        $stuckResponse->assertOk();
        $this->assertCount(1, $stuckResponse->json('data'));
        $this->assertSame('stuck@example.com', $stuckResponse->json('data.0.email'));
    }

    public function test_sending_status_also_detected_as_stuck_after_threshold(): void
    {
        $mailbox = MailboxAccount::query()->create([
            'provider' => 'ovh_mx_plan',
            'email' => 'ops@aegis-mail.test',
            'display_name' => 'AEGIS',
            'username' => 'ops@aegis-mail.test',
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

        $campaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'outbound_provider' => 'ovh_mx_plan',
            'name' => 'Stuck sending',
            'mode' => 'campaign',
            'status' => 'sending',
            'last_edited_at' => now(),
        ]);

        Carbon::setTestNow(CarbonImmutable::now());

        MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'email' => 'stuck-sending@example.com',
            'status' => 'sending',
            'scheduled_for' => CarbonImmutable::now()->subMinutes(60),
        ]);

        $response = $this->getJson('/api/diagnostic/health');
        $response->assertOk();
        $this->assertSame(1, $response->json('queue.stuck'));
    }

    // ── Frontend Payload Coherence ──────────────────────────

    public function test_smtp_test_diagnostic_payload_matches_frontend_contract(): void
    {
        $response = $this->postJson('/api/settings/mail/test-smtp', [
            'provider' => 'ovh_mx_plan',
            'sender_email' => 'ops@aegis-mail.test',
            'mailbox_username' => 'ops@aegis-mail.test',
            'mailbox_password' => 'secret-pass',
            'smtp_host' => 'smtp.mail.ovh.net',
            'smtp_port' => 465,
            'smtp_secure' => true,
        ]);

        $response->assertOk();

        // Verify all expected diagnostic fields are present
        $data = $response->json();
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('protocol', $data);
        $this->assertArrayHasKey('provider', $data);
        $this->assertArrayHasKey('provider_label', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('tested_host', $data);
        $this->assertArrayHasKey('tested_port', $data);
        $this->assertArrayHasKey('tested_secure', $data);
        $this->assertArrayHasKey('tested_at', $data);
        $this->assertArrayHasKey('failure_stage', $data);
        $this->assertArrayHasKey('technical_detail', $data);

        // Verify correct types
        $this->assertIsBool($data['success']);
        $this->assertIsString($data['protocol']);
        $this->assertIsString($data['provider']);
        $this->assertIsString($data['provider_label']);
        $this->assertIsString($data['message']);
        $this->assertIsString($data['tested_host']);
        $this->assertIsInt($data['tested_port']);
        $this->assertIsBool($data['tested_secure']);
        $this->assertIsString($data['tested_at']);

        // status_code must NOT be in response body (stripped by controller)
        $this->assertArrayNotHasKey('status_code', $data);
    }

    // ── Timestamp serialization coherence ──────────────────

    public function test_diagnostic_events_dates_use_iso8601_with_offset(): void
    {
        MailEvent::query()->create([
            'event_type' => 'test.timestamp',
            'event_payload' => [],
            'occurred_at' => CarbonImmutable::parse('2026-03-21 10:38:11', config('app.timezone')),
            'created_at' => CarbonImmutable::now(),
        ]);

        $response = $this->getJson('/api/diagnostic/events');

        $response->assertOk();
        $occurredAt = $response->json('data.0.occurred_at');

        // Must contain offset like +01:00 or +02:00, NOT end with Z
        $this->assertStringNotContainsString('.000000Z', $occurredAt);
        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', $occurredAt);

        // Must represent the correct Paris time
        $this->assertStringContainsString('10:38:11', $occurredAt);
    }

    public function test_health_last_event_at_uses_iso8601_with_offset(): void
    {
        MailEvent::query()->create([
            'event_type' => 'test.health',
            'event_payload' => [],
            'occurred_at' => CarbonImmutable::parse('2026-03-21 10:38:11', config('app.timezone')),
            'created_at' => CarbonImmutable::now(),
        ]);

        $response = $this->getJson('/api/diagnostic/health');

        $response->assertOk();
        $lastEventAt = $response->json('last_event_at');

        $this->assertNotNull($lastEventAt);
        $this->assertStringNotContainsString('.000000Z', $lastEventAt);
        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', $lastEventAt);
    }
}
