<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailCampaign;
use App\Models\MailDraft;
use App\Models\MailRecipient;
use App\Models\MailTemplate;
use App\Models\MailboxAccount;
use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComposerPagePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_drafts_templates_and_campaigns_pages_expose_empty_shapes(): void
    {
        $this->get('/drafts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Drafts/Index')
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
            );
    }

    public function test_drafts_templates_and_campaigns_pages_expose_expected_payloads(): void
    {
        [$draft, $template, $campaign] = $this->seedComposerDataset();

        $this->get('/drafts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Drafts/Index')
                ->has('drafts', 1)
                ->has('drafts.0', fn (Assert $draftPayload) => $draftPayload
                    ->where('id', $draft->id)
                    ->where('subject', 'Séquence V1')
                    ->where('recipientCount', 2)
                    ->where('type', 'multiple')
                    ->where('scheduledAt', '2026-03-20 09:30')
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
