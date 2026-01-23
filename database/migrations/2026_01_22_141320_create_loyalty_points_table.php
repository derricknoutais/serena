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
        Schema::create('loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->index();
            $table->unsignedBigInteger('hotel_id')->index();
            $table->unsignedBigInteger('reservation_id')->index();
            $table->unsignedBigInteger('guest_id')->index();
            $table->string('type')->default('earn');
            $table->integer('points');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->foreign('hotel_id')
                ->references('id')
                ->on('hotels')
                ->cascadeOnDelete();
            $table->foreign('reservation_id')
                ->references('id')
                ->on('reservations')
                ->cascadeOnDelete();
            $table->foreign('guest_id')
                ->references('id')
                ->on('guests')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_points');
    }
};
