<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // In-app notification center (6.1).
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);               // daily_picks | sync | photo_ready | kitchen_ready
            $table->string('title', 150);
            $table->string('body', 500);
            $table->string('cta_label', 40)->nullable();
            $table->string('deep_link', 120)->nullable();
            $table->string('image_key', 40)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->index();
            $table->string('device_id', 100)->unique();
            $table->string('name', 100)->nullable();
            $table->string('expo_push_token')->nullable();
            $table->enum('platform', ['android', 'ios']);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // 6-digit codes for password reset and email change.
        Schema::create('auth_codes', function (Blueprint $table) {
            $table->id();
            $table->enum('purpose', ['password_reset', 'email_change']);
            $table->string('email');                  // where the code was sent
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts_left')->default(5);
            $table->dateTime('expires_at');
            $table->dateTime('last_sent_at');
            $table->string('reset_token_hash')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['purpose', 'email']);
        });

        // Everything users do, for the super-admin activity log.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 60)->index();     // auth.login, meal.logged, dish.removed…
            $table->string('description', 255);
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('auth_codes');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('app_notifications');
    }
};
