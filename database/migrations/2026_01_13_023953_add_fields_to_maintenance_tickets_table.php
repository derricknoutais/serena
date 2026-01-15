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
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenance_tickets', 'maintenance_type_id')) {
                $table->foreignId('maintenance_type_id')->nullable()->index('maintenance_tickets_maintenance_type_id_index');
            }

            if (! Schema::hasColumn('maintenance_tickets', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable();
            }

            if (! Schema::hasColumn('maintenance_tickets', 'closed_by_user_id')) {
                $table->foreignId('closed_by_user_id')->nullable()->index('maintenance_tickets_closed_by_user_id_index');
            }
        });

        if (
            Schema::hasColumn('maintenance_tickets', 'maintenance_type_id')
            && ! $this->foreignKeyExists('maintenance_tickets', 'maintenance_tickets_maintenance_type_id_foreign')
        ) {
            Schema::table('maintenance_tickets', function (Blueprint $table) {
                $table->foreign('maintenance_type_id', 'maintenance_tickets_maintenance_type_id_foreign')
                    ->references('id')
                    ->on('maintenance_types')
                    ->nullOnDelete();
            });
        }

        if (
            Schema::hasColumn('maintenance_tickets', 'closed_by_user_id')
            && ! $this->foreignKeyExists('maintenance_tickets', 'maintenance_tickets_closed_by_user_id_foreign')
        ) {
            Schema::table('maintenance_tickets', function (Blueprint $table) {
                $table->foreign('closed_by_user_id', 'maintenance_tickets_closed_by_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (
            Schema::hasColumn('maintenance_tickets', 'tenant_id')
            && Schema::hasColumn('maintenance_tickets', 'hotel_id')
            && Schema::hasColumn('maintenance_tickets', 'maintenance_type_id')
            && ! $this->indexExists('maintenance_tickets', 'maintenance_tickets_tenant_hotel_type_index')
        ) {
            Schema::table('maintenance_tickets', function (Blueprint $table) {
                $table->index(
                    ['tenant_id', 'hotel_id', 'maintenance_type_id'],
                    'maintenance_tickets_tenant_hotel_type_index',
                );
            });
        }

        $hotelScopes = DB::table('maintenance_tickets')
            ->select(['tenant_id', 'hotel_id'])
            ->distinct()
            ->get();

        foreach ($hotelScopes as $scope) {
            $typeId = DB::table('maintenance_types')
                ->where('tenant_id', $scope->tenant_id)
                ->where('hotel_id', $scope->hotel_id)
                ->where('name', 'Autre')
                ->value('id');

            if (! $typeId) {
                $typeId = DB::table('maintenance_types')->insertGetId([
                    'tenant_id' => $scope->tenant_id,
                    'hotel_id' => $scope->hotel_id,
                    'name' => 'Autre',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('maintenance_tickets')
                ->where('tenant_id', $scope->tenant_id)
                ->where('hotel_id', $scope->hotel_id)
                ->whereNull('maintenance_type_id')
                ->update([
                    'maintenance_type_id' => $typeId,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            if ($this->indexExists('maintenance_tickets', 'maintenance_tickets_tenant_hotel_type_index')) {
                $table->dropIndex('maintenance_tickets_tenant_hotel_type_index');
            }

            if ($this->foreignKeyExists('maintenance_tickets', 'maintenance_tickets_maintenance_type_id_foreign')) {
                $table->dropForeign('maintenance_tickets_maintenance_type_id_foreign');
            }

            if ($this->foreignKeyExists('maintenance_tickets', 'maintenance_tickets_closed_by_user_id_foreign')) {
                $table->dropForeign('maintenance_tickets_closed_by_user_id_foreign');
            }

            $columns = array_filter([
                Schema::hasColumn('maintenance_tickets', 'maintenance_type_id') ? 'maintenance_type_id' : null,
                Schema::hasColumn('maintenance_tickets', 'resolved_at') ? 'resolved_at' : null,
                Schema::hasColumn('maintenance_tickets', 'closed_by_user_id') ? 'closed_by_user_id' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function foreignKeyExists(string $table, string $keyName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return false;
        }

        return count(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $keyName],
        )) > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return count(array_filter(DB::select("PRAGMA index_list('{$table}')"), function ($row) use ($index): bool {
                return ($row->name ?? $row['name'] ?? null) === $index;
            })) > 0;
        }

        return count(DB::select(
            'SHOW INDEX FROM '.$table.' WHERE Key_name = ?',
            [$index],
        )) > 0;
    }
};
