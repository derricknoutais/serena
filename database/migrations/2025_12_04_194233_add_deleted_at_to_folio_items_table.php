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
        Schema::table('folio_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('folio_items', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folio_items', function (Blueprint $table): void {
            if (Schema::hasColumn('folio_items', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
