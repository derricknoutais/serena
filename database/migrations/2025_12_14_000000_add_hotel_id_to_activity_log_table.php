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
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table): void {
            if (! Schema::hasColumn(config('activitylog.table_name'), 'hotel_id')) {
                $table->unsignedBigInteger('hotel_id')->nullable()->after('tenant_id');
                $table->index('hotel_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table): void {
            if (Schema::hasColumn(config('activitylog.table_name'), 'hotel_id')) {
                $table->dropIndex([config('activitylog.table_name').'_hotel_id_index']);
                $table->dropColumn('hotel_id');
            }
        });
    }
};
