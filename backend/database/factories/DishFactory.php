<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Dish>
 */
class DishFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->words(2, true)).' '.fake()->unique()->numberBetween(1, 999999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category_id' => Category::factory(),
            'note' => null,
            'image_key' => null,
            'default_weather' => ['any'],
            'default_effort' => 'medium',
            'default_cost' => 'low',
            'usual_meal' => 'both',
            'is_active' => true,
        ];
    }

    public function weather(array $weather): static
    {
        return $this->state(fn () => ['default_weather' => $weather]);
    }
}
