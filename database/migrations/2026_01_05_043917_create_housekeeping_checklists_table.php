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
        Schema::create('housekeeping_checklists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('hotel_id')->nullable()->index();
            $table->string('name');
            $table->string('scope', 32)->index();
            $table->unsignedBigInteger('room_type_id')->nullable()->index();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();

            $table->index(['tenant_id', 'hotel_id']);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('hotel_id')
                ->references('id')
                ->on('hotels')
                ->nullOnDelete();

            $table->foreign('room_type_id')
                ->references('id')
                ->on('room_types')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housekeeping_checklists');
    }
};
