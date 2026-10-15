<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for queries that scan whole tables at scale:
 *  - admin dashboard widgets filter meal_logs by logged_at / date across all users,
 *  - the every-minute reminder run filters kitchens by notifications_enabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_logs', function (Blueprint $table) {
            $table->index('logged_at');
            $table->index(['date', 'deleted_at']);
        });

        Schema::table('kitchens', function (Blueprint $table) {
            $table->index(['notifications_enabled', 'notify_time']);
        });
    }

    public function down(): void
    {
        Schema::table('meal_logs', function (Blueprint $table) {
            $table->dropIndex(['logged_at']);
            $table->dropIndex(['date', 'deleted_at']);
        });

        Schema::table('kitchens', function (Blueprint $table) {
            $table->dropIndex(['notifications_enabled', 'notify_time']);
        });
    }
};
