<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('hotel_id')->index();

            $table->string('type')->index(); // frontdesk, bar

            // Users involved
            $table->unsignedBigInteger('opened_by_user_id')->index();
            $table->unsignedBigInteger('closed_by_user_id')->nullable()->index();
            $table->unsignedBigInteger('validated_by_user_id')->nullable()->index();

            // Dates
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable(); // Closed at
            $table->dateTime('validated_at')->nullable();

            // Amounts
            $table->decimal('starting_amount', 10, 2);
            $table->decimal('expected_closing_amount', 10, 2)->nullable();
            $table->decimal('closing_amount', 10, 2)->nullable(); // Actual amount counted
            $table->decimal('difference_amount', 10, 2)->nullable(); // Actual - Expected

            $table->string('currency', 3)->default('XAF');
            $table->string('status')->default('open')->index(); // open, closed_pending_validation, validated
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
            $table->foreign('opened_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('closed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('validated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
