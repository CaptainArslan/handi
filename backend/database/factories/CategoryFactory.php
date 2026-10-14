<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $key = 'cat'.fake()->unique()->numberBetween(1, 999999);

        return ['key' => $key, 'name' => ucfirst($key), 'image_key' => 'lentils', 'sort_order' => 0, 'is_active' => true];
    }
}
