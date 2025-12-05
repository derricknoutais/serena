<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('document_type')->nullable();
            $table->string('document_number')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
