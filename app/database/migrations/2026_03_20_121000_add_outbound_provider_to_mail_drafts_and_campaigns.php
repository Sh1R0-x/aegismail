<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_drafts', function (Blueprint $table) {
            $table->string('outbound_provider')->default('ovh_mx_plan')->after('mailbox_account_id');
            $table->index(['mailbox_account_id', 'outbound_provider'], 'mail_drafts_mailbox_provider_index');
        });

        Schema::table('mail_campaigns', function (Blueprint $table) {
            $table->string('outbound_provider')->default('ovh_mx_plan')->after('mailbox_account_id');
            $table->index(['mailbox_account_id', 'outbound_provider'], 'mail_campaigns_mailbox_provider_index');
        });
    }

    public function down(): void
    {
        Schema::table('mail_campaigns', function (Blueprint $table) {
            $table->dropIndex('mail_campaigns_mailbox_provider_index');
            $table->dropColumn('outbound_provider');
        });

        Schema::table('mail_drafts', function (Blueprint $table) {
            $table->dropIndex('mail_drafts_mailbox_provider_index');
            $table->dropColumn('outbound_provider');
        });
    }
};
