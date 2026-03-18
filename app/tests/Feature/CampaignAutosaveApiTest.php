<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailboxAccount;
use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CampaignAutosaveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_autosave_creates_internal_draft_and_campaign_without_manual_save_action(): void
    {
        [$contact, $primaryEmail] = $this->seedAudience();

        $response = $this->postJson('/api/campaigns/autosave', [
            'name' => 'Campagne autosave',
            'type' => 'bulk',
            'subject' => 'Bonjour Alice',
            'htmlBody' => '<p>Bonjour Alice</p>',
            'textBody' => 'Bonjour Alice',
            'recipients' => [
                [
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'organizationName' => 'Acme',
                    'email' => $primaryEmail->email,
                    'name' => 'Alice Martin',
                ],
            ],
        ]);

        $campaignId = $response->assertCreated()
            ->assertJsonPath('draft.status', 'draft')
            ->assertJsonPath('campaign.status', 'draft')
            ->assertJsonPath('campaign.recipientCount', 1)
            ->json('campaign.id');

        $this->assertNotNull($response->json('campaign.lastEditedAt'));

        $this->assertDatabaseHas('mail_campaigns', [
            'id' => $campaignId,
            'name' => 'Campagne autosave',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('mail_drafts', [
            'id' => $response->json('draft.id'),
            'subject' => 'Bonjour Alice',
            'status' => 'draft',
        ]);
    }

    public function test_campaign_autosave_updates_existing_campaign_and_rejects_stale_writes(): void
    {
        [$contact, $primaryEmail] = $this->seedAudience();

        $created = $this->postJson('/api/campaigns/autosave', [
            'name' => 'Campagne autosave',
            'type' => 'bulk',
            'subject' => 'Version 1',
            'htmlBody' => '<p>Version 1</p>',
            'textBody' => 'Version 1',
            'recipients' => [
                [
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'organizationName' => 'Acme',
                    'email' => $primaryEmail->email,
                    'name' => 'Alice Martin',
                ],
            ],
        ])->assertCreated();

        $campaignId = $created->json('campaign.id');
        $draftId = $created->json('draft.id');
        $expectedUpdatedAt = $created->json('campaign.lastEditedAt');

        $updated = $this->postJson('/api/campaigns/autosave', [
            'campaignId' => $campaignId,
            'draftId' => $draftId,
            'expectedUpdatedAt' => $expectedUpdatedAt,
            'name' => 'Campagne autosave V2',
            'type' => 'bulk',
            'subject' => 'Version 2',
            'htmlBody' => '<p>Version 2</p>',
            'textBody' => 'Version 2',
            'recipients' => [
                [
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'organizationName' => 'Acme',
                    'email' => $primaryEmail->email,
                    'name' => 'Alice Martin',
                ],
            ],
        ]);

        $updated->assertOk()
            ->assertJsonPath('campaign.id', $campaignId)
            ->assertJsonPath('campaign.name', 'Campagne autosave V2')
            ->assertJsonPath('draft.subject', 'Version 2');

        $this->postJson('/api/campaigns/autosave', [
            'campaignId' => $campaignId,
            'draftId' => $draftId,
            'expectedUpdatedAt' => '2026-03-15T10:00:00+01:00',
            'name' => 'Version stale',
            'type' => 'bulk',
            'subject' => 'Version stale',
            'htmlBody' => '<p>Version stale</p>',
            'textBody' => 'Version stale',
            'recipients' => [
                [
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'organizationName' => 'Acme',
                    'email' => $primaryEmail->email,
                    'name' => 'Alice Martin',
                ],
            ],
        ])->assertStatus(409)
            ->assertJsonPath('message', 'Une version plus récente de la campagne existe déjà. Rechargez la page avant de continuer.');
    }

    public function test_campaign_create_page_exposes_audiences_and_autosave_contract(): void
    {
        [$contact] = $this->seedAudience();

        $this->get('/campaigns/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Create')
                ->where('autosave.endpoint', '/api/campaigns/autosave')
                ->has('audiences.contacts', 1)
                ->where('audiences.contacts.0.email', 'alice@acme.test')
                ->where('audiences.organizations.0.organizationName', 'Acme')
            );
    }

    private function seedAudience(): array
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

        $organization = Organization::query()->create([
            'name' => 'Acme',
            'domain' => 'acme.test',
        ]);

        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'full_name' => 'Alice Martin',
        ]);

        $primaryEmail = ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        return [$contact, $primaryEmail];
    }
}
