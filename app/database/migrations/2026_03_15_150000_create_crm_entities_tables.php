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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('name');
            $table->index('domain');
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->nullable();
            $table->timestampsTz();

            $table->index('organization_id');
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('contact_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->string('email');
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('opt_out_at')->nullable();
            $table->string('opt_out_reason')->nullable();
            $table->string('bounce_status')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();

            $table->unique(['contact_id', 'email']);
            $table->index(['contact_id', 'is_primary']);
        });

        Schema::create('mail_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('content_id')->nullable();
            $table->string('disposition')->nullable();
            $table->timestampsTz();

            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_attachments');
        Schema::dropIfExists('contact_emails');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('organizations');
    }
};
