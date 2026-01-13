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
        Schema::create('maintenance_intervention_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->index();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('maintenance_intervention_id')
                ->constrained('maintenance_interventions')
                ->cascadeOnDelete()
                ->index('mic_intervention_idx');
            $table->string('cost_type', 32);
            $table->string('label', 190);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('XAF');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->index('mic_created_by_user_idx');
            $table->timestamps();

            $table->index(['tenant_id', 'hotel_id']);
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->foreign('maintenance_intervention_id', 'maintenance_intervention_costs_intervention_id_foreign')
                ->references('id')
                ->on('maintenance_interventions')
                ->cascadeOnDelete();
            $table->foreign('created_by_user_id', 'maintenance_intervention_costs_created_by_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        $interventions = DB::table('maintenance_interventions')
            ->where(function ($query): void {
                $query->where('labor_cost', '>', 0)->orWhere('parts_cost', '>', 0);
            })
            ->get([
                'id',
                'tenant_id',
                'hotel_id',
                'labor_cost',
                'parts_cost',
                'currency',
                'created_by_user_id',
            ]);

        foreach ($interventions as $intervention) {
            $hasLines = DB::table('maintenance_intervention_costs')
                ->where('maintenance_intervention_id', $intervention->id)
                ->exists();

            if ($hasLines) {
                continue;
            }

            $currency = $intervention->currency ?? 'XAF';
            $timestamp = now();
            $lines = [];

            if ((float) $intervention->labor_cost > 0) {
                $lines[] = [
                    'tenant_id' => $intervention->tenant_id,
                    'hotel_id' => $intervention->hotel_id,
                    'maintenance_intervention_id' => $intervention->id,
                    'cost_type' => 'labor',
                    'label' => 'Main d’œuvre',
                    'quantity' => 1,
                    'unit_price' => $intervention->labor_cost,
                    'total_amount' => $intervention->labor_cost,
                    'currency' => $currency,
                    'notes' => null,
                    'created_by_user_id' => $intervention->created_by_user_id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            if ((float) $intervention->parts_cost > 0) {
                $lines[] = [
                    'tenant_id' => $intervention->tenant_id,
                    'hotel_id' => $intervention->hotel_id,
                    'maintenance_intervention_id' => $intervention->id,
                    'cost_type' => 'parts',
                    'label' => 'Pièces',
                    'quantity' => 1,
                    'unit_price' => $intervention->parts_cost,
                    'total_amount' => $intervention->parts_cost,
                    'currency' => $currency,
                    'notes' => null,
                    'created_by_user_id' => $intervention->created_by_user_id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            if ($lines !== []) {
                DB::table('maintenance_intervention_costs')->insert($lines);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_intervention_costs');
    }
};
