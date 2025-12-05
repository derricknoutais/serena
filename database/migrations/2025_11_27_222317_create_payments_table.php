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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained('folios')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
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
        Schema::dropIfExists('payments');
    }
};
