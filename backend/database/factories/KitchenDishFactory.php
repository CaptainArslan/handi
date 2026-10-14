<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\KitchenDish>
 */
class KitchenDishFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'dish_id' => Dish::factory(),
            'is_custom' => false,
        ];
    }
}
