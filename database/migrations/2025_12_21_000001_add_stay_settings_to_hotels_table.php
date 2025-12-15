<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table): void {
            $table->json('stay_settings')->nullable()->after('check_out_time');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table): void {
            $table->dropColumn('stay_settings');
        });
    }
};
