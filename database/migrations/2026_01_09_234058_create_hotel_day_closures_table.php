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
        Schema::create('hotel_day_closures', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('hotel_id')->index();
            $table->date('business_date');
            $table->dateTime('started_at');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'business_date'], 'hotel_business_date_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
            $table->foreign('closed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotel_day_closures', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['hotel_id']);
            $table->dropForeign(['closed_by_user_id']);
        });

        Schema::dropIfExists('hotel_day_closures');
    }
};
