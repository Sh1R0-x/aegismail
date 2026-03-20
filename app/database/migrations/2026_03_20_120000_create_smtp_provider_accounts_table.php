<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->unique();
            $table->string('username')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->boolean('smtp_secure')->default(true);
            $table->boolean('send_enabled')->default(true);
            $table->string('health_status')->default('unknown');
            $table->text('health_message')->nullable();
            $table->timestampTz('last_tested_at')->nullable();
            $table->timestampsTz();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_provider_accounts');
    }
};
