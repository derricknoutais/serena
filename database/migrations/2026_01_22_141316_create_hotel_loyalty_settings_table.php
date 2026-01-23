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
        Schema::create('hotel_loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->index();
            $table->unsignedBigInteger('hotel_id')->unique();
            $table->boolean('enabled')->default(false);

            $table->string('earning_mode')->default('amount');
            $table->unsignedInteger('points_per_amount')->nullable();
            $table->decimal('amount_base', 12, 2)->nullable();
            $table->unsignedInteger('points_per_night')->nullable();
            $table->unsignedInteger('fixed_points')->nullable();
            $table->unsignedInteger('max_points_per_stay')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->foreign('hotel_id')
                ->references('id')
                ->on('hotels')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_loyalty_settings');
    }
};
