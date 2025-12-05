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
        Schema::create('maintenance_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('hotel_id')->index();
            $table->foreignUuid('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->unsignedBigInteger('reported_by_user_id');
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->string('status', 32)->index();
            $table->string('severity', 32);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'hotel_id']);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('hotel_id')
                ->references('id')
                ->on('hotels')
                ->cascadeOnDelete();

            $table->foreign('reported_by_user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('assigned_to_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
    }
};
