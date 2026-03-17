<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailCampaign;
use App\Models\MailDraft;
use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Models\MailThread;
use App\Models\MailboxAccount;
use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CrmPagePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_exposes_the_empty_payload_shape(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.sentToday', 0)
                ->where('stats.dailyLimit', 100)
                ->where('stats.healthStatus', 'critical')
                ->where('stats.bounceRate', 0)
                ->where('stats.activeCampaigns', 0)
                ->where('stats.scheduledCount', 0)
                ->has('recentReplies', 0)
                ->has('recentAlerts', 0)
                ->has('scheduledSends', 0)
            );
    }

    public function test_contacts_page_exposes_empty_contacts(): void
    {
        $this->get('/contacts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contacts/Index')
                ->has('contacts', 0)
                ->has('organizations', 0)
                ->where('capabilities.canCreate', true)
                ->where('capabilities.createEndpoint', '/api/contacts')
            );
    }

    public function test_organizations_page_exposes_empty_organizations(): void
    {
        $this->get('/organizations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Index')
                ->has('organizations', 0)
                ->where('capabilities.canCreate', true)
                ->where('capabilities.createEndpoint', '/api/organizations')
            );
    }

    public function test_dashboard_page_exposes_scheduled_sends_stats_and_recent_activity(): void
    {
        Carbon::setTestNow('2026-03-15 08:30:00');

        [$mailbox] = $this->seedCrmDataset();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.sentToday', 1)
                ->where('stats.dailyLimit', 150)
                ->where('stats.healthStatus', 'good')
                ->where('stats.bounceRate', 25)
                ->where('stats.activeCampaigns', 1)
                ->where('stats.scheduledCount', 1)
                ->has('scheduledSends', 1)
                ->has('scheduledSends.0', fn (Assert $send) => $send
                    ->where('id', 2)
                    ->where('subject', 'Sequence bulk Q2')
                    ->where('recipientCount', 2)
                    ->where('scheduledAt', '2026-03-20 09:30')
                    ->etc()
                )
                ->has('recentReplies', 1)
                ->has('recentReplies.0', fn (Assert $reply) => $reply
                    ->where('status', 'replied')
                    ->where('from', 'alice@acme.test')
                    ->where('subject', 'Re: Offre AEGIS')
                    ->where('time', '2026-03-15 10:15')
                    ->etc()
                )
                ->has('recentAlerts', 1)
                ->has('recentAlerts.0', fn (Assert $alert) => $alert
                    ->where('status', 'hard_bounced')
                    ->where('email', 'mailer-daemon@ovh.test')
                    ->where('detail', 'Hard bounce · Delivery failure')
                    ->where('time', '2026-03-15 10:45')
                    ->etc()
                )
            );

        $this->assertSame('healthy', $mailbox->fresh()->health_status);
    }

    public function test_contacts_page_exposes_expected_shape_and_flags(): void
    {
        $this->seedCrmDataset();

        $this->get('/contacts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contacts/Index')
                ->has('contacts', 3)
                ->has('contacts.0', fn (Assert $contact) => $contact
                    ->where('firstName', 'Alice')
                    ->where('lastName', 'Martin')
                    ->where('title', 'Head of Sales')
                    ->where('organization', 'Acme')
                    ->where('email', 'alice@acme.test')
                    ->where('score', 10)
                    ->where('scoreLevel', 'engaged')
                    ->where('excluded', false)
                    ->where('unsubscribed', false)
                    ->where('lastActivityAt', '2026-03-15 10:30')
                    ->etc()
                )
                ->has('contacts.1', fn (Assert $contact) => $contact
                    ->where('firstName', 'Bob')
                    ->where('lastName', 'Durand')
                    ->where('score', -20)
                    ->where('scoreLevel', 'excluded')
                    ->where('excluded', false)
                    ->where('unsubscribed', true)
                    ->where('lastActivityAt', '2026-03-15 11:00')
                    ->etc()
                )
                ->has('contacts.2', fn (Assert $contact) => $contact
                    ->where('firstName', 'Chloe')
                    ->where('lastName', 'Bernard')
                    ->where('score', -15)
                    ->where('scoreLevel', 'excluded')
                    ->where('excluded', true)
                    ->where('unsubscribed', false)
                    ->where('lastActivityAt', '2026-03-15 12:30')
                    ->etc()
                )
                ->has('organizations', 1)
                ->where('organizations.0.id', 1)
                ->where('organizations.0.name', 'Acme')
                ->where('capabilities.canCreate', true)
                ->where('capabilities.createEndpoint', '/api/contacts')
            );
    }

    public function test_contacts_page_supports_simple_server_filters(): void
    {
        $this->seedCrmDataset();

        $this->get('/contacts?search=Bob&status=unsubscribed&score=excluded')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contacts/Index')
                ->has('contacts', 1)
                ->has('contacts.0', fn (Assert $contact) => $contact
                    ->where('firstName', 'Bob')
                    ->where('unsubscribed', true)
                    ->where('scoreLevel', 'excluded')
                    ->etc()
                )
            );
    }

    public function test_organizations_page_exposes_expected_shape_and_counts(): void
    {
        $this->seedCrmDataset();

        $this->get('/organizations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Index')
                ->has('organizations', 1)
                ->has('organizations.0', fn (Assert $organization) => $organization
                    ->where('name', 'Acme')
                    ->where('domain', 'acme.test')
                    ->where('contactCount', 3)
                    ->where('sentCount', 4)
                    ->where('lastActivityAt', '2026-03-15 12:30')
                    ->etc()
                )
                ->where('capabilities.canCreate', true)
                ->where('capabilities.createEndpoint', '/api/organizations')
            );
    }

    public function test_contact_show_page_exposes_real_detail_payload(): void
    {
        [, , $alice] = $this->seedCrmDataset();

        $this->get('/contacts/'.$alice->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contacts/Show')
                ->where('contact.id', $alice->id)
                ->where('contact.firstName', 'Alice')
                ->where('contact.organizationName', 'Acme')
                ->has('contact.emails', 1)
                ->where('contact.emails.0.email', 'alice@acme.test')
                ->has('contact.recentThreads', 1)
                ->where('contact.recentThreads.0.replyReceived', true)
                ->has('organizations', 1)
                ->where('organizations.0.name', 'Acme')
            );
    }

    public function test_organization_show_page_exposes_real_detail_payload(): void
    {
        [, $organization] = $this->seedCrmDataset();

        $this->get('/organizations/'.$organization->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Show')
                ->where('organization.id', $organization->id)
                ->where('organization.name', 'Acme')
                ->where('organization.contactCount', 3)
                ->has('organization.contacts', 3)
                ->where('organization.contacts.0.name', 'Chloe Bernard')
                ->has('organization.recentThreads', 1)
                ->where('organization.recentThreads.0.lastDirection', 'in')
            );
    }

    private function seedCrmDataset(): array
    {
        Setting::query()->create([
            'key' => 'general',
            'value_json' => array_replace(config('mailing.defaults.general', []), [
                'daily_limit_default' => 150,
                'open_points' => 1,
                'click_points' => 2,
                'reply_points' => 8,
                'auto_reply_points' => 0,
                'soft_bounce_points' => -5,
                'hard_bounce_points' => -15,
                'unsubscribe_points' => -20,
            ]),
            'updated_at' => Carbon::parse('2026-03-15 08:00:00'),
        ]);

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

        $alice = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'job_title' => 'Head of Sales',
        ]);

        $bob = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Bob',
            'last_name' => 'Durand',
        ]);

        $chloe = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Chloe',
            'last_name' => 'Bernard',
        ]);

        $aliceEmail = ContactEmail::query()->create([
            'contact_id' => $alice->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
            'last_seen_at' => Carbon::parse('2026-03-15 09:30:00'),
        ]);

        $bobEmail = ContactEmail::query()->create([
            'contact_id' => $bob->id,
            'email' => 'bob@acme.test',
            'is_primary' => true,
            'opt_out_at' => Carbon::parse('2026-03-15 11:00:00'),
            'opt_out_reason' => 'manual_request',
        ]);

        $chloeEmail = ContactEmail::query()->create([
            'contact_id' => $chloe->id,
            'email' => 'chloe@acme.test',
            'is_primary' => true,
            'bounce_status' => 'hard_bounced',
            'last_seen_at' => Carbon::parse('2026-03-15 12:00:00'),
        ]);

        $thread = MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailbox->id,
            'organization_id' => $organization->id,
            'contact_id' => $alice->id,
            'subject_canonical' => 'offre aegis',
            'first_message_at' => Carbon::parse('2026-03-15 09:00:00'),
            'last_message_at' => Carbon::parse('2026-03-15 10:30:00'),
            'last_direction' => 'in',
            'reply_received' => true,
            'auto_reply_received' => false,
        ]);

        MailDraft::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'mode' => 'single',
            'subject' => 'Follow-up Alice',
            'html_body' => '<p>Bonjour Alice</p>',
            'text_body' => 'Bonjour Alice',
            'status' => 'draft',
        ]);

        $scheduledDraft = MailDraft::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'mode' => 'bulk',
            'subject' => 'Sequence bulk Q2',
            'html_body' => '<p>Bonjour</p>',
            'text_body' => 'Bonjour',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::parse('2026-03-20 09:30:00'),
        ]);

        $scheduledCampaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'name' => 'Q2 Campaign',
            'mode' => 'bulk',
            'draft_id' => $scheduledDraft->id,
            'status' => 'scheduled',
        ]);

        MailRecipient::query()->create([
            'campaign_id' => $scheduledCampaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $alice->id,
            'contact_email_id' => $aliceEmail->id,
            'email' => 'alice@acme.test',
            'status' => 'draft',
            'scheduled_for' => Carbon::parse('2026-03-20 09:30:00'),
        ]);

        MailRecipient::query()->create([
            'campaign_id' => $scheduledCampaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $bob->id,
            'contact_email_id' => $bobEmail->id,
            'email' => 'bob@acme.test',
            'status' => 'draft',
            'scheduled_for' => Carbon::parse('2026-03-20 09:32:00'),
        ]);

        $sentCampaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'name' => 'Sent Campaign',
            'mode' => 'bulk',
            'status' => 'completed',
        ]);

        MailRecipient::query()->create([
            'campaign_id' => $sentCampaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $alice->id,
            'contact_email_id' => $aliceEmail->id,
            'email' => 'alice@acme.test',
            'status' => 'clicked',
            'sent_at' => Carbon::parse('2026-03-15 09:45:00'),
            'last_event_at' => Carbon::parse('2026-03-15 10:30:00'),
        ]);

        MailRecipient::query()->create([
            'campaign_id' => $sentCampaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $alice->id,
            'contact_email_id' => $aliceEmail->id,
            'email' => 'alice@acme.test',
            'status' => 'replied',
            'sent_at' => Carbon::parse('2026-03-15 09:50:00'),
            'last_event_at' => Carbon::parse('2026-03-15 10:15:00'),
            'replied_at' => Carbon::parse('2026-03-15 10:15:00'),
        ]);

        MailRecipient::query()->create([
            'campaign_id' => $sentCampaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $bob->id,
            'contact_email_id' => $bobEmail->id,
            'email' => 'bob@acme.test',
            'status' => 'unsubscribed',
            'sent_at' => Carbon::parse('2026-03-15 10:00:00'),
            'last_event_at' => Carbon::parse('2026-03-15 11:00:00'),
            'unsubscribe_at' => Carbon::parse('2026-03-15 11:00:00'),
        ]);

        MailRecipient::query()->create([
            'campaign_id' => $sentCampaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $chloe->id,
            'contact_email_id' => $chloeEmail->id,
            'email' => 'chloe@acme.test',
            'status' => 'hard_bounced',
            'sent_at' => Carbon::parse('2026-03-15 10:10:00'),
            'last_event_at' => Carbon::parse('2026-03-15 12:30:00'),
            'bounced_at' => Carbon::parse('2026-03-15 12:30:00'),
        ]);

        MailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_account_id' => $mailbox->id,
            'recipient_id' => null,
            'direction' => 'out',
            'message_id_header' => '<outbound-1@aegis.test>',
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => 'ops@aegis.test',
            'to_emails' => ['alice@acme.test'],
            'subject' => 'Offre AEGIS',
            'headers_json' => [],
            'classification' => 'unknown',
            'sent_at' => Carbon::parse('2026-03-15 09:40:00'),
        ]);

        MailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_account_id' => $mailbox->id,
            'direction' => 'in',
            'message_id_header' => '<reply-1@acme.test>',
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => 'alice@acme.test',
            'to_emails' => ['ops@aegis.test'],
            'subject' => 'Re: Offre AEGIS',
            'headers_json' => [],
            'classification' => 'human_reply',
            'received_at' => Carbon::parse('2026-03-15 10:15:00'),
        ]);

        MailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_account_id' => $mailbox->id,
            'direction' => 'in',
            'message_id_header' => '<bounce-1@ovh.test>',
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => 'mailer-daemon@ovh.test',
            'to_emails' => ['ops@aegis.test'],
            'subject' => 'Delivery failure',
            'headers_json' => [],
            'classification' => 'hard_bounce',
            'received_at' => Carbon::parse('2026-03-15 10:45:00'),
        ]);

        return [$mailbox, $organization, $alice, $bob, $chloe];
    }
}
