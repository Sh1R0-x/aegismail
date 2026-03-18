<?php

namespace Tests\Feature;

use App\Models\MailboxAccount;
use App\Models\Setting;
use App\Services\Mailing\DeliverabilityDomainCheckService;
use App\Services\Mailing\MailboxSettingsService;
use App\Services\Mailing\MailEventLogger;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DeliverabilityChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_refresh_retests_spf_dkim_and_dmarc_with_logs_and_persisted_results(): void
    {
        $this->seedMailConfiguration();

        $service = Mockery::mock(DeliverabilityDomainCheckService::class, [
            app(SettingsStore::class),
            app(MailboxSettingsService::class),
            app(MailEventLogger::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('lookupTxtRecords')
            ->andReturnUsing(function (string $host): array {
                return match ($host) {
                    'aegis.test' => [
                        'records' => ['v=spf1 include:mx.ovh.com ~all'],
                        'logs' => [[
                            'level' => 'info',
                            'message' => 'lookup',
                            'context' => ['host' => $host],
                            'recordedAt' => now()->toIso8601String(),
                        ]],
                    ],
                    'selector1._domainkey.aegis.test' => [
                        'records' => ['v=DKIM1; k=rsa; p=abc123'],
                        'logs' => [[
                            'level' => 'info',
                            'message' => 'lookup',
                            'context' => ['host' => $host],
                            'recordedAt' => now()->toIso8601String(),
                        ]],
                    ],
                    '_dmarc.aegis.test' => [
                        'records' => ['v=DMARC1; p=quarantine; rua=mailto:dmarc@aegis.test'],
                        'logs' => [[
                            'level' => 'info',
                            'message' => 'lookup',
                            'context' => ['host' => $host],
                            'recordedAt' => now()->toIso8601String(),
                        ]],
                    ],
                    default => [
                        'records' => [],
                        'logs' => [[
                            'level' => 'info',
                            'message' => 'lookup',
                            'context' => ['host' => $host],
                            'recordedAt' => now()->toIso8601String(),
                        ]],
                    ],
                };
            });

        $this->app->instance(DeliverabilityDomainCheckService::class, $service);

        $response = $this->postJson('/api/settings/deliverability/checks/refresh', [
            'mechanisms' => ['spf', 'dkim', 'dmarc'],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Vérifications de délivrabilité relancées.')
            ->assertJsonPath('deliverability.domain', 'aegis.test')
            ->assertJsonPath('deliverability.checks.spf.status', 'pass')
            ->assertJsonPath('deliverability.checks.spf.detected_value', 'v=spf1 include:mx.ovh.com ~all')
            ->assertJsonPath('deliverability.checks.dkim.status', 'pass')
            ->assertJsonPath('deliverability.checks.dmarc.status', 'pass')
            ->assertJsonPath('deliverability.checks.spf.logs.0.context.host', 'aegis.test')
            ->assertJsonPath('deliverability.refreshEndpoint', '/api/settings/deliverability/checks/refresh');

        $stored = Setting::query()->where('key', 'deliverability')->firstOrFail();

        $this->assertSame('pass', $stored->value_json['checks']['spf']['status'] ?? null);
        $this->assertSame('pass', $stored->value_json['checks']['dkim']['status'] ?? null);
        $this->assertSame('pass', $stored->value_json['checks']['dmarc']['status'] ?? null);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'settings.deliverability.checks_refreshed']);
    }

    public function test_refresh_returns_not_detected_when_no_sender_domain_is_configured(): void
    {
        $this->postJson('/api/settings/deliverability/checks/refresh')
            ->assertOk()
            ->assertJsonPath('deliverability.checks.spf.status', 'not_detected')
            ->assertJsonPath('deliverability.checks.dkim.status', 'not_detected')
            ->assertJsonPath('deliverability.checks.dmarc.status', 'not_detected');
    }

    private function seedMailConfiguration(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'deliverability'],
            ['value_json' => array_replace(config('mailing.defaults.deliverability', []), [
                'dkim_selectors' => ['selector1'],
            ])],
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
    }
}
