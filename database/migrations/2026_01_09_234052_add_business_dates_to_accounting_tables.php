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
        Schema::table('payments', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('created_at');
            $table->index(['tenant_id', 'hotel_id', 'business_date'], 'payments_business_date_idx');
        });

        Schema::table('folio_items', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('date');
            $table->index(['tenant_id', 'hotel_id', 'business_date'], 'folio_items_business_date_idx');
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('started_at');
            $table->index(['tenant_id', 'hotel_id', 'business_date'], 'cash_sessions_business_date_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('issue_date');
            $table->index(['tenant_id', 'hotel_id', 'business_date'], 'invoices_business_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_business_date_idx');
            $table->dropColumn('business_date');
        });

        Schema::table('folio_items', function (Blueprint $table) {
            $table->dropIndex('folio_items_business_date_idx');
            $table->dropColumn('business_date');
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropIndex('cash_sessions_business_date_idx');
            $table->dropColumn('business_date');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_business_date_idx');
            $table->dropColumn('business_date');
        });
    }
};
