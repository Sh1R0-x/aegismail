<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailAttachment;
use App\Models\MailMessage;
use App\Models\MailDraft;
use App\Models\MailRecipient;
use App\Models\MailboxAccount;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\Mailing\Contracts\MailGatewayClient;
use App\Services\Mailing\Outbound\OutboundMailService;
use App\Jobs\Mailing\DispatchMailMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ComposerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

        $this->postJson('/api/templates/'.$templateId.'/activate')
            ->assertOk()
            ->assertJsonPath('template.active', true);

        $this->postJson('/api/templates', [
            'name' => 'Template texte seul',
            'subject' => 'Bonjour texte',
            'htmlBody' => null,
            'textBody' => 'Bonjour en texte brut',
        ])->assertCreated()
            ->assertJsonPath('template.htmlBody', null)
            ->assertJsonPath('template.textBody', 'Bonjour en texte brut');
    }

    public function test_templates_require_at_least_one_text_or_html_body(): void
    {
        $this->seedMailboxAndSettings();

        $response = $this->postJson('/api/templates', [
            'name' => 'Template vide',
            'subject' => 'Sans contenu',
            'htmlBody' => null,
            'textBody' => null,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['textBody']);

        $this->assertStringContainsString('Ajoutez au moins une version texte ou HTML au modèle.', $response->json('message'));
    }

    public function test_drafts_support_crud_duplicate_schedule_and_unschedule(): void
    {
        [$contact, $primaryEmail] = $this->seedContacts();
        Queue::fake();

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

        Queue::assertPushedOn(config('mailing.queues.outbound'), DispatchMailMessageJob::class);
        $this->assertDatabaseHas('mail_recipients', [
            'campaign_id' => 1,
            'email' => 'alice@acme.test',
            'status' => 'queued',
        ]);
        $this->assertDatabaseCount('mail_messages', 1);

        $this->postJson('/api/drafts/'.$draftId.'/unschedule')
            ->assertOk()
            ->assertJsonPath('draft.status', 'draft')
            ->assertJsonPath('campaign.status', 'draft');
    }

    public function test_draft_can_be_deleted_when_no_send_history_exists(): void
    {
        $this->seedMailboxAndSettings();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Delete me',
            'html_body' => null,
            'text_body' => 'Bonjour',
            'payload_json' => [],
            'status' => 'draft',
        ]);

        $this->deleteJson('/api/drafts/'.$draft->id)
            ->assertOk()
            ->assertJsonPath('message', 'Brouillon supprimé.');

        $this->assertDatabaseMissing('mail_drafts', ['id' => $draft->id]);
    }

    public function test_drafts_support_bulk_delete(): void
    {
        $this->seedMailboxAndSettings();

        $draftA = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Bulk A',
            'html_body' => null,
            'text_body' => 'Bonjour A',
            'payload_json' => [],
            'status' => 'draft',
        ]);

        $draftB = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'bulk',
            'subject' => 'Bulk B',
            'html_body' => null,
            'text_body' => 'Bonjour B',
            'payload_json' => [],
            'status' => 'draft',
        ]);

        $this->deleteJson('/api/drafts', [
            'ids' => [$draftA->id, $draftB->id],
        ])->assertOk()
            ->assertJsonPath('deletedCount', 2)
            ->assertJsonPath('message', '2 brouillons supprimés.');

        $this->assertDatabaseMissing('mail_drafts', ['id' => $draftA->id]);
        $this->assertDatabaseMissing('mail_drafts', ['id' => $draftB->id]);
    }

    public function test_unscheduled_campaign_does_not_dispatch_when_delayed_job_runs_later(): void
    {
        [$contact, $primaryEmail] = $this->seedContacts();
        Queue::fake();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Delayed unschedule safeguard',
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

        $this->postJson('/api/drafts/'.$draft->id.'/schedule', [
            'scheduledAt' => '2026-03-20 12:00:00',
        ])->assertOk();

        $message = MailMessage::query()->firstOrFail();

        $this->postJson('/api/drafts/'.$draft->id.'/unschedule')
            ->assertOk()
            ->assertJsonPath('draft.status', 'draft')
            ->assertJsonPath('campaign.status', 'draft');

        app(OutboundMailService::class)->dispatchQueuedMessage([
            'mail_message_id' => $message->id,
            'idempotency_key' => 'dispatch.'.$message->id,
        ], app(MailGatewayClient::class));

        $this->assertNull($message->fresh()->sent_at);
        $this->assertDatabaseHas('mail_recipients', [
            'id' => $message->recipient_id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('mail_events', [
            'event_type' => 'mail_message.dispatch_skipped',
        ]);
        $this->assertDatabaseMissing('mail_events', [
            'event_type' => 'mail_message.sent',
            'message_id' => $message->id,
        ]);
    }

    public function test_scheduling_respects_send_window_and_daily_ceiling(): void
    {
        [$contact, $primaryEmail, $optOutEmail, $bouncedEmail] = $this->seedContacts();
        Queue::fake();

        Setting::query()->updateOrCreate(
            ['key' => 'general'],
            [
                'value_json' => array_replace(config('mailing.defaults.general', []), [
                    'daily_limit_default' => 1,
                    'hourly_limit_default' => 1,
                    'min_delay_seconds' => 60,
                    'jitter_min_seconds' => 5,
                    'jitter_max_seconds' => 20,
                    'slow_mode_enabled' => true,
                ]),
            ],
        );

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

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'bulk',
            'subject' => 'Cadence test',
            'html_body' => '<p>Bonjour</p>',
            'text_body' => 'Bonjour',
            'payload_json' => [
                'recipients' => [
                    ['contactId' => $contact->id, 'contactEmailId' => $primaryEmail->id, 'organizationId' => $contact->organization_id, 'email' => $primaryEmail->email],
                    ['email' => 'second@acme.test'],
                    ['email' => 'third@acme.test'],
                ],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/schedule', [
            'scheduledAt' => '2026-03-20 08:00:00',
        ])->assertOk();

        $scheduled = MailRecipient::query()->orderBy('scheduled_for')->get()->pluck('scheduled_for')->map->format('Y-m-d H:i:s')->all();

        $this->assertSame([
            '2026-03-20 09:00:00',
            '2026-03-21 09:00:00',
            '2026-03-22 09:00:00',
        ], $scheduled);
        Queue::assertPushed(DispatchMailMessageJob::class, 3);
    }

    public function test_scheduling_respects_hourly_ceiling_min_delay_and_slow_mode(): void
    {
        [$contact, $primaryEmail] = $this->seedContacts();
        Queue::fake();

        Setting::query()->updateOrCreate(
            ['key' => 'general'],
            [
                'value_json' => array_replace(config('mailing.defaults.general', []), [
                    'daily_limit_default' => 10,
                    'hourly_limit_default' => 1,
                    'min_delay_seconds' => 60,
                    'jitter_min_seconds' => 5,
                    'jitter_max_seconds' => 20,
                    'slow_mode_enabled' => true,
                ]),
            ],
        );

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'bulk',
            'subject' => 'Hourly cadence test',
            'html_body' => '<p>Bonjour</p>',
            'text_body' => 'Bonjour',
            'payload_json' => [
                'recipients' => [
                    ['contactId' => $contact->id, 'contactEmailId' => $primaryEmail->id, 'organizationId' => $contact->organization_id, 'email' => $primaryEmail->email],
                    ['email' => 'second@acme.test'],
                    ['email' => 'third@acme.test'],
                ],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/schedule', [
            'scheduledAt' => '2026-03-20 09:00:00',
        ])->assertOk();

        $scheduled = MailRecipient::query()->orderBy('scheduled_for')->get()->pluck('scheduled_for')->map->format('Y-m-d H:i:s')->all();

        $this->assertSame([
            '2026-03-20 09:00:00',
            '2026-03-20 10:00:00',
            '2026-03-20 11:00:00',
        ], $scheduled);
    }

    public function test_schedule_immediately_persists_sent_message_message_id_and_events(): void
    {
        Carbon::setTestNow('2026-03-20 10:00:00');
        [$contact, $primaryEmail] = $this->seedContacts();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Send now',
            'html_body' => '<p>Bonjour</p>',
            'text_body' => 'Bonjour',
            'payload_json' => [
                'recipients' => [
                    ['contactId' => $contact->id, 'contactEmailId' => $primaryEmail->id, 'organizationId' => $contact->organization_id, 'email' => $primaryEmail->email],
                ],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/schedule', [
            'scheduledAt' => now()->subMinute()->toDateTimeString(),
        ])->assertOk();

        $message = MailMessage::query()->firstOrFail();

        $this->assertNotNull($message->sent_at);
        $this->assertNotNull($message->aegis_tracking_id);
        $this->assertStringStartsWith('<', $message->message_id_header);
        $this->assertDatabaseHas('mail_recipients', [
            'id' => $message->recipient_id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'mail_message.queued']);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'mail_message.sent']);
    }

    public function test_scheduling_uses_first_real_slot_to_keep_campaign_scheduled_until_window_opens(): void
    {
        Carbon::setTestNow('2026-03-20 08:30:00');
        [$contact, $primaryEmail] = $this->seedContacts();
        Queue::fake();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Window aware',
            'html_body' => '<p>Bonjour</p>',
            'text_body' => 'Bonjour',
            'payload_json' => [
                'recipients' => [
                    ['contactId' => $contact->id, 'contactEmailId' => $primaryEmail->id, 'organizationId' => $contact->organization_id, 'email' => $primaryEmail->email],
                ],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/schedule', [
            'scheduledAt' => '2026-03-20 08:00:00',
        ])->assertOk()
            ->assertJsonPath('campaign.status', 'scheduled')
            ->assertJsonPath('campaign.scheduledAt', '2026-03-20 09:00');
    }

    public function test_gateway_returned_headers_and_message_id_are_persisted_for_future_threading(): void
    {
        Carbon::setTestNow('2026-03-20 10:00:00');
        [$contact, $primaryEmail] = $this->seedContacts();

        $this->app->bind(MailGatewayClient::class, fn () => new class implements MailGatewayClient
        {
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
                return [
                    'success' => true,
                    'driver' => 'test',
                    'message' => 'accepted',
                    'accepted_at' => Carbon::now()->toIso8601String(),
                    'message_id_header' => '<gateway-message-id@ovh.test>',
                    'headers_json' => [
                        'Message-ID' => '<gateway-message-id@ovh.test>',
                        'X-Transport' => 'smtp-test',
                    ],
                ];
            }

            public function syncMailbox(array $payload): array
            {
                return ['success' => true, 'driver' => 'test', 'message' => 'ok', 'messages' => []];
            }
        });

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Gateway headers',
            'html_body' => '<p>Bonjour</p>',
            'text_body' => 'Bonjour',
            'payload_json' => [
                'recipients' => [
                    ['contactId' => $contact->id, 'contactEmailId' => $primaryEmail->id, 'organizationId' => $contact->organization_id, 'email' => $primaryEmail->email],
                ],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/schedule', [
            'scheduledAt' => now()->subMinute()->toDateTimeString(),
        ])->assertOk();

        $message = MailMessage::query()->firstOrFail();

        $this->assertSame('<gateway-message-id@ovh.test>', $message->message_id_header);
        $this->assertSame('<gateway-message-id@ovh.test>', $message->headers_json['Message-ID'] ?? null);
        $this->assertSame('smtp-test', $message->headers_json['X-Transport'] ?? null);
        $this->assertSame('test', $message->headers_json['gateway']['driver'] ?? null);
    }

    public function test_schedule_immediately_persists_failed_message_and_failed_event(): void
    {
        Carbon::setTestNow('2026-03-20 10:00:00');
        $this->seedMailboxAndSettings();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'single',
            'subject' => 'Fail now',
            'html_body' => '<p>Bonjour</p>',
            'text_body' => 'Bonjour',
            'payload_json' => [
                'recipients' => [
                    ['email' => 'fail@acme.test'],
                ],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/schedule', [
            'scheduledAt' => now()->subMinute()->toDateTimeString(),
        ])->assertOk();

        $message = MailMessage::query()->firstOrFail();

        $this->assertNull($message->sent_at);
        $this->assertDatabaseHas('mail_recipients', [
            'id' => $message->recipient_id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'mail_message.failed']);
    }

    public function test_campaign_auto_stops_after_simple_failure_threshold(): void
    {
        Carbon::setTestNow('2026-03-20 10:00:00');
        $this->seedMailboxAndSettings();

        Setting::query()->updateOrCreate(
            ['key' => 'general'],
            [
                'value_json' => array_replace(config('mailing.defaults.general', []), [
                    'stop_on_consecutive_failures' => 1,
                    'stop_on_hard_bounce_threshold' => 3,
                ]),
            ],
        );

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'bulk',
            'subject' => 'Auto stop',
            'html_body' => '<p>Bonjour</p>',
            'text_body' => 'Bonjour',
            'payload_json' => [
                'recipients' => [
                    ['email' => 'fail@acme.test'],
                    ['email' => 'second@acme.test'],
                ],
            ],
            'status' => 'draft',
        ]);

        $this->postJson('/api/drafts/'.$draft->id.'/schedule', [
            'scheduledAt' => now()->subMinute()->toDateTimeString(),
        ])->assertOk();

        $this->assertDatabaseHas('mail_recipients', ['email' => 'fail@acme.test', 'status' => 'failed']);
        $this->assertDatabaseHas('mail_recipients', ['email' => 'second@acme.test', 'status' => 'cancelled']);
        $this->assertDatabaseHas('mail_events', ['event_type' => 'mail_campaign.auto_stopped']);
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
            ->assertJsonPath('preflight.recipientSummary.excluded', 1)
            ->assertJsonPath('preflight.recipientSummary.optOut', 1)
            ->assertJsonPath('preflight.recipientSummary.invalid', 1)
            ->assertJsonCount(1, 'preflight.deliverableRecipients')
            ->assertJsonCount(1, 'preflight.excludedRecipients')
            ->assertJsonCount(1, 'preflight.optOutRecipients')
            ->assertJsonCount(1, 'preflight.invalidRecipients')
            ->assertJsonPath('preflight.excludedRecipients.0.reason', 'hard_bounced')
            ->assertJsonPath('preflight.optOutRecipients.0.reason', 'opt_out')
            ->assertJsonPath('preflight.invalidRecipients.0.reason', 'invalid_email')
            ->assertJsonCount(6, 'preflight.warnings');
    }

    public function test_preflight_blocks_empty_message_body_but_accepts_text_first_drafts(): void
    {
        [$contact, $primaryEmail] = $this->seedContacts();
        Queue::fake();

        $emptyDraft = $this->postJson('/api/drafts', [
            'type' => 'single',
            'subject' => 'Sans contenu',
            'htmlBody' => null,
            'textBody' => null,
            'recipients' => [
                [
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'email' => $primaryEmail->email,
                ],
            ],
        ])->assertCreated()->json('draft.id');

        $this->postJson('/api/drafts/'.$emptyDraft.'/preflight')
            ->assertOk()
            ->assertJsonPath('preflight.ok', false)
            ->assertJsonPath('preflight.errors.0.code', 'missing_message_body');

        $scheduleResponse = $this->postJson('/api/drafts/'.$emptyDraft.'/schedule', [
            'scheduledAt' => '2026-03-20 10:00:00',
        ]);

        $scheduleResponse->assertUnprocessable()
            ->assertJsonValidationErrors(['preflight']);

        $this->assertStringContainsString('Le message est vide.', $scheduleResponse->json('message'));

        $textFirstDraft = $this->postJson('/api/drafts', [
            'type' => 'single',
            'subject' => 'Texte d’abord',
            'htmlBody' => null,
            'textBody' => "Bonjour Alice,\nCeci est un test.",
            'recipients' => [
                [
                    'contactId' => $contact->id,
                    'contactEmailId' => $primaryEmail->id,
                    'organizationId' => $contact->organization_id,
                    'email' => $primaryEmail->email,
                ],
            ],
        ])->assertCreated()->json('draft.id');

        $this->postJson('/api/drafts/'.$textFirstDraft.'/preflight')
            ->assertOk()
            ->assertJsonPath('preflight.ok', true)
            ->assertJsonPath('preflight.hasTextVersion', true);

        $this->postJson('/api/drafts/'.$textFirstDraft.'/schedule', [
            'scheduledAt' => '2026-03-20 10:30:00',
        ])->assertOk();

        $message = MailMessage::query()
            ->where('subject', 'Texte d’abord')
            ->firstOrFail();

        $this->assertStringContainsString('<p>Bonjour Alice,<br>Ceci est un test.</p>', (string) $message->html_body);
        $this->assertStringContainsString('Bonjour Alice,', (string) $message->text_body);
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

    public function test_campaign_can_be_deleted_when_no_send_history_exists(): void
    {
        [$contact, $primaryEmail] = $this->seedContacts();

        $draft = MailDraft::query()->create([
            'mailbox_account_id' => MailboxAccount::query()->firstOrFail()->id,
            'mode' => 'bulk',
            'subject' => 'Delete campaign',
            'html_body' => null,
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

        $campaignId = $this->postJson('/api/drafts/'.$draft->id.'/campaign', [
            'name' => 'Delete campaign',
        ])->assertCreated()->json('campaign.id');

        $this->deleteJson('/api/campaigns/'.$campaignId)
            ->assertOk()
            ->assertJsonPath('message', 'Campagne supprimée.');

        $this->assertDatabaseMissing('mail_campaigns', ['id' => $campaignId]);
        $this->assertDatabaseMissing('mail_drafts', ['id' => $draft->id]);
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

        Setting::query()->updateOrCreate(
            ['key' => 'general'],
            [
                'value_json' => config('mailing.defaults.general', []),
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
