<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds missing indexes on foreign key columns for query performance
 * and data integrity. FK constraints are omitted intentionally for
 * SQLite compatibility (ALTER TABLE ADD CONSTRAINT is limited) and
 * because Laravel handles referential integrity via service layer
 * (nullOnDelete, cascadeOnDelete patterns in code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_recipients', function (Blueprint $table) {
            $table->index('contact_id');
            $table->index('contact_email_id');
        });

        Schema::table('mail_threads', function (Blueprint $table) {
            $table->index('contact_id');
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('mail_recipients', function (Blueprint $table) {
            $table->dropIndex(['contact_id']);
            $table->dropIndex(['contact_email_id']);
        });

        Schema::table('mail_threads', function (Blueprint $table) {
            $table->dropIndex(['contact_id']);
            $table->dropIndex(['organization_id']);
        });
    }
};
