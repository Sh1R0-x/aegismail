<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailboxAccount;
use App\Models\MailDraft;
use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\Mailing\Contracts\MailGatewayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use stdClass;
use Tests\TestCase;

class OutboundEmailQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_bulk_html_only_dispatch_uses_public_urls_generates_text_and_unsubscribe_headers(): void
    {
        config(['app.url' => 'http://127.0.0.1:8001']);
        Carbon::setTestNow('2026-03-20 10:00:00');

        [$contact, $primaryEmail] = $this->seedContacts();
        $capture = $this->captureGateway();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'bulk',
            'subject' => 'Outbound quality',
            'html_body' => '<p><a href="/offer">Voir l’offre</a></p>',
            'text_body' => null,
            'payload_json' => [
                'recipients' => [[
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'email' => $primaryEmail->email,
                ]],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/schedule', [
            'scheduledAt' => now()->subMinute()->toDateTimeString(),
        ])->assertOk()
            ->assertJsonPath('preflight.hasTextVersion', true);

        $message = MailMessage::query()->firstOrFail();
        $serializedHeaders = json_encode($message->headers_json, JSON_THROW_ON_ERROR);

        $this->assertSame('https://mail.example.com/offer', data_get($message->headers_json, 'tracking.clicks.0.url'));
        $this->assertStringContainsString('https://mail.example.com/assets/logo.png', (string) $message->html_body);
        $this->assertStringContainsString('https://track.example.com/t/o/'.$message->aegis_tracking_id.'.gif', (string) $message->html_body);
        $this->assertStringContainsString('https://track.example.com/t/c/', (string) $message->text_body);
        $this->assertStringStartsWith('<https://track.example.com/u/', (string) ($capture->payload['headers_json']['List-Unsubscribe'] ?? ''));
        $this->assertSame('List-Unsubscribe=One-Click', $capture->payload['headers_json']['List-Unsubscribe-Post'] ?? null);

        foreach (['localhost', '127.0.0.1', '10.0.0.'] as $needle) {
            $this->assertStringNotContainsString($needle, (string) $message->html_body);
            $this->assertStringNotContainsString($needle, (string) $message->text_body);
            $this->assertStringNotContainsString($needle, $serializedHeaders);
        }
    }

    public function test_preflight_blocks_relative_urls_when_no_public_email_base_is_available(): void
    {
        config(['app.url' => 'http://127.0.0.1:8001']);

        [$contact, $primaryEmail] = $this->seedContacts(withPublicBases: false);

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Missing public base',
            'html_body' => '<p><a href="/offer">Voir l’offre</a></p>',
            'text_body' => null,
            'payload_json' => [
                'recipients' => [[
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'email' => $primaryEmail->email,
                ]],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/preflight')
            ->assertOk()
            ->assertJsonPath('preflight.ok', false)
            ->assertJsonFragment(['code' => 'link_requires_public_base'])
            ->assertJsonFragment(['code' => 'tracking_base_url_invalid']);
    }

    public function test_preflight_blocks_local_private_and_non_https_urls(): void
    {
        config(['app.url' => 'http://127.0.0.1:8001']);

        [$contact, $primaryEmail] = $this->seedContacts();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Invalid public URLs',
            'html_body' => '<p><a href="http://www.example.com/offer">Offre</a></p><p><a href="https://127.0.0.1:8001/private">Local</a></p><img src="http://cdn.example.com/logo.png" alt="Logo">',
            'text_body' => 'Version texte http://www.example.com/offer',
            'payload_json' => [
                'recipients' => [[
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'email' => $primaryEmail->email,
                ]],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/preflight')
            ->assertOk()
            ->assertJsonPath('preflight.ok', false)
            ->assertJsonFragment(['code' => 'link_not_https'])
            ->assertJsonFragment(['code' => 'link_not_public'])
            ->assertJsonFragment(['code' => 'image_not_https']);
    }

    public function test_unsubscribe_endpoint_marks_recipient_and_contact_email_as_opted_out(): void
    {
        config(['app.url' => 'http://127.0.0.1:8001']);
        Carbon::setTestNow('2026-03-20 10:00:00');

        [$contact, $primaryEmail] = $this->seedContacts();
        $this->captureGateway();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'bulk',
            'subject' => 'Unsubscribe ready',
            'html_body' => '<p><a href="https://www.example.com/offer">Voir l’offre</a></p>',
            'text_body' => 'Voir https://www.example.com/offer',
            'payload_json' => [
                'recipients' => [[
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'email' => $primaryEmail->email,
                ]],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/schedule', [
            'scheduledAt' => now()->subMinute()->toDateTimeString(),
        ])->assertOk();

        $recipient = MailRecipient::query()->firstOrFail();
        $unsubscribeUrl = trim((string) data_get(MailMessage::query()->firstOrFail()->headers_json, 'List-Unsubscribe', ''), '<>');
        $path = (string) parse_url($unsubscribeUrl, PHP_URL_PATH);

        $this->post($path)->assertOk()->assertSeeText('OK');

        $recipient->refresh();
        $primaryEmail->refresh();

        $this->assertSame('unsubscribed', $recipient->status);
        $this->assertNotNull($recipient->unsubscribe_at);
        $this->assertNotNull($primaryEmail->opt_out_at);
        $this->assertSame('one_click_unsubscribe', $primaryEmail->opt_out_reason);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'mail_recipient.unsubscribed']);
    }

    private function captureGateway(): stdClass
    {
        $capture = new stdClass;
        $capture->payload = [];

        $this->app->bind(MailGatewayClient::class, fn () => new class($capture) implements MailGatewayClient
        {
            public function __construct(private stdClass $capture) {}

            public function testImap(array $configuration): array
            {
                return ['success' => true, 'driver' => 'test', 'message' => 'ok'];
            }

            public function testSmtp(array $configuration): array
            {
                return ['success' => true, 'driver' => 'test', 'message' => 'ok'];
            }

            public function dispatchMessage(array $payload): array
            {
                $this->capture->payload = $payload;

                return [
                    'success' => true,
                    'driver' => 'test',
                    'message' => 'accepted',
                    'accepted_at' => Carbon::now()->toIso8601String(),
                    'message_id_header' => $payload['message_id_header'] ?? null,
                    'headers_json' => [
                        'Message-ID' => $payload['message_id_header'] ?? null,
                    ],
                ];
            }

            public function syncMailbox(array $payload): array
            {
                return ['success' => true, 'driver' => 'test', 'message' => 'ok', 'messages' => []];
            }
        });

        return $capture;
    }

    private function seedContacts(bool $withPublicBases = true): array
    {
        $this->seedMailboxAndSettings($withPublicBases);

        $organization = Organization::query()->create([
            'name' => 'Acme',
            'domain' => 'acme.test',
        ]);

        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
        ]);

        $primaryEmail = ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        return [$contact, $primaryEmail];
    }

    private function seedMailboxAndSettings(bool $withPublicBases = true): void
    {
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

        Setting::query()->updateOrCreate(
            ['key' => 'mail'],
            ['value_json' => [
                'global_signature_html' => '<p><img src="/assets/logo.png" alt="Logo AEGIS"></p>',
                'global_signature_text' => 'AEGIS',
                'send_window_start' => '09:00',
                'send_window_end' => '18:00',
            ]],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'general'],
            ['value_json' => config('mailing.defaults.general', [])],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'deliverability'],
            ['value_json' => array_replace(config('mailing.defaults.deliverability', []), [
                'tracking_opens_enabled' => true,
                'tracking_clicks_enabled' => true,
                'public_base_url' => $withPublicBases ? 'https://mail.example.com' : null,
                'tracking_base_url' => $withPublicBases ? 'https://track.example.com' : null,
            ])],
        );
    }
}
