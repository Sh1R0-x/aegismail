<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailCampaign;
use App\Models\MailDraft;
use App\Models\MailMessage;
use App\Models\MailRecipient;
use App\Models\MailTemplate;
use App\Models\MailThread;
use App\Models\MailboxAccount;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SmokeTestSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::parse('2026-03-15 10:00:00');

        $user = User::factory()->create([
            'name' => 'Smoke User',
            'email' => 'smoke@example.com',
        ]);

        $this->seedSettings($user->id, $now);
        $mailbox = $this->seedMailbox($user->id, $now);

        $acme = Organization::query()->create([
            'name' => 'Acme Labs',
            'domain' => 'acme.test',
            'website' => 'https://acme.test',
        ]);

        $beta = Organization::query()->create([
            'name' => 'Beta Logistics',
            'domain' => 'beta.test',
            'website' => 'https://beta.test',
        ]);

        [$alice, $aliceEmail] = $this->seedContact($acme, 'Alice', 'Martin', 'alice@acme.test', $now->copy()->subHours(2));
        [$bruno, $brunoEmail] = $this->seedContact($beta, 'Bruno', 'Leroy', 'bruno@beta.test', $now->copy()->subDay());
        [$carla, $carlaEmail] = $this->seedContact($acme, 'Carla', 'Durand', 'carla@acme.test', $now->copy()->subHours(5), 'hard_bounced');

        $template = MailTemplate::query()->create([
            'name' => 'Prospection smoke',
            'slug' => 'prospection-smoke',
            'subject_template' => 'Bonjour {{first_name}}',
            'html_template' => '<p>Bonjour {{first_name}},</p><p>On peut échanger cette semaine ?</p>',
            'text_template' => "Bonjour {{first_name}},\nOn peut echanger cette semaine ?",
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $draftScheduled = MailDraft::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'user_id' => $user->id,
            'mode' => 'bulk',
            'template_id' => $template->id,
            'subject' => 'Relance Q2',
            'html_body' => '<p>Relance Q2</p>',
            'text_body' => 'Relance Q2',
            'signature_snapshot' => '<p>Cordialement,<br>AEGIS</p>',
            'payload_json' => [
                'recipients' => [
                    ['contactId' => $alice->id, 'contactEmailId' => $aliceEmail->id, 'organizationId' => $acme->id, 'email' => $aliceEmail->email],
                ],
            ],
            'status' => 'scheduled',
            'scheduled_at' => $now->copy()->addDay()->setTime(9, 30),
        ]);

        $campaignScheduled = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'user_id' => $user->id,
            'name' => 'Relance Q2 Batch',
            'mode' => 'bulk',
            'draft_id' => $draftScheduled->id,
            'status' => 'scheduled',
            'send_window_json' => ['start' => '09:00', 'end' => '18:00'],
            'throttling_json' => ['dailyLimit' => 100, 'hourlyLimit' => 12],
        ]);

        MailRecipient::query()->create([
            'campaign_id' => $campaignScheduled->id,
            'organization_id' => $acme->id,
            'contact_id' => $alice->id,
            'contact_email_id' => $aliceEmail->id,
            'email' => $aliceEmail->email,
            'status' => 'queued',
            'scheduled_for' => $now->copy()->addDay()->setTime(9, 30),
            'last_event_at' => $now->copy()->subMinutes(30),
        ]);

        $draftWorking = MailDraft::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'user_id' => $user->id,
            'mode' => 'single',
            'template_id' => null,
            'subject' => 'Brouillon a relire',
            'html_body' => '<p>Bonjour,</p><p>Je vous relance.</p>',
            'text_body' => 'Bonjour, je vous relance.',
            'signature_snapshot' => '<p>Cordialement,<br>AEGIS</p>',
            'payload_json' => [
                'recipients' => [
                    ['email' => 'lead@example.test', 'name' => 'Lead Demo'],
                ],
            ],
            'status' => 'draft',
        ]);

        $campaignHistory = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'user_id' => $user->id,
            'name' => 'Prospection Mars',
            'mode' => 'bulk',
            'draft_id' => $draftWorking->id,
            'status' => 'sent',
            'send_window_json' => ['start' => '09:00', 'end' => '18:00'],
            'throttling_json' => ['dailyLimit' => 100, 'hourlyLimit' => 12],
            'started_at' => $now->copy()->subDay(),
            'completed_at' => $now->copy()->subHours(1),
        ]);

        $recipientReply = MailRecipient::query()->create([
            'campaign_id' => $campaignHistory->id,
            'organization_id' => $acme->id,
            'contact_id' => $alice->id,
            'contact_email_id' => $aliceEmail->id,
            'email' => $aliceEmail->email,
            'status' => 'replied',
            'sent_at' => $now->copy()->subHours(3),
            'replied_at' => $now->copy()->subHours(2),
            'last_event_at' => $now->copy()->subHours(2),
        ]);

        $recipientAutoReply = MailRecipient::query()->create([
            'campaign_id' => $campaignHistory->id,
            'organization_id' => $beta->id,
            'contact_id' => $bruno->id,
            'contact_email_id' => $brunoEmail->id,
            'email' => $brunoEmail->email,
            'status' => 'auto_replied',
            'sent_at' => $now->copy()->subHours(4),
            'auto_replied_at' => $now->copy()->subHours(3),
            'last_event_at' => $now->copy()->subHours(3),
        ]);

        $recipientBounce = MailRecipient::query()->create([
            'campaign_id' => $campaignHistory->id,
            'organization_id' => $acme->id,
            'contact_id' => $carla->id,
            'contact_email_id' => $carlaEmail->id,
            'email' => $carlaEmail->email,
            'status' => 'hard_bounced',
            'sent_at' => $now->copy()->subHours(5),
            'bounced_at' => $now->copy()->subHours(4),
            'last_event_at' => $now->copy()->subHours(4),
        ]);

        $replyThread = $this->seedThread($mailbox->id, $acme->id, $alice->id, 'relance q2', true, false, 'replied', $now->copy()->subHours(2));
        $replyOutbound = $this->seedOutboundMessage($replyThread->id, $mailbox->id, $recipientReply->id, 'ops@aegis-mail.test', $aliceEmail->email, 'Relance Q2', $now->copy()->subHours(3));
        $this->seedInboundMessage($replyThread->id, $mailbox->id, $recipientReply->id, 'INBOX', 101, $aliceEmail->email, ['ops@aegis-mail.test'], 'Re: Relance Q2', 'human_reply', $now->copy()->subHours(2), $replyOutbound->message_id_header);

        $autoReplyThread = $this->seedThread($mailbox->id, $beta->id, $bruno->id, 'prospection mars', false, true, 'auto_reply', $now->copy()->subHours(3));
        $autoReplyOutbound = $this->seedOutboundMessage($autoReplyThread->id, $mailbox->id, $recipientAutoReply->id, 'ops@aegis-mail.test', $brunoEmail->email, 'Prospection Mars', $now->copy()->subHours(4));
        $this->seedInboundMessage($autoReplyThread->id, $mailbox->id, $recipientAutoReply->id, 'INBOX', 102, $brunoEmail->email, ['ops@aegis-mail.test'], 'Re: Prospection Mars', 'out_of_office', $now->copy()->subHours(3), $autoReplyOutbound->message_id_header);

        $bounceThread = $this->seedThread($mailbox->id, $acme->id, $carla->id, 'prospection bounce', false, false, 'hard_bounced', $now->copy()->subHours(4));
        $bounceOutbound = $this->seedOutboundMessage($bounceThread->id, $mailbox->id, $recipientBounce->id, 'ops@aegis-mail.test', $carlaEmail->email, 'Prospection Bounce', $now->copy()->subHours(5));
        $this->seedInboundMessage($bounceThread->id, $mailbox->id, $recipientBounce->id, 'INBOX', 103, 'mailer-daemon@mail.ovh.net', ['ops@aegis-mail.test'], 'Delivery failure', 'hard_bounce', $now->copy()->subHours(4), $bounceOutbound->message_id_header);
    }

    private function seedSettings(int $userId, Carbon $now): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'general'],
            [
                'value_json' => array_replace(config('mailing.defaults.general', []), [
                    'daily_limit_default' => 150,
                    'hourly_limit_default' => 20,
                    'min_delay_seconds' => 60,
                    'jitter_min_seconds' => 5,
                    'jitter_max_seconds' => 15,
                    'slow_mode_enabled' => false,
                ]),
                'updated_by' => $userId,
                'updated_at' => $now,
            ],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'deliverability'],
            [
                'value_json' => config('mailing.defaults.deliverability', []),
                'updated_by' => $userId,
                'updated_at' => $now,
            ],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'mail'],
            [
                'value_json' => [
                    'provider' => 'ovh_mx_plan',
                    'sender_email' => 'ops@aegis-mail.test',
                    'sender_name' => 'AEGIS Ops',
                    'global_signature_html' => '<p>Cordialement,<br>AEGIS</p>',
                    'global_signature_text' => "Cordialement,\nAEGIS",
                    'mailbox_username' => 'ops@aegis-mail.test',
                    'mailbox_password_configured' => true,
                    'imap_host' => 'imap.mail.ovh.net',
                    'imap_port' => 993,
                    'imap_secure' => true,
                    'smtp_host' => 'smtp.mail.ovh.net',
                    'smtp_port' => 465,
                    'smtp_secure' => true,
                    'sync_enabled' => true,
                    'send_enabled' => true,
                    'send_window_start' => '09:00',
                    'send_window_end' => '18:00',
                    'health_status' => 'healthy',
                    'health_message' => 'Ready for smoke validation.',
                    'last_sync_at' => $now->toIso8601String(),
                ],
                'updated_by' => $userId,
                'updated_at' => $now,
            ],
        );
    }

    private function seedMailbox(int $userId, Carbon $now): MailboxAccount
    {
        return MailboxAccount::query()->create([
            'user_id' => $userId,
            'provider' => 'ovh_mx_plan',
            'email' => 'ops@aegis-mail.test',
            'display_name' => 'AEGIS Ops',
            'username' => 'ops@aegis-mail.test',
            'password_encrypted' => 'super-secret-password',
            'imap_host' => 'imap.mail.ovh.net',
            'imap_port' => 993,
            'imap_secure' => true,
            'smtp_host' => 'smtp.mail.ovh.net',
            'smtp_port' => 465,
            'smtp_secure' => true,
            'sync_enabled' => true,
            'send_enabled' => true,
            'last_inbox_uid' => 103,
            'last_sent_uid' => 0,
            'last_sync_at' => $now,
            'health_status' => 'healthy',
            'health_message' => 'Smoke mailbox ready.',
        ]);
    }

    private function seedContact(
        Organization $organization,
        string $firstName,
        string $lastName,
        string $email,
        Carbon $lastSeenAt,
        ?string $bounceStatus = null,
    ): array {
        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => trim($firstName.' '.$lastName),
            'job_title' => 'Prospect',
            'status' => 'active',
        ]);

        $contactEmail = ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => $email,
            'is_primary' => true,
            'bounce_status' => $bounceStatus,
            'last_seen_at' => $lastSeenAt,
        ]);

        return [$contact, $contactEmail];
    }

    private function seedThread(
        int $mailboxId,
        int $organizationId,
        int $contactId,
        string $subjectCanonical,
        bool $replyReceived,
        bool $autoReplyReceived,
        string $status,
        Carbon $lastMessageAt,
    ): MailThread {
        return MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailboxId,
            'organization_id' => $organizationId,
            'contact_id' => $contactId,
            'subject_canonical' => $subjectCanonical,
            'first_message_at' => $lastMessageAt->copy()->subHour(),
            'last_message_at' => $lastMessageAt,
            'last_direction' => 'in',
            'reply_received' => $replyReceived,
            'auto_reply_received' => $autoReplyReceived,
            'confidence_score' => 0.95,
            'status' => $status,
        ]);
    }

    private function seedOutboundMessage(
        int $threadId,
        int $mailboxId,
        int $recipientId,
        string $fromEmail,
        string $toEmail,
        string $subject,
        Carbon $sentAt,
    ): MailMessage {
        return MailMessage::query()->create([
            'thread_id' => $threadId,
            'mailbox_account_id' => $mailboxId,
            'recipient_id' => $recipientId,
            'direction' => 'out',
            'message_id_header' => '<'.Str::uuid().'@aegis-mail.test>',
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => $fromEmail,
            'to_emails' => [$toEmail],
            'subject' => $subject,
            'html_body' => '<p>'.$subject.'</p>',
            'text_body' => $subject,
            'headers_json' => [],
            'classification' => 'unknown',
            'sent_at' => $sentAt,
        ]);
    }

    private function seedInboundMessage(
        int $threadId,
        int $mailboxId,
        int $recipientId,
        string $folder,
        int $uid,
        string $fromEmail,
        array $toEmails,
        string $subject,
        string $classification,
        Carbon $receivedAt,
        ?string $inReplyTo = null,
    ): MailMessage {
        return MailMessage::query()->create([
            'thread_id' => $threadId,
            'mailbox_account_id' => $mailboxId,
            'recipient_id' => $recipientId,
            'direction' => 'in',
            'provider_folder' => $folder,
            'provider_uid' => $uid,
            'message_id_header' => '<'.Str::uuid().'@mail.test>',
            'in_reply_to_header' => $inReplyTo,
            'aegis_tracking_id' => (string) Str::uuid(),
            'from_email' => $fromEmail,
            'to_emails' => $toEmails,
            'subject' => $subject,
            'html_body' => '<p>'.$subject.'</p>',
            'text_body' => $subject,
            'headers_json' => [],
            'classification' => $classification,
            'received_at' => $receivedAt,
        ]);
    }
}
