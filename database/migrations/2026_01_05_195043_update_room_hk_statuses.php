<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('rooms')
            ->where('hk_status', 'clean')
            ->update([
                'hk_status' => 'inspected',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('rooms')
            ->where('hk_status', 'inspected')
            ->update([
                'hk_status' => 'clean',
            ]);
    }
};
