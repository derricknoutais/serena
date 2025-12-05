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
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('hotel_id')->nullable()->index();
            $table->string('name');
            $table->string('code')->index();
            $table->string('type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('provider')->nullable();
            $table->string('account_number')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'hotel_id', 'code']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
