<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mailbox_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('ovh_mx_plan')->unique();
            $table->string('email');
            $table->string('display_name');
            $table->string('username');
            $table->text('password_encrypted');
            $table->string('imap_host');
            $table->unsignedSmallInteger('imap_port');
            $table->boolean('imap_secure')->default(true);
            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port');
            $table->boolean('smtp_secure')->default(true);
            $table->boolean('sync_enabled')->default(true);
            $table->boolean('send_enabled')->default(true);
            $table->unsignedBigInteger('last_inbox_uid')->nullable();
            $table->unsignedBigInteger('last_sent_uid')->nullable();
            $table->timestampTz('last_sync_at')->nullable();
            $table->string('health_status')->default('unknown');
            $table->text('health_message')->nullable();
            $table->timestampsTz();
        });

        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subject_template');
            $table->longText('html_template');
            $table->longText('text_template');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('mail_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 10);
            $table->foreignId('template_id')->nullable()->constrained('mail_templates')->nullOnDelete();
            $table->string('subject');
            $table->longText('html_body')->nullable();
            $table->longText('text_body')->nullable();
            $table->longText('signature_snapshot')->nullable();
            $table->json('payload_json')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampsTz();

            $table->index(['mailbox_account_id', 'status']);
        });

        Schema::create('mail_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('mode', 10);
            $table->foreignId('draft_id')->nullable()->constrained('mail_drafts')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->json('send_window_json')->nullable();
            $table->json('throttling_json')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['mailbox_account_id', 'status']);
        });

        Schema::create('mail_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('mail_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('contact_email_id')->nullable();
            $table->string('email');
            $table->string('status', 30)->default('draft');
            $table->timestampTz('last_event_at')->nullable();
            $table->timestampTz('scheduled_for')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('replied_at')->nullable();
            $table->timestampTz('auto_replied_at')->nullable();
            $table->timestampTz('bounced_at')->nullable();
            $table->timestampTz('unsubscribe_at')->nullable();
            $table->string('score_bucket')->nullable();
            $table->timestampsTz();

            $table->index(['campaign_id', 'status']);
            $table->index('email');
        });

        Schema::create('mail_threads', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('mailbox_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('subject_canonical');
            $table->timestampTz('first_message_at');
            $table->timestampTz('last_message_at');
            $table->string('last_direction', 3);
            $table->boolean('reply_received')->default(false);
            $table->boolean('auto_reply_received')->default(false);
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('status')->nullable();
            $table->timestampsTz();

            $table->index(['mailbox_account_id', 'last_message_at']);
        });

        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('mail_threads')->cascadeOnDelete();
            $table->foreignId('mailbox_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('mail_recipients')->nullOnDelete();
            $table->string('direction', 3);
            $table->string('provider_folder')->nullable();
            $table->unsignedBigInteger('provider_uid')->nullable();
            $table->string('message_id_header')->unique();
            $table->string('in_reply_to_header')->nullable();
            $table->text('references_header')->nullable();
            $table->uuid('aegis_tracking_id')->unique();
            $table->string('from_email');
            $table->json('to_emails');
            $table->json('cc_emails')->nullable();
            $table->json('bcc_emails')->nullable();
            $table->string('subject');
            $table->longText('html_body')->nullable();
            $table->longText('text_body')->nullable();
            $table->json('headers_json')->nullable();
            $table->string('classification')->default('unknown');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('opened_first_at')->nullable();
            $table->timestampTz('clicked_first_at')->nullable();
            $table->timestampsTz();

            $table->unique(['mailbox_account_id', 'provider_folder', 'provider_uid'], 'mail_messages_mailbox_folder_uid_unique');
            $table->index(['thread_id', 'received_at']);
            $table->index('classification');
        });

        Schema::create('mail_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('mail_campaigns')->nullOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('mail_recipients')->nullOnDelete();
            $table->foreignId('thread_id')->nullable()->constrained('mail_threads')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('mail_messages')->nullOnDelete();
            $table->string('event_type');
            $table->json('event_payload')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->nullable();

            $table->index('event_type');
            $table->index('occurred_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value_json');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('mail_events');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_threads');
        Schema::dropIfExists('mail_recipients');
        Schema::dropIfExists('mail_campaigns');
        Schema::dropIfExists('mail_drafts');
        Schema::dropIfExists('mail_templates');
        Schema::dropIfExists('mailbox_accounts');
    }
};
