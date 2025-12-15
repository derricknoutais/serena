<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('notifications', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('notifiable_id')->index();
            }

            if (! Schema::hasColumn('notifications', 'hotel_id')) {
                $table->unsignedBigInteger('hotel_id')->nullable()->after('tenant_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            if (Schema::hasColumn('notifications', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }

            if (Schema::hasColumn('notifications', 'hotel_id')) {
                $table->dropColumn('hotel_id');
            }
        });
    }
};
