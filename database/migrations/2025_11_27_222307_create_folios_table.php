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
        Schema::create('folios', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->index();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->string('code');
            $table->string('status')->default('open');
            $table->boolean('is_main')->default(false)->index();
            $table->string('type');
            $table->string('origin')->nullable();
            $table->string('currency', 3);
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('billing_name')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folios');
    }
};
