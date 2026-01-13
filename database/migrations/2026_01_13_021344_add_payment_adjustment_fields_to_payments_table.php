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
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'parent_payment_id')) {
                $table->foreignId('parent_payment_id')
                    ->nullable()
                    ->constrained('payments')
                    ->nullOnDelete()
                    ->index('payments_parent_payment_id_index');
            }

            if (! Schema::hasColumn('payments', 'entry_type')) {
                $table->string('entry_type')->nullable()->index('payments_entry_type_index');
            }

            if (! Schema::hasColumn('payments', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->index('payments_voided_at_index');
            }

            if (! Schema::hasColumn('payments', 'voided_by_user_id')) {
                $table->foreignId('voided_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->index('payments_voided_by_user_id_index');
            }

            if (! Schema::hasColumn('payments', 'void_reason')) {
                $table->string('void_reason')->nullable();
            }

            if (! Schema::hasColumn('payments', 'refund_reason')) {
                $table->string('refund_reason')->nullable();
            }

            if (! Schema::hasColumn('payments', 'refund_reference')) {
                $table->string('refund_reference')->nullable();
            }
        });

        if (
            Schema::hasColumn('payments', 'tenant_id')
            && Schema::hasColumn('payments', 'hotel_id')
            && Schema::hasColumn('payments', 'parent_payment_id')
            && ! $this->indexExists('payments', 'payments_tenant_hotel_parent_payment_id_index')
        ) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(
                    ['tenant_id', 'hotel_id', 'parent_payment_id'],
                    'payments_tenant_hotel_parent_payment_id_index',
                );
            });
        }

        if (
            Schema::hasColumn('payments', 'tenant_id')
            && Schema::hasColumn('payments', 'hotel_id')
            && Schema::hasColumn('payments', 'entry_type')
            && ! $this->indexExists('payments', 'payments_tenant_hotel_entry_type_index')
        ) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(
                    ['tenant_id', 'hotel_id', 'entry_type'],
                    'payments_tenant_hotel_entry_type_index',
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if ($this->indexExists('payments', 'payments_tenant_hotel_entry_type_index')) {
                $table->dropIndex('payments_tenant_hotel_entry_type_index');
            }

            if ($this->indexExists('payments', 'payments_tenant_hotel_parent_payment_id_index')) {
                $table->dropIndex('payments_tenant_hotel_parent_payment_id_index');
            }

            if (Schema::hasColumn('payments', 'parent_payment_id')) {
                $table->dropForeign(['parent_payment_id']);
            }

            if (Schema::hasColumn('payments', 'voided_by_user_id')) {
                $table->dropForeign(['voided_by_user_id']);
            }

            $columns = array_filter([
                Schema::hasColumn('payments', 'parent_payment_id') ? 'parent_payment_id' : null,
                Schema::hasColumn('payments', 'entry_type') ? 'entry_type' : null,
                Schema::hasColumn('payments', 'voided_at') ? 'voided_at' : null,
                Schema::hasColumn('payments', 'voided_by_user_id') ? 'voided_by_user_id' : null,
                Schema::hasColumn('payments', 'void_reason') ? 'void_reason' : null,
                Schema::hasColumn('payments', 'refund_reason') ? 'refund_reason' : null,
                Schema::hasColumn('payments', 'refund_reference') ? 'refund_reference' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return count(DB::select(
            'SHOW INDEX FROM '.$table.' WHERE Key_name = ?',
            [$index],
        )) > 0;
    }
};
