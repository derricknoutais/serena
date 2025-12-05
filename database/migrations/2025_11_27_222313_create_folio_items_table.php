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
        Schema::create('folio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->index();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained('folios')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->date('date')->default(today());
            $table->string('description');
            $table->string('type')->nullable();
            $table->string('account_code')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('base_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'hotel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folio_items');
    }
};
