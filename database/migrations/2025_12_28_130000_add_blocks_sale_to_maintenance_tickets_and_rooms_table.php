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
        Schema::table('maintenance_tickets', function (Blueprint $table): void {
            $table->boolean('blocks_sale')->default(false)->after('severity');
            $table->index(['room_id', 'status']);
            $table->index(['room_id', 'blocks_sale']);
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->boolean('block_sale_after_checkout')->default(false)->after('status');
        });

        DB::table('maintenance_tickets')
            ->whereIn('severity', ['high', 'critical'])
            ->update(['blocks_sale' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table): void {
            $table->dropIndex(['room_id', 'status']);
            $table->dropIndex(['room_id', 'blocks_sale']);
            $table->dropColumn('blocks_sale');
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropColumn('block_sale_after_checkout');
        });
    }
};
