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
        Schema::table('housekeeping_tasks', function (Blueprint $table): void {
            $table->unsignedInteger('duration_seconds')->nullable()->after('ended_at');
            $table->string('outcome', 32)->nullable()->after('duration_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('housekeeping_tasks', function (Blueprint $table): void {
            $table->dropColumn(['duration_seconds', 'outcome']);
        });
    }
};
