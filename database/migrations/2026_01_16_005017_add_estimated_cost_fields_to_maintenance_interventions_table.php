<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maintenance_interventions', function (Blueprint $table): void {
            if (! Schema::hasColumn('maintenance_interventions', 'estimated_subtotal_amount')) {
                $table->decimal('estimated_subtotal_amount', 10, 2)->default(0)->after('total_cost');
            }

            if (! Schema::hasColumn('maintenance_interventions', 'estimated_total_amount')) {
                $table->decimal('estimated_total_amount', 10, 2)->default(0)->after('estimated_subtotal_amount');
            }

            if (! Schema::hasColumn('maintenance_interventions', 'cost_mode')) {
                $table->string('cost_mode', 32)->default('estimated')->after('estimated_total_amount');
            }
        });

        DB::table('maintenance_interventions')->update([
            'estimated_subtotal_amount' => DB::raw('total_cost'),
            'estimated_total_amount' => DB::raw('total_cost'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_interventions', function (Blueprint $table): void {
            if (Schema::hasColumn('maintenance_interventions', 'cost_mode')) {
                $table->dropColumn('cost_mode');
            }

            if (Schema::hasColumn('maintenance_interventions', 'estimated_total_amount')) {
                $table->dropColumn('estimated_total_amount');
            }

            if (Schema::hasColumn('maintenance_interventions', 'estimated_subtotal_amount')) {
                $table->dropColumn('estimated_subtotal_amount');
            }
        });
    }
};
