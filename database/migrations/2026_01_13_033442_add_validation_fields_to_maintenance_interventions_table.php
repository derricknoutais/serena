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
        Schema::table('maintenance_interventions', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenance_interventions', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }

            if (! Schema::hasColumn('maintenance_interventions', 'submitted_by_user_id')) {
                $table->foreignId('submitted_by_user_id')
                    ->nullable()
                    ->index('maintenance_interventions_submitted_by_user_id_index');
            }

            if (! Schema::hasColumn('maintenance_interventions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }

            if (! Schema::hasColumn('maintenance_interventions', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')
                    ->nullable()
                    ->index('maintenance_interventions_approved_by_user_id_index');
            }

            if (! Schema::hasColumn('maintenance_interventions', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }

            if (! Schema::hasColumn('maintenance_interventions', 'rejected_by_user_id')) {
                $table->foreignId('rejected_by_user_id')
                    ->nullable()
                    ->index('maintenance_interventions_rejected_by_user_id_index');
            }

            if (! Schema::hasColumn('maintenance_interventions', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }

            if (! Schema::hasColumn('maintenance_interventions', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }

            if (! Schema::hasColumn('maintenance_interventions', 'paid_by_user_id')) {
                $table->foreignId('paid_by_user_id')
                    ->nullable()
                    ->index('maintenance_interventions_paid_by_user_id_index');
            }
        });

        Schema::table('maintenance_interventions', function (Blueprint $table) {
            if (
                Schema::hasColumn('maintenance_interventions', 'submitted_by_user_id')
                && ! $this->foreignKeyExists('maintenance_interventions', 'maintenance_interventions_submitted_by_user_id_foreign')
            ) {
                $table->foreign('submitted_by_user_id', 'maintenance_interventions_submitted_by_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (
                Schema::hasColumn('maintenance_interventions', 'approved_by_user_id')
                && ! $this->foreignKeyExists('maintenance_interventions', 'maintenance_interventions_approved_by_user_id_foreign')
            ) {
                $table->foreign('approved_by_user_id', 'maintenance_interventions_approved_by_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (
                Schema::hasColumn('maintenance_interventions', 'rejected_by_user_id')
                && ! $this->foreignKeyExists('maintenance_interventions', 'maintenance_interventions_rejected_by_user_id_foreign')
            ) {
                $table->foreign('rejected_by_user_id', 'maintenance_interventions_rejected_by_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (
                Schema::hasColumn('maintenance_interventions', 'paid_by_user_id')
                && ! $this->foreignKeyExists('maintenance_interventions', 'maintenance_interventions_paid_by_user_id_foreign')
            ) {
                $table->foreign('paid_by_user_id', 'maintenance_interventions_paid_by_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_interventions', function (Blueprint $table) {
            if ($this->foreignKeyExists('maintenance_interventions', 'maintenance_interventions_submitted_by_user_id_foreign')) {
                $table->dropForeign('maintenance_interventions_submitted_by_user_id_foreign');
            }

            if ($this->foreignKeyExists('maintenance_interventions', 'maintenance_interventions_approved_by_user_id_foreign')) {
                $table->dropForeign('maintenance_interventions_approved_by_user_id_foreign');
            }

            if ($this->foreignKeyExists('maintenance_interventions', 'maintenance_interventions_rejected_by_user_id_foreign')) {
                $table->dropForeign('maintenance_interventions_rejected_by_user_id_foreign');
            }

            if ($this->foreignKeyExists('maintenance_interventions', 'maintenance_interventions_paid_by_user_id_foreign')) {
                $table->dropForeign('maintenance_interventions_paid_by_user_id_foreign');
            }

            $columns = array_filter([
                Schema::hasColumn('maintenance_interventions', 'submitted_at') ? 'submitted_at' : null,
                Schema::hasColumn('maintenance_interventions', 'submitted_by_user_id') ? 'submitted_by_user_id' : null,
                Schema::hasColumn('maintenance_interventions', 'approved_at') ? 'approved_at' : null,
                Schema::hasColumn('maintenance_interventions', 'approved_by_user_id') ? 'approved_by_user_id' : null,
                Schema::hasColumn('maintenance_interventions', 'rejected_at') ? 'rejected_at' : null,
                Schema::hasColumn('maintenance_interventions', 'rejected_by_user_id') ? 'rejected_by_user_id' : null,
                Schema::hasColumn('maintenance_interventions', 'rejection_reason') ? 'rejection_reason' : null,
                Schema::hasColumn('maintenance_interventions', 'paid_at') ? 'paid_at' : null,
                Schema::hasColumn('maintenance_interventions', 'paid_by_user_id') ? 'paid_by_user_id' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function foreignKeyExists(string $table, string $keyName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        return count(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $keyName],
        )) > 0;
    }
};
