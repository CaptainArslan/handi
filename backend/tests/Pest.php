<?php

use App\Models\Dish;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        Http::preventStrayRequests();
        fakeWeather();
    })
    ->in('Feature');

/** Fake Open-Meteo and the Expo push API. */
function fakeWeather(float $temp = 31, int $code = 1, int $rain = 10): void
{
    Http::fake([
        'weather.test/*' => Http::response([
            'current' => ['temperature_2m' => $temp, 'weather_code' => $code],
            'daily' => ['temperature_2m_max' => [$temp + 3], 'precipitation_probability_max' => [$rain]],
        ]),
        'push.test/*' => Http::response(['data' => [['status' => 'ok', 'id' => 'x']]]),
    ]);
}

function seedCatalog(): void
{
    (new CatalogSeeder)->run();
}

/** Library dish ids by name. @return array<int, int> */
function dishIds(array $names): array
{
    return Dish::whereIn('name', $names)->pluck('id')->all();
}

const KITCHEN_DISHES = ['Daal Chawal', 'Bhindi', 'Chicken Karahi', 'Aloo Gobi', 'Biryani', 'Qeema', 'Fish Fry', 'Chana Daal', 'Karela', 'Matar Pulao', 'Nihari', 'Mutton Karahi'];

function registerPayload(array $overrides = []): array
{
    $base = [
        'name' => 'Hina Arslan',
        'email' => 'hina@example.com',
        'password' => 'kitchen123',
        'password_confirmation' => 'kitchen123',
        'terms_accepted' => true,
        'device_name' => 'Pixel 8',
        'kitchen' => ['family_size' => 4, 'budget' => 'normal', 'repeat_gap_days' => 5, 'meals' => ['lunch', 'dinner']],
        'location' => ['mode' => 'city', 'city_id' => \App\Models\City::where('slug', 'lahore')->value('id')],
        'dish_ids' => dishIds(KITCHEN_DISHES),
        'custom_dishes' => [['name' => 'Mom’s Karhi', 'category' => 'lentils']],
    ];
    foreach (['kitchen', 'location'] as $key) {
        if (isset($overrides[$key])) {
            $overrides[$key] = array_replace($base[$key], $overrides[$key]);
        }
    }

    return array_replace($base, $overrides);
}

/** A signed-up app user with a full kitchen (via the real sign-up endpoint). */
function appUser(array $overrides = []): User
{
    seedCatalogOnce();
    test()->postJson('/api/v1/auth/register', registerPayload($overrides))->assertCreated();

    return User::where('email', $overrides['email'] ?? 'hina@example.com')->firstOrFail();
}

function seedCatalogOnce(): void
{
    if (Dish::count() === 0) {
        seedCatalog();
    }
}
