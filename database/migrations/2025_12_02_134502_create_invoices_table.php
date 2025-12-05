<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('hotel_id')->index();
            $table->unsignedBigInteger('folio_id')->index();
            $table->unsignedBigInteger('guest_id')->nullable()->index();
            $table->string('number')->index();
            $table->string('status');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3);
            $table->decimal('sub_total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('billing_name')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('billing_tax_id')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'number']);
            $table->index(['tenant_id', 'hotel_id', 'status']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
            $table->foreign('folio_id')->references('id')->on('folios')->cascadeOnDelete();
            $table->foreign('guest_id')->references('id')->on('guests')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
