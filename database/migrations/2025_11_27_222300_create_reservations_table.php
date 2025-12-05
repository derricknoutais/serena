<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('hotel_id')->index();
            $table->unsignedBigInteger('guest_id')->index();
            $table->unsignedBigInteger('room_type_id')->index();
            $table->uuid('room_id')->nullable()->index();
            $table->unsignedBigInteger('offer_id')->nullable()->index();

            $table->string('code')->index();
            $table->string('status');
            $table->string('source')->nullable();

            $table->string('offer_name')->nullable();
            $table->string('offer_kind')->nullable();

            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);

            $table->datetime('check_in_date');
            $table->datetime('check_out_date');
            $table->time('expected_arrival_time')->nullable();
            $table->dateTime('actual_check_in_at')->nullable();
            $table->dateTime('actual_check_out_at')->nullable();

            $table->string('currency', 3);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('base_amount', 10, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('booked_by_user_id')->nullable();

            $table->timestamps();

            $table->unique(['hotel_id', 'code']);
            $table->index(['tenant_id', 'hotel_id', 'check_in_date']);
            $table->index(['tenant_id', 'hotel_id', 'status']);

            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
            $table->foreign('guest_id')->references('id')->on('guests')->restrictOnDelete();
            $table->foreign('room_type_id')->references('id')->on('room_types')->restrictOnDelete();
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
            $table->foreign('offer_id')->references('id')->on('offers')->nullOnDelete();
            $table->foreign('booked_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
