<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('linkedin_url')->nullable()->after('phone');
            $table->string('country')->nullable()->after('linkedin_url');
            $table->string('city')->nullable()->after('country');
            $table->json('tags_json')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn([
                'linkedin_url',
                'country',
                'city',
                'tags_json',
            ]);
        });
    }
};
