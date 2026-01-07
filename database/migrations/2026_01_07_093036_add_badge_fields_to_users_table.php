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
        Schema::table('users', function (Blueprint $table) {
            $table->string('badge_code')->nullable()->after('email');
            $table->string('badge_pin')->nullable()->after('badge_code');

            $table->unique(['tenant_id', 'badge_code'], 'users_tenant_badge_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_badge_code_unique');
            $table->dropColumn(['badge_code', 'badge_pin']);
        });
    }
};
