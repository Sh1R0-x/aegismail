<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('phone_landline')->nullable()->after('phone');
            $table->string('phone_mobile')->nullable()->after('phone_landline');
        });

        DB::table('contacts')
            ->whereNotNull('phone')
            ->update([
                'phone_landline' => DB::raw('phone'),
            ]);
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn([
                'phone_landline',
                'phone_mobile',
            ]);
        });
    }
};
