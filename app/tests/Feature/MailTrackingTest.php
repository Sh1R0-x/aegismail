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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MailTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_outbound_queue_injects_open_pixel_and_rewrites_links_before_dispatch(): void
    {
        config(['app.url' => 'https://mail.example.com']);

        [$contact, $primaryEmail] = $this->seedContacts();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Tracking injection',
            'html_body' => '<p>Bonjour <a href="https://www.example.com/html-offer">offre HTML</a></p>',
            'text_body' => 'Version texte https://www.example.com/text-offer',
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
            'scheduledAt' => Carbon::parse('2026-03-20 09:00:00')->subMinute()->toDateTimeString(),
        ])->assertOk();

        $message = MailMessage::query()->firstOrFail();

        $this->assertStringContainsString('/t/o/'.$message->aegis_tracking_id.'.gif', (string) $message->html_body);
        $this->assertStringContainsString('/t/c/'.$message->aegis_tracking_id.'.1.', (string) $message->html_body);
        $this->assertStringContainsString('/t/c/'.$message->aegis_tracking_id.'.2.', (string) $message->text_body);
        $this->assertSame('https://www.example.com/html-offer', data_get($message->headers_json, 'tracking.clicks.0.url'));
        $this->assertSame('https://www.example.com/text-offer', data_get($message->headers_json, 'tracking.clicks.1.url'));
        $this->assertSame(
            'https://track.example.com/t/o/'.$message->aegis_tracking_id.'.gif',
            (string) data_get($message->headers_json, 'tracking.open.url')
        );
        $this->assertStringNotContainsString('localhost', (string) $message->html_body);
        $this->assertStringNotContainsString('127.0.0.1', (string) $message->html_body);
        $this->assertStringNotContainsString('localhost', (string) $message->text_body);
        $this->assertStringNotContainsString('127.0.0.1', (string) $message->text_body);
    }

    public function test_open_and_click_tracking_routes_update_message_and_recipient_state(): void
    {
        config(['app.url' => 'https://mail.example.com']);
        Carbon::setTestNow('2026-03-20 10:00:00');

        [$contact, $primaryEmail] = $this->seedContacts();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Tracking state',
            'html_body' => '<p><a href="https://www.example.com/proposal">Voir la proposition</a></p>',
            'text_body' => 'Voir la proposition',
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

        $message = MailMessage::query()->firstOrFail();
        $recipient = MailRecipient::query()->firstOrFail();
        $clickToken = data_get($message->headers_json, 'tracking.clicks.0.token');

        $this->get(route('mailings.track.open', ['token' => $message->aegis_tracking_id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        Carbon::setTestNow('2026-03-20 10:05:00');

        $this->get(route('mailings.track.click', ['token' => $clickToken]))
            ->assertRedirect('https://www.example.com/proposal');

        $message->refresh();
        $recipient->refresh();

        $this->assertNotNull($message->opened_first_at);
        $this->assertNotNull($message->clicked_first_at);
        $this->assertSame('clicked', $recipient->status);
        $this->assertSame('interested', $recipient->score_bucket);
        $this->assertSame('2026-03-20 10:05:00', $recipient->last_event_at?->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('mail_events', ['event_type' => 'mail_message.opened']);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'mail_message.clicked']);

        $this->get('/activity')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Activity/Index')
                ->has('events', 3)
                ->has('events.0', fn (AssertableInertia $event) => $event
                    ->where('status', 'clicked')
                    ->where('direction', 'outbound')
                    ->etc()
                )
                ->where('events', fn ($events) => collect($events)->contains(
                    fn (array $event) => ($event['status'] ?? null) === 'opened' && ($event['direction'] ?? null) === 'outbound'
                ))
            );
    }

    public function test_tracking_routes_do_not_persist_events_when_tracking_is_disabled(): void
    {
        config(['app.url' => 'https://mail.example.com']);
        [$contact, $primaryEmail] = $this->seedContacts();

        Setting::query()->updateOrCreate(
            ['key' => 'deliverability'],
            ['value_json' => array_replace(config('mailing.defaults.deliverability', []), [
                'tracking_opens_enabled' => false,
                'tracking_clicks_enabled' => false,
                'public_base_url' => 'https://mail.example.com',
                'tracking_base_url' => 'https://track.example.com',
            ])],
        );

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Tracking disabled',
            'html_body' => '<p><a href="https://www.example.com/proposal">Voir</a></p>',
            'text_body' => 'Voir https://www.example.com/proposal',
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

        $message = MailMessage::query()->firstOrFail();
        $recipient = MailRecipient::query()->firstOrFail();

        $this->assertNull(data_get($message->headers_json, 'tracking.open'));
        $this->assertSame([], data_get($message->headers_json, 'tracking.clicks', []));

        $this->get(route('mailings.track.open', ['token' => $message->aegis_tracking_id]))
            ->assertOk();

        $this->get(route('mailings.track.click', ['token' => $message->aegis_tracking_id.'.1.deadbeefdeadbeefdeadbeefdeadbeef']))
            ->assertNotFound();

        $this->assertNull($message->fresh()->opened_first_at);
        $this->assertNull($message->fresh()->clicked_first_at);
        $this->assertSame('sent', $recipient->fresh()->status);
        $this->assertDatabaseMissing('mail_events', ['event_type' => 'mail_message.opened']);
        $this->assertDatabaseMissing('mail_events', ['event_type' => 'mail_message.clicked']);
    }

    public function test_invalid_click_token_returns_not_found_without_side_effects(): void
    {
        config(['app.url' => 'https://mail.example.com']);
        [$contact, $primaryEmail] = $this->seedContacts();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Invalid token',
            'html_body' => '<p><a href="https://www.example.com/proposal">Voir</a></p>',
            'text_body' => 'Voir',
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

        $message = MailMessage::query()->firstOrFail();

        $this->get(route('mailings.track.click', ['token' => $message->aegis_tracking_id.'.1.deadbeefdeadbeefdeadbeefdeadbeef']))
            ->assertNotFound();

        $this->assertNull($message->fresh()->clicked_first_at);
        $this->assertDatabaseMissing('mail_events', ['event_type' => 'mail_message.clicked']);
    }

    private function seedMailboxAndSettings(): void
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
                'global_signature_html' => '<p>Cordialement,<br>AEGIS</p>',
                'global_signature_text' => "Cordialement,\nAEGIS",
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
                'public_base_url' => 'https://mail.example.com',
                'tracking_base_url' => 'https://track.example.com',
            ])],
        );
    }

    private function seedContacts(): array
    {
        $this->seedMailboxAndSettings();

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
}
