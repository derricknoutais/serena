<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_locations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('hotel_id');
            $table->string('name', 120);
            $table->string('code', 32)->nullable();
            $table->string('category', 32)->default('general');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'hotel_id']);
            $table->unique(['tenant_id', 'hotel_id', 'name']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_locations');
    }
};
