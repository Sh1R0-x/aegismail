<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailboxAccount;
use App\Models\MailCampaign;
use App\Models\MailDraft;
use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Models\MailTemplate;
use App\Models\MailThread;
use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComposerPagePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_drafts_templates_and_campaigns_pages_expose_empty_shapes(): void
    {
        $this->get('/drafts')
            ->assertRedirect('/mails?tab=drafts');

        $this->get('/mails')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mails/Index')
                ->has('drafts', 0)
            );

        $this->get('/templates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Templates/Index')
                ->has('templates', 0)
            );

        $this->get('/campaigns')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Index')
                ->has('campaigns', 0)
                ->where('creationFlow.type', 'draft_first')
                ->where('creationFlow.entryHref', '/campaigns/create')
                ->where('creationFlow.actionLabel', 'Préparer une campagne')
            );
    }

    public function test_drafts_templates_and_campaigns_pages_expose_expected_payloads(): void
    {
        [$draft, $template, $campaign] = $this->seedComposerDataset();

        $this->get('/drafts')
            ->assertRedirect('/mails?tab=drafts');

        $this->get('/mails')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mails/Index')
                ->has('drafts', 1)
                ->has('drafts.0', fn (Assert $draftPayload) => $draftPayload
                    ->where('id', $draft->id)
                    ->where('subject', 'Séquence V1')
                    ->where('recipientCount', 2)
                    ->where('type', 'multiple')
                    ->where('scheduledAt', '2026-03-20T09:30:00+01:00')
                    ->etc()
                )
            );

        $this->get('/templates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Templates/Index')
                ->has('templates', 1)
                ->has('templates.0', fn (Assert $templatePayload) => $templatePayload
                    ->where('id', $template->id)
                    ->where('name', 'Prospection')
                    ->where('subject', 'Bonjour {{first_name}}')
                    ->where('active', true)
                    ->where('usageCount', 1)
                    ->etc()
                )
            );

        $this->get('/campaigns')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Index')
                ->has('campaigns', 1)
                ->where('creationFlow.type', 'draft_first')
                ->where('creationFlow.entryHref', '/campaigns/create')
                ->where('creationFlow.actionLabel', 'Préparer une campagne')
                ->where('creationFlow.helperText', 'Le module Campagnes conserve une couche draft technique interne, mais l’utilisateur prépare, édite et planifie ses campagnes depuis /campaigns.')
                ->has('campaigns.0', fn (Assert $campaignPayload) => $campaignPayload
                    ->where('id', $campaign->id)
                    ->where('name', 'Séquence V1')
                    ->where('status', 'scheduled')
                    ->where('progressPercent', 0)
                    ->where('recipientCount', 2)
                    ->where('openCount', 0)
                    ->where('replyCount', 0)
                    ->where('bounceCount', 0)
                    ->etc()
                )
            );
    }

    public function test_campaign_create_page_and_show_page_expose_real_campaign_payloads(): void
    {
        [$draft, , $campaign] = $this->seedComposerDataset();

        $this->get('/campaigns/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Create')
                ->has('templates', 1)
                ->where('templates.0.id', $draft->template_id)
                ->where('autosave.endpoint', '/api/campaigns/autosave')
                ->where('autosave.conflictMode', 'reject_on_stale_updated_at')
                ->has('audiences.contacts', 2)
                ->has('audiences.organizations', 1)
                ->has('audiences.recentImports', 0)
            );

        $this->get('/campaigns/'.$campaign->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Show')
                ->where('campaign.id', $campaign->id)
                ->where('campaign.draftId', $draft->id)
                ->where('campaign.draft.id', $draft->id)
                ->where('campaign.draft.type', 'multiple')
                ->has('campaign.recipients', 2)
                ->where('campaign.recipients.0.email', 'alice@acme.test')
                ->where('campaign.recipients.1.email', 'bob@acme.test')
                ->has('templates', 1)
            );
    }

    public function test_mails_page_exposes_thread_id_and_thread_show_payload_when_history_exists(): void
    {
        Setting::query()->create([
            'key' => 'general',
            'value_json' => config('mailing.defaults.general', []),
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

        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
        ]);

        $email = ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'mode' => 'single',
            'subject' => 'Suivi Alice',
            'html_body' => '<p>Bonjour Alice</p>',
            'text_body' => 'Bonjour Alice',
            'status' => 'sent',
        ]);

        $campaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'name' => 'Suivi Alice',
            'mode' => 'single',
            'draft_id' => $draft->id,
            'status' => 'sent',
        ]);

        $recipient = MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'contact_email_id' => $email->id,
            'email' => $email->email,
            'status' => 'sent',
            'sent_at' => Carbon::parse('2026-03-15 09:45:00'),
        ]);

        $thread = MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailbox->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'subject_canonical' => 'suivi alice',
            'first_message_at' => Carbon::parse('2026-03-15 09:45:00'),
            'last_message_at' => Carbon::parse('2026-03-15 10:15:00'),
            'last_direction' => 'in',
            'reply_received' => true,
            'auto_reply_received' => false,
            'status' => 'replied',
        ]);

        MailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_account_id' => $mailbox->id,
            'recipient_id' => $recipient->id,
            'direction' => 'out',
            'message_id_header' => '<outbound-1@aegis.test>',
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => 'ops@aegis.test',
            'to_emails' => ['alice@acme.test'],
            'subject' => 'Suivi Alice',
            'headers_json' => [],
            'classification' => 'unknown',
            'sent_at' => Carbon::parse('2026-03-15 09:45:00'),
        ]);

        MailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_account_id' => $mailbox->id,
            'direction' => 'in',
            'message_id_header' => '<reply-1@acme.test>',
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => 'alice@acme.test',
            'to_emails' => ['ops@aegis.test'],
            'subject' => 'Re: Suivi Alice',
            'headers_json' => [],
            'classification' => 'human_reply',
            'received_at' => Carbon::parse('2026-03-15 10:15:00'),
        ]);

        $this->get('/mails')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mails/Index')
                ->has('recipients', 1)
                ->where('recipients.0.threadId', $thread->id)
                ->where('recipients.0.campaignId', $campaign->id)
            );

        $this->get('/threads/'.$thread->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Threads/Show')
                ->where('thread.id', $thread->id)
                ->where('thread.replyReceived', true)
                ->has('thread.messages', 2)
                ->where('thread.messages.0.direction', 'out')
                ->where('thread.messages.1.classification', 'human_reply')
            );
    }

    private function seedComposerDataset(): array
    {
        Setting::query()->create([
            'key' => 'mail',
            'value_json' => [
                'global_signature_html' => '<p>Cordialement,<br>AEGIS</p>',
                'global_signature_text' => "Cordialement,\nAEGIS",
                'send_window_start' => '09:00',
                'send_window_end' => '18:00',
            ],
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

        $contactA = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
        ]);

        $contactB = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Bob',
            'last_name' => 'Durand',
        ]);

        $emailA = ContactEmail::query()->create([
            'contact_id' => $contactA->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $emailB = ContactEmail::query()->create([
            'contact_id' => $contactB->id,
            'email' => 'bob@acme.test',
            'is_primary' => true,
        ]);

        $template = MailTemplate::query()->create([
            'name' => 'Prospection',
            'slug' => 'prospection',
            'subject_template' => 'Bonjour {{first_name}}',
            'html_template' => '<p>Bonjour {{first_name}}</p>',
            'text_template' => 'Bonjour {{first_name}}',
            'is_active' => true,
        ]);

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'mode' => 'bulk',
            'template_id' => $template->id,
            'subject' => 'Séquence V1',
            'html_body' => '<p>Bonjour</p>',
            'text_body' => 'Bonjour',
            'payload_json' => [
                'recipients' => [
                    ['contactId' => $contactA->id, 'contactEmailId' => $emailA->id, 'organizationId' => $organization->id, 'email' => $emailA->email],
                    ['contactId' => $contactB->id, 'contactEmailId' => $emailB->id, 'organizationId' => $organization->id, 'email' => $emailB->email],
                ],
            ],
            'status' => 'scheduled',
            'scheduled_at' => Carbon::parse('2026-03-20 09:30:00'),
            'updated_at' => Carbon::parse('2026-03-15 10:00:00'),
        ]);

        $campaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'name' => 'Séquence V1',
            'mode' => 'bulk',
            'draft_id' => $draft->id,
            'status' => 'scheduled',
        ]);

        MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $contactA->id,
            'contact_email_id' => $emailA->id,
            'email' => $emailA->email,
            'status' => 'draft',
            'scheduled_for' => Carbon::parse('2026-03-20 09:30:00'),
        ]);

        MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $contactB->id,
            'contact_email_id' => $emailB->id,
            'email' => $emailB->email,
            'status' => 'draft',
            'scheduled_for' => Carbon::parse('2026-03-20 09:32:00'),
        ]);

        return [$draft, $template, $campaign];
    }
}
