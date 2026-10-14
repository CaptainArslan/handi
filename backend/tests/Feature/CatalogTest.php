<?php

beforeEach(fn () => seedCatalog());

it('lists the library with categories, art and filters', function () {
    $this->getJson('/api/v1/library/dishes')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'category' => ['key', 'name'], 'image_url', 'image_key', 'image_bg', 'default_weather', 'default_effort', 'default_cost', 'usual_meal']], 'meta' => ['version', 'total']]);

    $lentils = $this->getJson('/api/v1/library/dishes?category=lentils')->json('data');
    expect(collect($lentils)->pluck('category.key')->unique()->all())->toBe(['lentils']);

    $this->getJson('/api/v1/library/dishes?q=karahi')->assertJsonCount(2, 'data');
});

it('lists categories in order', function () {
    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonPath('data.0.key', 'vegetables')
        ->assertJsonCount(9, 'data');
});

it('lists popular cities and provinces, with search', function () {
    $this->getJson('/api/v1/cities')
        ->assertOk()
        ->assertJsonCount(5, 'data.popular')
        ->assertJsonPath('data.provinces.0.name', 'Punjab');

    $result = $this->getJson('/api/v1/cities?q=karac')->json('data.provinces');
    expect(collect($result)->flatMap(fn ($p) => collect($p['cities'])->pluck('name'))->all())->toBe(['Karachi']);
});

it('returns bucketed weather for a city', function () {
    $lahore = \App\Models\City::where('slug', 'lahore')->first();

    $this->getJson("/api/v1/weather?city_id={$lahore->id}")
        ->assertOk()
        ->assertJsonPath('data.condition', 'hot')
        ->assertJsonPath('data.temp_now', 31)
        ->assertJsonPath('data.summary', '31° · hot · rain 10%')
        ->assertJsonPath('data.city_label', 'Lahore');
});

it('maps raw weather to the four conditions', function (float $temp, int $code, int $rain, string $expected) {
    expect(\App\Services\WeatherService::condition($temp, $code, $rain))->toBe($expected);
})->with([
    [34, 0, 0, 'hot'], [22, 2, 20, 'mild'], [12, 3, 0, 'cold'], [24, 63, 80, 'rainy'], [28, 1, 70, 'rainy'],
]);

it('serves terms and privacy', function () {
    $this->getJson('/api/v1/legal/terms')->assertOk()->assertJsonPath('data.title', 'Terms of use')->assertJsonCount(5, 'data.sections');
    $this->getJson('/api/v1/legal/privacy')->assertOk()->assertJsonPath('data.title', 'Privacy policy');
});

it('previews 3 picks from different categories for a guest', function () {
    $response = $this->postJson('/api/v1/preview-picks', [
        'dish_ids' => dishIds(KITCHEN_DISHES),
        'family_size' => 4, 'budget' => 'normal', 'repeat_gap_days' => 5, 'meals' => ['lunch', 'dinner'],
        'location' => ['mode' => 'city', 'city_id' => \App\Models\City::where('slug', 'lahore')->value('id')],
    ])->assertOk();

    $picks = collect($response->json('data.picks'));
    expect($picks)->toHaveCount(3)
        ->and($picks->pluck('category.key')->unique())->toHaveCount(3)
        ->and($response->json('data.weather.condition'))->toBe('hot');
});
