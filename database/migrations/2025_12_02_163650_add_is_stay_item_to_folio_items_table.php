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
        Schema::table('folio_items', function (Blueprint $table) {
            $table->boolean('is_stay_item')->default(false)->after('folio_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folio_items', function (Blueprint $table) {
            $table->dropColumn('is_stay_item');
        });
    }
};
