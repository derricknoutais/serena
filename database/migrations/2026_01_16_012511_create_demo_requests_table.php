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
        Schema::create('demo_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('hotel_name', 160);
            $table->string('name', 160);
            $table->string('phone', 40);
            $table->string('city', 120)->nullable();
            $table->string('email', 160)->nullable();
            $table->text('message')->nullable();
            $table->string('source', 40)->default('landing');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
