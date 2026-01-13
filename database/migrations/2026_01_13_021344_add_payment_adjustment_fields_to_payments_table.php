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
            $table->foreignId('parent_payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete()
                ->index();
            $table->string('entry_type')->nullable()->index();
            $table->timestamp('voided_at')->nullable()->index();
            $table->foreignId('voided_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->index();
            $table->string('void_reason')->nullable();
            $table->string('refund_reason')->nullable();
            $table->string('refund_reference')->nullable();
            $table->index(['tenant_id', 'hotel_id', 'parent_payment_id']);
            $table->index(['tenant_id', 'hotel_id', 'entry_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'hotel_id', 'entry_type']);
            $table->dropIndex(['tenant_id', 'hotel_id', 'parent_payment_id']);
            $table->dropForeign(['parent_payment_id']);
            $table->dropForeign(['voided_by_user_id']);
            $table->dropColumn([
                'parent_payment_id',
                'entry_type',
                'voided_at',
                'voided_by_user_id',
                'void_reason',
                'refund_reason',
                'refund_reference',
            ]);
        });
    }
};
