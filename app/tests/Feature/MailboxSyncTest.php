<?php

namespace Tests\Feature;

use App\Jobs\Mailing\SyncMailboxFolderJob;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailCampaign;
use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Models\MailThread;
use App\Models\MailboxAccount;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\Mailing\Contracts\MailGatewayClient;
use App\Services\Mailing\Inbound\MailboxSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MailboxSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_activity_and_threads_expose_empty_shapes_when_no_mail_exists(): void
    {
        $this->get('/activity')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activity/Index')
                ->has('events', 0)
            );

        $this->getJson('/api/threads')
            ->assertOk()
            ->assertJsonPath('threads', []);
    }

    public function test_sync_is_idempotent_updates_uid_cursor_and_correlates_sent_message_by_message_id(): void
    {
        Carbon::setTestNow('2026-03-15 10:00:00');
        [$mailbox, , , , , $outboundMessage] = $this->seedConversation();

        $this->runSync([
            'mailbox_account_id' => $mailbox->id,
            'folder' => 'SENT',
            'idempotency_key' => 'mailbox.sync.sent.initial',
            'stub_messages' => [
                [
                    'uid' => 14,
                    'message_id_header' => $outboundMessage->message_id_header,
                    'from_email' => 'ops@aegis.test',
                    'to_emails' => ['alice@acme.test'],
                    'subject' => 'Offre AEGIS',
                    'sent_at' => '2026-03-15T09:05:00+00:00',
                    'headers_json' => [
                        'Message-ID' => $outboundMessage->message_id_header,
                    ],
                ],
            ],
        ]);

        $outboundMessage->refresh();

        $this->assertSame('SENT', $outboundMessage->provider_folder);
        $this->assertSame(14, $outboundMessage->provider_uid);
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertSame(14, $mailbox->fresh()->last_sent_uid);

        $this->runSync([
            'mailbox_account_id' => $mailbox->id,
            'folder' => 'SENT',
            'idempotency_key' => 'mailbox.sync.sent.repeat',
            'stub_messages' => [
                [
                    'uid' => 14,
                    'message_id_header' => $outboundMessage->message_id_header,
                    'from_email' => 'ops@aegis.test',
                    'to_emails' => ['alice@acme.test'],
                    'subject' => 'Offre AEGIS',
                    'sent_at' => '2026-03-15T09:05:00+00:00',
                ],
            ],
        ]);

        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'mailbox.sync_completed']);
    }

    public function test_in_reply_to_sync_matches_existing_thread_marks_human_reply_and_cancels_future_follow_up(): void
    {
        Carbon::setTestNow('2026-03-15 10:30:00');
        [$mailbox, $contact, $contactEmail, $recipient, $queuedFollowUp, $outboundMessage] = $this->seedConversation();

        $this->runSync([
            'mailbox_account_id' => $mailbox->id,
            'folder' => 'INBOX',
            'idempotency_key' => 'mailbox.sync.inbox.reply',
            'stub_messages' => [
                [
                    'uid' => 101,
                    'message_id_header' => '<reply-1@acme.test>',
                    'in_reply_to_header' => $outboundMessage->message_id_header,
                    'references_header' => $outboundMessage->message_id_header,
                    'from_email' => $contactEmail->email,
                    'to_emails' => ['ops@aegis.test'],
                    'subject' => 'Re: Offre AEGIS',
                    'text_body' => 'Merci pour votre message.',
                    'received_at' => '2026-03-15T10:30:00+00:00',
                ],
            ],
        ]);

        $inbound = MailMessage::query()->where('direction', 'in')->firstOrFail();

        $this->assertSame('human_reply', $inbound->classification);
        $this->assertSame($outboundMessage->thread_id, $inbound->thread_id);
        $this->assertSame($recipient->id, $inbound->recipient_id);
        $this->assertTrue($recipient->fresh()->replied_at !== null);
        $this->assertSame('replied', $recipient->fresh()->status);
        $this->assertSame('cancelled', $queuedFollowUp->fresh()->status);
        $this->assertTrue($contact->fresh()->threads()->firstOrFail()->reply_received);
        $this->assertFalse($contact->fresh()->threads()->firstOrFail()->auto_reply_received);
        $this->assertSame('2026-03-15 10:30:00', $contactEmail->fresh()->last_seen_at?->format('Y-m-d H:i:s'));
    }

    public function test_auto_reply_remains_distinct_from_human_reply_and_feeds_activity_timeline(): void
    {
        Carbon::setTestNow('2026-03-15 11:00:00');
        [$mailbox, , $contactEmail, $recipient, , $outboundMessage] = $this->seedConversation();

        $this->runSync([
            'mailbox_account_id' => $mailbox->id,
            'folder' => 'INBOX',
            'idempotency_key' => 'mailbox.sync.inbox.auto-reply',
            'stub_messages' => [
                [
                    'uid' => 110,
                    'message_id_header' => '<ooo-1@acme.test>',
                    'in_reply_to_header' => $outboundMessage->message_id_header,
                    'from_email' => $contactEmail->email,
                    'to_emails' => ['ops@aegis.test'],
                    'subject' => 'Automatic reply: out of office',
                    'text_body' => 'I am away from the office until Monday.',
                    'received_at' => '2026-03-15T11:00:00+00:00',
                    'headers_json' => [
                        'Auto-Submitted' => 'auto-replied',
                    ],
                    'attachments' => [
                        [
                            'original_name' => 'calendar.ics',
                            'mime_type' => 'text/calendar',
                            'size_bytes' => 512,
                            'storage_disk' => 'local',
                            'storage_path' => 'mailbox-sync/calendar.ics',
                        ],
                    ],
                ],
            ],
        ]);

        $recipient->refresh();
        $thread = MailThread::query()->findOrFail($outboundMessage->thread_id);

        $this->assertSame('auto_replied', $recipient->status);
        $this->assertNull($recipient->replied_at);
        $this->assertNotNull($recipient->auto_replied_at);
        $this->assertFalse($thread->reply_received);
        $this->assertTrue($thread->auto_reply_received);
        $this->assertDatabaseHas('mail_attachments', ['original_name' => 'calendar.ics']);

        $this->get('/activity')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activity/Index')
                ->has('events', 2)
                ->has('events.0', fn (Assert $event) => $event
                    ->where('status', 'auto_replied')
                    ->where('isAutoReply', true)
                    ->where('isBounce', false)
                    ->etc()
                )
            );

        $this->getJson('/api/threads')
            ->assertOk()
            ->assertJsonPath('threads.0.replyReceived', false)
            ->assertJsonPath('threads.0.autoReplyReceived', true);

        $this->getJson('/api/threads/'.$thread->id)
            ->assertOk()
            ->assertJsonPath('thread.replyReceived', false)
            ->assertJsonPath('thread.autoReplyReceived', true)
            ->assertJsonPath('thread.messages.1.classification', 'out_of_office')
            ->assertJsonPath('thread.messages.1.attachmentCount', 1);
    }

    public function test_soft_and_hard_bounces_are_classified_and_hard_bounce_updates_exclusion_state(): void
    {
        Carbon::setTestNow('2026-03-15 12:00:00');
        [$mailbox, , $contactEmail, $recipient, $queuedFollowUp, $outboundMessage] = $this->seedConversation();

        $this->runSync([
            'mailbox_account_id' => $mailbox->id,
            'folder' => 'INBOX',
            'idempotency_key' => 'mailbox.sync.inbox.soft-bounce',
            'stub_messages' => [
                [
                    'uid' => 120,
                    'message_id_header' => '<soft-bounce-1@ovh.test>',
                    'in_reply_to_header' => $outboundMessage->message_id_header,
                    'from_email' => 'mailer-daemon@ovh.test',
                    'to_emails' => ['ops@aegis.test'],
                    'subject' => 'Mail delivery failed',
                    'text_body' => 'Temporary error: mailbox full for alice@acme.test',
                    'received_at' => '2026-03-15T12:00:00+00:00',
                    'headers_json' => [
                        'X-Failed-Recipients' => 'alice@acme.test',
                    ],
                ],
            ],
        ]);

        $this->assertSame('soft_bounced', $recipient->fresh()->status);
        $this->assertNull($contactEmail->fresh()->bounce_status);

        $this->runSync([
            'mailbox_account_id' => $mailbox->id,
            'folder' => 'INBOX',
            'idempotency_key' => 'mailbox.sync.inbox.hard-bounce',
            'stub_messages' => [
                [
                    'uid' => 121,
                    'message_id_header' => '<hard-bounce-1@ovh.test>',
                    'references_header' => [$outboundMessage->message_id_header],
                    'from_email' => 'mailer-daemon@ovh.test',
                    'to_emails' => ['ops@aegis.test'],
                    'subject' => 'Delivery failure',
                    'text_body' => 'User unknown: alice@acme.test',
                    'received_at' => '2026-03-15T12:05:00+00:00',
                    'headers_json' => [
                        'X-Failed-Recipients' => 'alice@acme.test',
                    ],
                ],
            ],
        ]);

        $this->assertSame('hard_bounced', $recipient->fresh()->status);
        $this->assertSame('hard_bounced', $contactEmail->fresh()->bounce_status);
        $this->assertSame('cancelled', $queuedFollowUp->fresh()->status);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'mail_campaign.follow_up_cancelled']);
    }

    private function runSync(array $payload): void
    {
        $job = new SyncMailboxFolderJob($payload);
        $job->handle(
            app(MailGatewayClient::class),
            app(MailboxSyncService::class),
        );
    }

    private function seedConversation(): array
    {
        Setting::query()->updateOrCreate(
            ['key' => 'mail'],
            [
                'value_json' => array_replace(config('mailing.defaults.mail', []), [
                    'global_signature_html' => '<p>Cordialement,<br>AEGIS</p>',
                    'global_signature_text' => "Cordialement,\nAEGIS",
                    'send_window_start' => '09:00',
                    'send_window_end' => '18:00',
                ]),
            ],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'general'],
            [
                'value_json' => config('mailing.defaults.general', []),
            ],
        );

        $mailbox = MailboxAccount::query()->create([
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

        $organization = Organization::query()->create([
            'name' => 'Acme',
            'domain' => 'acme.test',
        ]);

        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
        ]);

        $contactEmail = ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $campaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'name' => 'Thread test',
            'mode' => 'single',
            'status' => 'sent',
        ]);

        $recipient = MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'contact_email_id' => $contactEmail->id,
            'email' => $contactEmail->email,
            'status' => 'sent',
            'sent_at' => Carbon::parse('2026-03-15 09:05:00'),
            'last_event_at' => Carbon::parse('2026-03-15 09:05:00'),
        ]);

        $queuedFollowUp = MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'contact_email_id' => $contactEmail->id,
            'email' => $contactEmail->email,
            'status' => 'queued',
            'scheduled_for' => Carbon::parse('2026-03-16 09:00:00'),
        ]);

        $thread = MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailbox->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'subject_canonical' => 'offre aegis',
            'first_message_at' => Carbon::parse('2026-03-15 09:05:00'),
            'last_message_at' => Carbon::parse('2026-03-15 09:05:00'),
            'last_direction' => 'out',
            'reply_received' => false,
            'auto_reply_received' => false,
            'status' => 'active',
        ]);

        $outboundMessage = MailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_account_id' => $mailbox->id,
            'recipient_id' => $recipient->id,
            'direction' => 'out',
            'message_id_header' => '<outbound-1@aegis.test>',
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => $mailbox->email,
            'to_emails' => [$contactEmail->email],
            'subject' => 'Offre AEGIS',
            'headers_json' => [
                'Message-ID' => '<outbound-1@aegis.test>',
            ],
            'classification' => 'unknown',
            'sent_at' => Carbon::parse('2026-03-15 09:05:00'),
        ]);

        return [$mailbox, $contact, $contactEmail, $recipient, $queuedFollowUp, $outboundMessage];
    }
}
