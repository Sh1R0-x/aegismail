<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_name');
            $table->string('source_type', 16);
            $table->string('status', 20)->default('completed');
            $table->unsignedInteger('imported_contacts_count')->default(0);
            $table->unsignedInteger('skipped_rows_count')->default(0);
            $table->unsignedInteger('invalid_rows_count')->default(0);
            $table->json('contact_ids_json')->nullable();
            $table->json('summary_json')->nullable();
            $table->json('report_json')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_import_batches');
    }
};
