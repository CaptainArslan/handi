<?php

namespace Database\Factories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\City>
 */
class CityFactory extends Factory
{
    public function definition(): array
    {
        $name = 'City '.fake()->unique()->numberBetween(1, 99999);

        return [
            'province_id' => Province::create(['name' => 'Province '.$name, 'short_name' => 'P']),
            'name' => $name,
            'lat' => 31.55,
            'lng' => 74.34,
            'is_popular' => false,
        ];
    }
}
