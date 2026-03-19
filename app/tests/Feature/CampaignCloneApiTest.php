<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailAttachment;
use App\Models\MailCampaign;
use App\Models\MailDraft;
use App\Models\MailEvent;
use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Models\MailboxAccount;
use App\Models\MailThread;
use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignCloneApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_clone_completed_campaign_creates_new_draft_campaign(): void
    {
        [$campaign, $draft] = $this->seedCompletedCampaign();

        $response = $this->postJson("/api/campaigns/{$campaign->id}/clone");

        $response->assertCreated()
            ->assertJsonPath('campaign.status', 'draft')
            ->assertJsonPath('message', 'Campagne clonée avec succès.');

        $newCampaignId = $response->json('campaign.id');

        $this->assertNotEquals($campaign->id, $newCampaignId);
        $this->assertDatabaseHas('mail_campaigns', [
            'id' => $newCampaignId,
            'status' => 'draft',
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function test_cloned_campaign_name_has_copy_suffix(): void
    {
        [$campaign] = $this->seedCompletedCampaign();

        $response = $this->postJson("/api/campaigns/{$campaign->id}/clone");

        $response->assertCreated()
            ->assertJsonPath('campaign.name', $campaign->name.' (copie)');
    }

    public function test_cloned_campaign_preserves_content_parameters(): void
    {
        [$campaign, $draft] = $this->seedCompletedCampaign();

        $response = $this->postJson("/api/campaigns/{$campaign->id}/clone");

        $newCampaignId = $response->json('campaign.id');
        $newCampaign = MailCampaign::query()->with('draft')->findOrFail($newCampaignId);

        // Campaign parameters preserved
        $this->assertSame($campaign->mode, $newCampaign->mode);
        $this->assertSame($campaign->mailbox_account_id, $newCampaign->mailbox_account_id);
        $this->assertEquals($campaign->send_window_json, $newCampaign->send_window_json);
        $this->assertEquals($campaign->throttling_json, $newCampaign->throttling_json);

        // Draft content preserved
        $newDraft = $newCampaign->draft;
        $this->assertNotNull($newDraft);
        $this->assertNotEquals($draft->id, $newDraft->id);
        $this->assertSame($draft->subject, $newDraft->subject);
        $this->assertSame($draft->html_body, $newDraft->html_body);
        $this->assertSame($draft->text_body, $newDraft->text_body);
        $this->assertSame($draft->template_id, $newDraft->template_id);
        $this->assertSame($draft->signature_snapshot, $newDraft->signature_snapshot);
        $this->assertSame('draft', $newDraft->status);
        $this->assertNull($newDraft->scheduled_at);
    }

    public function test_cloned_campaign_preserves_audience_logic(): void
    {
        [$campaign, $draft] = $this->seedCompletedCampaign();

        $response = $this->postJson("/api/campaigns/{$campaign->id}/clone");

        $newCampaignId = $response->json('campaign.id');
        $newDraft = MailCampaign::query()->findOrFail($newCampaignId)->draft;

        // Audience logic from payload_json is preserved
        $this->assertEquals(
            $draft->payload_json['recipients'],
            $newDraft->payload_json['recipients'],
        );
    }

    public function test_cloned_campaign_does_not_copy_execution_history(): void
    {
        [$campaign] = $this->seedCompletedCampaign();

        // Verify source has execution data
        $this->assertTrue($campaign->recipients()->exists());
        $this->assertTrue(MailEvent::query()->where('campaign_id', $campaign->id)->exists());

        $response = $this->postJson("/api/campaigns/{$campaign->id}/clone");

        $newCampaignId = $response->json('campaign.id');

        // New campaign has zero recipients (will be regenerated on schedule)
        $this->assertSame(0, MailRecipient::query()->where('campaign_id', $newCampaignId)->count());

        // New campaign has no events except the clone event
        $cloneEvents = MailEvent::query()->where('campaign_id', $newCampaignId)->get();
        $this->assertTrue($cloneEvents->every(fn ($e) => $e->event_type === 'mail_campaign.cloned'));

        // New campaign has no messages
        $newRecipientIds = MailRecipient::query()->where('campaign_id', $newCampaignId)->pluck('id');
        $this->assertSame(0, MailMessage::query()->whereIn('recipient_id', $newRecipientIds)->count());
    }

    public function test_cloned_campaign_does_not_copy_metrics_or_dates(): void
    {
        [$campaign] = $this->seedCompletedCampaign();

        $response = $this->postJson("/api/campaigns/{$campaign->id}/clone");

        $newCampaignId = $response->json('campaign.id');
        $newCampaign = MailCampaign::query()->findOrFail($newCampaignId);

        $this->assertSame('draft', $newCampaign->status);
        $this->assertNull($newCampaign->started_at);
        $this->assertNull($newCampaign->completed_at);

        // Serialized response should reflect clean state
        $this->assertSame(0, $response->json('campaign.progressPercent'));
        $this->assertSame(0, $response->json('campaign.openCount'));
        $this->assertSame(0, $response->json('campaign.replyCount'));
        $this->assertSame(0, $response->json('campaign.bounceCount'));
    }

    public function test_cloned_campaign_creates_independent_object(): void
    {
        [$campaign] = $this->seedCompletedCampaign();

        $response = $this->postJson("/api/campaigns/{$campaign->id}/clone");

        $newCampaignId = $response->json('campaign.id');
        $newCampaign = MailCampaign::query()->findOrFail($newCampaignId);

        // Source campaign is unchanged
        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertNotNull($campaign->started_at);
        $this->assertNotNull($campaign->completed_at);

        // Different draft IDs
        $this->assertNotEquals($campaign->draft_id, $newCampaign->draft_id);
    }

    public function test_cloned_campaign_copies_attachments(): void
    {
        [$campaign, $draft] = $this->seedCompletedCampaign();

        // Add an attachment to the source draft
        MailAttachment::query()->create([
            'draft_id' => $draft->id,
            'original_name' => 'brochure.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'storage_disk' => 'local',
            'storage_path' => 'attachments/brochure.pdf',
            'content_id' => null,
            'disposition' => 'attachment',
        ]);

        $response = $this->postJson("/api/campaigns/{$campaign->id}/clone");

        $newCampaignId = $response->json('campaign.id');
        $newDraft = MailCampaign::query()->findOrFail($newCampaignId)->draft;

        $newAttachments = MailAttachment::query()->where('draft_id', $newDraft->id)->get();
        $this->assertCount(1, $newAttachments);
        $this->assertSame('brochure.pdf', $newAttachments->first()->original_name);
        $this->assertSame('application/pdf', $newAttachments->first()->mime_type);
    }

    public function test_clone_nonexistent_campaign_returns_404(): void
    {
        $this->seedMailboxAndSettings();

        $this->postJson('/api/campaigns/99999/clone')
            ->assertNotFound();
    }

    public function test_cloned_campaign_can_be_edited_normally(): void
    {
        [$campaign] = $this->seedCompletedCampaign();

        $cloneResponse = $this->postJson("/api/campaigns/{$campaign->id}/clone");
        $newCampaignId = $cloneResponse->json('campaign.id');
        $newDraftId = $cloneResponse->json('campaign.draftId');

        // The cloned campaign's draft should be updatable via autosave
        $this->postJson('/api/campaigns/autosave', [
            'campaignId' => $newCampaignId,
            'draftId' => $newDraftId,
            'name' => 'Campagne modifiée',
            'type' => 'bulk',
            'subject' => 'Nouveau sujet',
            'textBody' => 'Nouveau contenu',
            'recipients' => [['email' => 'alice@acme.test']],
        ])->assertOk();

        $updatedCampaign = MailCampaign::query()->findOrFail($newCampaignId);
        $this->assertSame('Campagne modifiée', $updatedCampaign->name);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function seedMailboxAndSettings(): MailboxAccount
    {
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

        Setting::query()->updateOrCreate(
            ['key' => 'general'],
            [
                'value_json' => config('mailing.defaults.general', []),
            ],
        );

        return $mailbox;
    }

    private function seedCompletedCampaign(): array
    {
        $mailbox = $this->seedMailboxAndSettings();

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

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'mode' => 'bulk',
            'template_id' => null,
            'subject' => 'Campagne Avril',
            'html_body' => '<p>Bonjour {{first_name}}</p>',
            'text_body' => 'Bonjour {{first_name}}',
            'signature_snapshot' => '<p>Cordialement</p>',
            'payload_json' => [
                'recipients' => [
                    [
                        'contactId' => $contact->id,
                        'contactEmailId' => $primaryEmail->id,
                        'organizationId' => $organization->id,
                        'email' => 'alice@acme.test',
                        'name' => 'Alice Martin',
                    ],
                ],
            ],
            'status' => 'scheduled',
            'scheduled_at' => '2026-03-15 09:00:00',
        ]);

        $campaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'name' => 'Campagne Avril',
            'mode' => 'bulk',
            'draft_id' => $draft->id,
            'status' => 'sent',
            'send_window_json' => ['start' => '09:00', 'end' => '18:00'],
            'throttling_json' => ['dailyLimit' => 50, 'hourlyLimit' => 10],
            'last_edited_at' => '2026-03-15 08:00:00',
            'started_at' => '2026-03-15 09:00:00',
            'completed_at' => '2026-03-15 09:05:00',
        ]);

        $thread = MailThread::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'public_uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'subject_canonical' => 'campagne avril',
            'first_message_at' => '2026-03-15 09:00:00',
            'last_message_at' => '2026-03-15 09:00:00',
            'last_direction' => 'out',
        ]);

        $recipient = MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'contact_email_id' => $primaryEmail->id,
            'email' => 'alice@acme.test',
            'status' => 'opened',
            'sent_at' => '2026-03-15 09:00:00',
            'last_event_at' => '2026-03-15 10:00:00',
        ]);

        MailMessage::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'thread_id' => $thread->id,
            'recipient_id' => $recipient->id,
            'direction' => 'outbound',
            'message_id_header' => '<msg-001@aegis.test>',
            'from_email' => 'ops@aegis.test',
            'to_emails' => ['alice@acme.test'],
            'subject' => 'Campagne Avril',
            'text_body' => 'Bonjour Alice',
            'aegis_tracking_id' => 'track-123',
            'sent_at' => '2026-03-15 09:00:00',
            'opened_first_at' => '2026-03-15 10:00:00',
        ]);

        MailEvent::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient->id,
            'event_type' => 'mail_message.sent',
            'event_payload' => ['email' => 'alice@acme.test'],
            'occurred_at' => '2026-03-15 09:00:00',
            'created_at' => '2026-03-15 09:00:00',
        ]);

        MailEvent::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient->id,
            'event_type' => 'mail_message.opened',
            'event_payload' => ['email' => 'alice@acme.test'],
            'occurred_at' => '2026-03-15 10:00:00',
            'created_at' => '2026-03-15 10:00:00',
        ]);

        return [$campaign, $draft];
    }
}
