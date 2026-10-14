<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One kitchen (household) per user: family, budget, preferences, location, reminder.
        Schema::create('kitchens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('family_size')->default(4);
            $table->enum('budget', ['low', 'normal', 'high'])->default('normal');
            $table->unsignedTinyInteger('repeat_gap_days')->default(5);
            $table->json('meals');                                 // ["lunch","dinner"]
            $table->enum('location_mode', ['gps', 'city'])->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 6, 2)->nullable();               // rounded to ~1 km
            $table->decimal('lng', 6, 2)->nullable();
            $table->string('location_label', 120)->nullable();      // "Lahore · near you"
            $table->boolean('notifications_enabled')->default(true);
            $table->string('notify_time', 5)->default('09:00');     // HH:mm, 05:00–13:00
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        // "My dishes": library dishes the user picked, plus their custom dishes.
        Schema::create('kitchen_dishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->nullable()->constrained()->nullOnDelete();   // library dish
            $table->boolean('is_custom')->default(false);
            $table->string('name', 60)->nullable();                                  // custom only
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete(); // custom only
            $table->enum('usual_meal', ['breakfast', 'lunch', 'dinner', 'both'])->nullable();
            $table->json('weather')->nullable();                                     // override / custom
            $table->enum('effort', ['quick', 'medium', 'heavy'])->nullable();        // override / custom
            $table->enum('cost', ['low', 'medium', 'high'])->nullable();             // override / custom
            $table->string('photo_path')->nullable();
            $table->enum('photo_status', ['none', 'processing', 'ready'])->default('none');
            $table->timestamp('removed_at')->nullable();                             // "Remove" keeps history
            $table->timestamps();

            $table->unique(['user_id', 'dish_id']);
            $table->index(['user_id', 'removed_at']);
        });

        // Today's 3 picks per user and date, the shuffle count and the chosen plan.
        Schema::create('daily_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('set_number')->default(0);
            $table->unsignedTinyInteger('shuffles_used')->default(0);
            $table->json('items');                    // [{kitchen_dish_id, reason}]
            $table->json('weather')->nullable();      // snapshot used for the picks
            $table->dateTime('generated_at');
            $table->foreignId('plan_kitchen_dish_id')->nullable()->constrained('kitchen_dishes')->nullOnDelete();
            $table->enum('plan_meal', ['lunch', 'dinner'])->nullable();
            $table->timestamp('planned_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        Schema::create('meal_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();            // client-generated for offline logging
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_dish_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dish_name', 150);         // snapshot: history survives dish removal
            $table->string('category_key', 40)->nullable();
            $table->date('date');
            $table->enum('meal', ['breakfast', 'lunch', 'dinner']);
            $table->enum('source', ['suggestion', 'manual'])->default('manual');
            $table->dateTime('logged_at');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'kitchen_dish_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_logs');
        Schema::dropIfExists('daily_picks');
        Schema::dropIfExists('kitchen_dishes');
        Schema::dropIfExists('kitchens');
    }
};
