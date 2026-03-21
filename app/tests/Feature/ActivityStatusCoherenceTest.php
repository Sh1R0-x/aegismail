<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailboxAccount;
use App\Models\MailCampaign;
use App\Models\MailDraft;
use App\Models\MailEvent;
use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Models\MailThread;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\Mailing\Inbound\MailboxActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityStatusCoherenceTest extends TestCase
{
    use RefreshDatabase;

    private function seedMailbox(): MailboxAccount
    {
        return MailboxAccount::query()->create([
            'email' => 'ops@aegis.test',
            'display_name' => 'Ops',
            'provider' => 'ovh_mx_plan',
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

    private function seedOutboundMessage(
        string $recipientStatus = 'queued',
        ?Carbon $sentAt = null,
        ?int $recipientId = null,
    ): array {
        $mailbox = $this->seedMailbox();

        $organization = Organization::query()->create([
            'name' => 'ACME Corp',
            'domain' => 'acme.test',
        ]);

        $contact = Contact::query()->create([
            'first_name' => 'Alice',
            'last_name' => 'Dupont',
            'organization_id' => $organization->id,
        ]);

        $contactEmail = ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $campaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'draft_id' => null,
            'name' => 'Test Campaign',
            'mode' => 'single',
            'status' => 'queued',
        ]);

        $recipient = MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'contact_email_id' => $contactEmail->id,
            'email' => 'alice@acme.test',
            'status' => $recipientStatus,
            'scheduled_for' => Carbon::parse('2026-03-20 09:00:00'),
            'last_event_at' => now(),
            'sent_at' => $sentAt,
        ]);

        $thread = MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailbox->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'subject_canonical' => 'test subject',
            'first_message_at' => now(),
            'last_message_at' => now(),
            'last_direction' => 'out',
            'reply_received' => false,
            'auto_reply_received' => false,
        ]);

        $message = MailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_account_id' => $mailbox->id,
            'recipient_id' => $recipientId ?? $recipient->id,
            'direction' => 'out',
            'message_id_header' => '<' . Str::uuid() . '@aegis.test>',
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => 'ops@aegis.test',
            'to_emails' => ['alice@acme.test'],
            'subject' => 'Test Subject',
            'headers_json' => [],
            'classification' => 'unknown',
            'sent_at' => $sentAt,
        ]);

        return compact('mailbox', 'organization', 'contact', 'contactEmail', 'campaign', 'recipient', 'thread', 'message');
    }

    // ── Test 1: Queued outbound message shows 'queued' (the exact bug) ──

    public function test_activity_shows_queued_for_outbound_message_still_queued(): void
    {
        $this->seedOutboundMessage(recipientStatus: 'queued', sentAt: null);

        $service = app(MailboxActivityService::class);
        $data = $service->activity();

        $this->assertCount(1, $data['events']);
        $this->assertSame('queued', $data['events'][0]['status']);
        $this->assertSame('outbound', $data['events'][0]['direction']);
    }

    // ── Test 2: Sending outbound message shows 'sending' ──

    public function test_activity_shows_sending_for_outbound_message_in_progress(): void
    {
        $this->seedOutboundMessage(recipientStatus: 'sending', sentAt: null);

        $service = app(MailboxActivityService::class);
        $data = $service->activity();

        $this->assertCount(1, $data['events']);
        $this->assertSame('sending', $data['events'][0]['status']);
    }

    // ── Test 3: Sent outbound message shows 'sent' ──

    public function test_activity_shows_sent_for_outbound_message_actually_sent(): void
    {
        $sentAt = Carbon::parse('2026-03-20 09:05:00');
        $this->seedOutboundMessage(recipientStatus: 'sent', sentAt: $sentAt);

        $service = app(MailboxActivityService::class);
        $data = $service->activity();

        $this->assertCount(1, $data['events']);
        $this->assertSame('sent', $data['events'][0]['status']);
    }

    // ── Test 4: Failed outbound message shows 'failed' ──

    public function test_activity_shows_failed_for_outbound_message_that_failed(): void
    {
        $this->seedOutboundMessage(recipientStatus: 'failed', sentAt: null);

        $service = app(MailboxActivityService::class);
        $data = $service->activity();

        $this->assertCount(1, $data['events']);
        $this->assertSame('failed', $data['events'][0]['status']);
    }

    // ── Test 5: Opened/clicked recipient shows correct upgraded status ──

    public function test_activity_shows_opened_for_recipient_that_opened(): void
    {
        $sentAt = Carbon::parse('2026-03-20 09:05:00');
        $this->seedOutboundMessage(recipientStatus: 'opened', sentAt: $sentAt);

        $service = app(MailboxActivityService::class);
        $data = $service->activity();

        $this->assertCount(1, $data['events']);
        $this->assertSame('opened', $data['events'][0]['status']);
    }

    // ── Test 6: Outbound message without recipient (test send) uses sent_at fallback ──

    public function test_activity_shows_sent_for_orphan_outbound_message_with_sent_at(): void
    {
        $mailbox = $this->seedMailbox();

        $thread = MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailbox->id,
            'subject_canonical' => 'test send',
            'first_message_at' => now(),
            'last_message_at' => now(),
            'last_direction' => 'out',
            'reply_received' => false,
            'auto_reply_received' => false,
        ]);

        MailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_account_id' => $mailbox->id,
            'recipient_id' => null,
            'direction' => 'out',
            'message_id_header' => '<' . Str::uuid() . '@aegis.test>',
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => 'ops@aegis.test',
            'to_emails' => ['test@acme.test'],
            'subject' => 'Test Send',
            'headers_json' => [],
            'classification' => 'unknown',
            'sent_at' => Carbon::parse('2026-03-20 10:00:00'),
        ]);

        $service = app(MailboxActivityService::class);
        $data = $service->activity();

        $this->assertCount(1, $data['events']);
        $this->assertSame('sent', $data['events'][0]['status']);
    }

    // ── Test 7: Orphan outbound message without sent_at shows 'queued' ──

    public function test_activity_shows_queued_for_orphan_outbound_message_without_sent_at(): void
    {
        $mailbox = $this->seedMailbox();

        $thread = MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailbox->id,
            'subject_canonical' => 'orphan test',
            'first_message_at' => now(),
            'last_message_at' => now(),
            'last_direction' => 'out',
            'reply_received' => false,
            'auto_reply_received' => false,
        ]);

        MailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_account_id' => $mailbox->id,
            'recipient_id' => null,
            'direction' => 'out',
            'message_id_header' => '<' . Str::uuid() . '@aegis.test>',
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => 'ops@aegis.test',
            'to_emails' => ['test@acme.test'],
            'subject' => 'Orphan Message',
            'headers_json' => [],
            'classification' => 'unknown',
            'sent_at' => null,
        ]);

        $service = app(MailboxActivityService::class);
        $data = $service->activity();

        $this->assertCount(1, $data['events']);
        $this->assertSame('queued', $data['events'][0]['status']);
    }

    // ── Test 8: Inbound messages still show correct classification ──

    public function test_activity_shows_correct_status_for_inbound_classifications(): void
    {
        $mailbox = $this->seedMailbox();

        $thread = MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailbox->id,
            'subject_canonical' => 'inbound test',
            'first_message_at' => now(),
            'last_message_at' => now(),
            'last_direction' => 'in',
            'reply_received' => true,
            'auto_reply_received' => false,
        ]);

        $classifications = [
            'human_reply' => 'replied',
            'auto_reply' => 'auto_replied',
            'out_of_office' => 'auto_replied',
            'auto_ack' => 'auto_replied',
            'soft_bounce' => 'soft_bounced',
            'hard_bounce' => 'hard_bounced',
            'unknown' => 'delivered_if_known',
        ];

        foreach ($classifications as $classification => $expectedStatus) {
            MailMessage::query()->create([
                'thread_id' => $thread->id,
                'mailbox_account_id' => $mailbox->id,
                'direction' => 'in',
                'message_id_header' => '<' . Str::uuid() . '@acme.test>',
                'aegis_tracking_id' => (string) Str::uuid(),
                'from_email' => 'alice@acme.test',
                'to_emails' => ['ops@aegis.test'],
                'subject' => "Test $classification",
                'headers_json' => [],
                'classification' => $classification,
                'received_at' => now(),
            ]);
        }

        $service = app(MailboxActivityService::class);
        $data = $service->activity();

        $this->assertCount(count($classifications), $data['events']);

        $statusMap = collect($data['events'])->pluck('status', 'title')->all();

        foreach ($classifications as $classification => $expectedStatus) {
            $this->assertSame($expectedStatus, $statusMap["Test $classification"],
                "Classification '$classification' should map to status '$expectedStatus'");
        }
    }

    // ── Test 9: Campaign detail and Activity show the same status for same recipient ──

    public function test_campaign_detail_and_activity_show_coherent_status(): void
    {
        Carbon::setTestNow('2026-03-20 09:00:00');

        $data = $this->seedOutboundMessage(recipientStatus: 'queued', sentAt: null);

        // Activity page
        $activityService = app(MailboxActivityService::class);
        $activityData = $activityService->activity();

        $this->assertCount(1, $activityData['events']);
        $activityStatus = $activityData['events'][0]['status'];

        // Campaign detail
        $campaign = $data['campaign']->fresh('recipients');
        $recipientStatus = $campaign->recipients->first()->status;

        // Both must agree
        $this->assertSame($recipientStatus, $activityStatus,
            "Campaign recipient status ($recipientStatus) must match Activity status ($activityStatus)");
    }

    // ── Test 10: Activity page via HTTP returns correct status for queued message ──

    public function test_activity_page_returns_queued_status_for_queued_message(): void
    {
        $this->seedOutboundMessage(recipientStatus: 'queued', sentAt: null);

        $this->get('/activity')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activity/Index')
                ->has('events', 1)
                ->where('events.0.status', 'queued')
                ->where('events.0.direction', 'outbound')
            );
    }

    // ── Test 11: Activity page via HTTP returns correct status for sent message ──

    public function test_activity_page_returns_sent_status_for_actually_sent_message(): void
    {
        $sentAt = Carbon::parse('2026-03-20 09:05:00');
        $this->seedOutboundMessage(recipientStatus: 'sent', sentAt: $sentAt);

        $this->get('/activity')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activity/Index')
                ->has('events', 1)
                ->where('events.0.status', 'sent')
            );
    }

    // ── Test 12: Reproduction of the exact reported bug scenario ──

    public function test_reproduce_queued_message_must_not_appear_as_sent_in_activity(): void
    {
        Carbon::setTestNow('2026-03-20 09:42:15');

        $mailbox = $this->seedMailbox();

        $organization = Organization::query()->create([
            'name' => 'Client Corp',
            'domain' => 'client.test',
        ]);

        $contact = Contact::query()->create([
            'first_name' => 'Ludovic',
            'last_name' => 'Bellavia',
            'organization_id' => $organization->id,
        ]);

        $contactEmail = ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'ludovic@client.test',
            'is_primary' => true,
        ]);

        $campaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'name' => 'Votre IT vous fait-il vraiment gagner du temps ?',
            'mode' => 'single',
            'status' => 'queued',
            'started_at' => null,
            'completed_at' => null,
        ]);

        $recipient = MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'contact_email_id' => $contactEmail->id,
            'email' => 'ludovic@client.test',
            'status' => 'queued',
            'scheduled_for' => now(),
            'last_event_at' => now(),
            'sent_at' => null,
        ]);

        $thread = MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailbox->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'subject_canonical' => 'votre it vous fait-il vraiment gagner du temps ?',
            'first_message_at' => now(),
            'last_message_at' => now(),
            'last_direction' => 'out',
            'reply_received' => false,
            'auto_reply_received' => false,
        ]);

        MailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_account_id' => $mailbox->id,
            'recipient_id' => $recipient->id,
            'direction' => 'out',
            'message_id_header' => '<' . Str::uuid() . '@aegis.test>',
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => 'ops@aegis.test',
            'to_emails' => ['ludovic@client.test'],
            'subject' => 'Votre IT vous fait-il vraiment gagner du temps ?',
            'headers_json' => [],
            'classification' => 'unknown',
            'sent_at' => null,
        ]);

        // This was the exact bug: Activity showed 'sent' for a queued message
        $service = app(MailboxActivityService::class);
        $data = $service->activity();

        $this->assertCount(1, $data['events']);
        $this->assertNotSame('sent', $data['events'][0]['status'],
            'A queued message must NEVER appear as sent in the Activity page');
        $this->assertSame('queued', $data['events'][0]['status']);

        // Campaign detail must also show queued
        $campaign->load('recipients');
        $this->assertSame('queued', $campaign->recipients->first()->status);
    }

    // ── Test 13: Tracking events are deduplicated from message entries ──

    public function test_activity_does_not_duplicate_tracking_events_with_message_entries(): void
    {
        $data = $this->seedOutboundMessage(recipientStatus: 'opened', sentAt: Carbon::parse('2026-03-20 09:05:00'));

        // Add a tracking event for the same message
        MailEvent::query()->create([
            'event_type' => 'mail_message.opened',
            'event_payload' => [],
            'campaign_id' => $data['campaign']->id,
            'recipient_id' => $data['recipient']->id,
            'message_id' => $data['message']->id,
            'occurred_at' => Carbon::parse('2026-03-20 10:00:00'),
            'created_at' => now(),
        ]);

        $service = app(MailboxActivityService::class);
        $activityData = $service->activity();

        // Should only have 1 entry (the message), not 2 (message + tracking event)
        $this->assertCount(1, $activityData['events']);
        $this->assertSame('opened', $activityData['events'][0]['status']);
    }
}
