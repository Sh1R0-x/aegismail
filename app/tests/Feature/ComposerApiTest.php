<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailAttachment;
use App\Models\MailDraft;
use App\Models\MailboxAccount;
use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ComposerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_support_minimal_crud_duplicate_and_archive(): void
    {
        $this->seedMailboxAndSettings();

        $created = $this->postJson('/api/templates', [
            'name' => 'Prospection V1',
            'subject' => 'Bonjour {{first_name}}',
            'htmlBody' => '<p>Bonjour {{first_name}}</p>',
            'textBody' => 'Bonjour {{first_name}}',
        ])->assertCreated();

        $templateId = $created->json('template.id');

        $this->getJson('/api/templates')
            ->assertOk()
            ->assertJsonPath('templates.0.name', 'Prospection V1');

        $this->putJson('/api/templates/'.$templateId, [
            'name' => 'Prospection V1 bis',
            'subject' => 'Rebonjour {{first_name}}',
            'htmlBody' => '<p>Rebonjour</p>',
            'textBody' => 'Rebonjour',
            'active' => true,
        ])->assertOk()
            ->assertJsonPath('template.subject', 'Rebonjour {{first_name}}');

        $this->postJson('/api/templates/'.$templateId.'/duplicate')
            ->assertCreated()
            ->assertJsonPath('template.name', 'Prospection V1 bis (copie)');

        $this->postJson('/api/templates/'.$templateId.'/archive')
            ->assertOk()
            ->assertJsonPath('template.active', false);
    }

    public function test_drafts_support_crud_duplicate_schedule_and_unschedule(): void
    {
        [$contact, $primaryEmail] = $this->seedContacts();

        $draftResponse = $this->postJson('/api/drafts', [
            'type' => 'bulk',
            'subject' => 'Campagne Avril',
            'htmlBody' => '<p>Bonjour</p>',
            'textBody' => 'Bonjour',
            'recipients' => [
                [
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'email' => $primaryEmail->email,
                ],
            ],
        ])->assertCreated();

        $draftId = $draftResponse->json('draft.id');

        $this->getJson('/api/drafts/'.$draftId)
            ->assertOk()
            ->assertJsonPath('draft.type', 'multiple')
            ->assertJsonPath('draft.recipientCount', 1);

        $this->putJson('/api/drafts/'.$draftId, [
            'type' => 'bulk',
            'subject' => 'Campagne Avril relance',
            'htmlBody' => '<p>Relance</p>',
            'textBody' => 'Relance',
            'recipients' => [
                [
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'email' => $primaryEmail->email,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('draft.subject', 'Campagne Avril relance');

        $this->postJson('/api/drafts/'.$draftId.'/duplicate')
            ->assertCreated()
            ->assertJsonPath('draft.subject', 'Campagne Avril relance (copie)')
            ->assertJsonPath('draft.status', 'draft');

        $this->postJson('/api/drafts/'.$draftId.'/schedule', [
            'scheduledAt' => '2026-03-20 09:30:00',
            'name' => 'Campagne Avril Batch 1',
        ])->assertOk()
            ->assertJsonPath('draft.status', 'scheduled')
            ->assertJsonPath('campaign.status', 'scheduled')
            ->assertJsonPath('campaign.recipientCount', 1)
            ->assertJsonPath('preflight.ok', true);

        $this->postJson('/api/drafts/'.$draftId.'/unschedule')
            ->assertOk()
            ->assertJsonPath('draft.status', 'draft')
            ->assertJsonPath('campaign.status', 'draft');
    }

    public function test_preflight_reports_warnings_opt_outs_invalids_and_attachment_weight(): void
    {
        [$contact, $primaryEmail, $optOutEmail, $bouncedEmail] = $this->seedContacts(includeFlags: true);

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'bulk',
            'subject' => 'Preflight test',
            'html_body' => '<p><img src="https://cdn.test/image.png"></p><a href="https://a.test">1</a><a href="https://b.test">2</a><a href="https://c.test">3</a>',
            'text_body' => null,
            'payload_json' => [
                'recipients' => [
                    ['contactId' => $contact->id, 'contactEmailId' => $primaryEmail->id],
                    ['contactId' => $contact->id, 'contactEmailId' => $optOutEmail->id],
                    ['contactId' => $contact->id, 'contactEmailId' => $bouncedEmail->id],
                    ['email' => 'not-an-email'],
                ],
            ],
            'status' => 'draft',
        ]);

        MailAttachment::query()->create([
            'draft_id' => $draft->id,
            'original_name' => 'brochure.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 6 * 1024 * 1024,
            'storage_disk' => 'local',
            'storage_path' => 'mail/brochure.pdf',
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'deliverability'],
            [
                'value_json' => [
                    'tracking_opens_enabled' => true,
                    'tracking_clicks_enabled' => true,
                    'max_links_warning_threshold' => 2,
                    'max_remote_images_warning_threshold' => 0,
                    'html_size_warning_kb' => 1,
                    'attachment_size_warning_mb' => 5,
                ],
            ],
        );

        $this->postJson('/api/drafts/'.$draft->id.'/preflight')
            ->assertOk()
            ->assertJsonPath('preflight.ok', true)
            ->assertJsonPath('preflight.mailboxValid', true)
            ->assertJsonPath('preflight.hasTextVersion', false)
            ->assertJsonPath('preflight.hasRemoteImages', true)
            ->assertJsonPath('preflight.recipientSummary.total', 4)
            ->assertJsonPath('preflight.recipientSummary.deliverable', 1)
            ->assertJsonPath('preflight.recipientSummary.excluded', 2)
            ->assertJsonPath('preflight.recipientSummary.optOut', 1)
            ->assertJsonPath('preflight.recipientSummary.invalid', 1)
            ->assertJsonCount(6, 'preflight.warnings');
    }

    public function test_preflight_blocks_scheduling_when_no_deliverable_recipient_exists(): void
    {
        [$contact, $primaryEmail] = $this->seedContacts(includeFlags: true);

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'bulk',
            'subject' => 'Invalid audience',
            'html_body' => '<p>Bonjour</p>',
            'text_body' => 'Bonjour',
            'payload_json' => [
                'recipients' => [
                    ['contactId' => $contact->id, 'contactEmailId' => $primaryEmail->id + 1],
                    ['email' => 'bad-email'],
                ],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/schedule', [
            'scheduledAt' => '2026-03-20 09:30:00',
        ])->assertUnprocessable();
    }

    public function test_campaign_can_be_created_from_draft_and_listed_with_progress_shape(): void
    {
        [$contact, $primaryEmail] = $this->seedContacts();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'bulk',
            'subject' => 'Campaign source',
            'html_body' => '<p>Bonjour</p>',
            'text_body' => 'Bonjour',
            'payload_json' => [
                'recipients' => [
                    [
                        'contactId' => $contact->id,
                        'contactEmailId' => $primaryEmail->id,
                        'organizationId' => $contact->organization_id,
                        'email' => $primaryEmail->email,
                    ],
                ],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/campaign', [
            'name' => 'Campaign source batch',
        ])->assertCreated()
            ->assertJsonPath('campaign.name', 'Campaign source batch')
            ->assertJsonPath('campaign.recipientCount', 1)
            ->assertJsonPath('preflight.ok', true);

        $this->getJson('/api/campaigns')
            ->assertOk()
            ->assertJsonPath('campaigns.0.name', 'Campaign source batch')
            ->assertJsonPath('campaigns.0.progressPercent', 0)
            ->assertJsonPath('campaigns.0.recipientCount', 1)
            ->assertJsonPath('campaigns.0.openCount', 0)
            ->assertJsonPath('campaigns.0.replyCount', 0)
            ->assertJsonPath('campaigns.0.bounceCount', 0);
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
            [
                'value_json' => [
                    'global_signature_html' => '<p>Cordialement,<br>AEGIS</p>',
                    'global_signature_text' => "Cordialement,\nAEGIS",
                    'send_window_start' => '09:00',
                    'send_window_end' => '18:00',
                ],
            ],
        );
    }

    private function seedContacts(bool $includeFlags = false): array
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

        $optOutEmail = ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice-optout@acme.test',
            'opt_out_at' => $includeFlags ? Carbon::parse('2026-03-15 09:00:00') : null,
        ]);

        $bouncedEmail = ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice-bounce@acme.test',
            'bounce_status' => $includeFlags ? 'hard_bounced' : null,
        ]);

        return [$contact, $primaryEmail, $optOutEmail, $bouncedEmail];
    }
}
