<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();          // vegetables, lentils, chicken…
            $table->string('name', 100);
            $table->string('image_key', 40)->nullable();   // default art for dishes without a photo
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // The global dish library ("150+ dishes"). Users pick from it into their kitchen.
        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('note', 80)->nullable();          // e.g. "Summer favourite"
            $table->text('description')->nullable();
            $table->string('image')->nullable();             // uploaded photo path (media disk)
            $table->string('image_key', 40)->nullable();     // bundled art key
            $table->json('default_weather');                 // ["hot","mild"] or ["any"]
            $table->enum('default_effort', ['quick', 'medium', 'heavy'])->default('medium');
            $table->enum('default_cost', ['low', 'medium', 'high'])->default('low');
            $table->enum('usual_meal', ['breakfast', 'lunch', 'dinner', 'both'])->default('both');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'category_id']);
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('short_name', 40);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->decimal('lat', 8, 5);
            $table->decimal('lng', 8, 5);
            $table->boolean('is_popular')->default(false);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('dishes');
        Schema::dropIfExists('categories');
    }
};
