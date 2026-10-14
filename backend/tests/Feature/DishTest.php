<?php

use App\Models\Dish;
use App\Models\KitchenDish;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lists my dishes with search and category filters', function () {
    $user = appUser();

    $this->actingAs($user)->getJson('/api/v1/me/dishes')->assertOk()
        ->assertJsonPath('meta.total', 13)->assertJsonPath('meta.custom_count', 1);

    $this->actingAs($user)->getJson('/api/v1/me/dishes?q=karahi')->assertOk()->assertJsonPath('meta.matches', 2);
    $this->actingAs($user)->getJson('/api/v1/me/dishes?custom=1')->assertJsonPath('meta.matches', 1);
});

it('adds library dishes and ignores ones already in the kitchen', function () {
    $user = appUser();
    $new = Dish::whereNotIn('name', KITCHEN_DISHES)->limit(2)->pluck('id')->all();

    $this->actingAs($user)->postJson('/api/v1/me/dishes', ['library_dish_ids' => [...$new, dishIds(['Bhindi'])[0]]])
        ->assertCreated()->assertJsonPath('data.added', 2);
});

it('overrides tags and resets them', function () {
    $user = appUser();
    $dish = $user->kitchenDishes()->whereNotNull('dish_id')->first();

    $this->actingAs($user)->patchJson("/api/v1/me/dishes/{$dish->id}", ['weather' => [], 'effort' => 'quick'])
        ->assertJsonValidationErrors(['weather' => 'Pick at least one weather, or “Any”.']);

    $this->actingAs($user)->patchJson("/api/v1/me/dishes/{$dish->id}", ['weather' => ['cold'], 'effort' => 'quick'])
        ->assertOk()->assertJsonPath('data.weather', ['cold'])->assertJsonPath('data.effort', 'quick')
        ->assertJsonPath('data.has_overrides', true);

    $this->actingAs($user)->deleteJson("/api/v1/me/dishes/{$dish->id}/overrides")
        ->assertOk()->assertJsonPath('data.has_overrides', false);
});

it('removes and restores a dish (undo)', function () {
    $user = appUser();
    $dish = $user->kitchenDishes()->first();

    $this->actingAs($user)->deleteJson("/api/v1/me/dishes/{$dish->id}")->assertNoContent();
    $this->actingAs($user)->getJson('/api/v1/me/dishes')->assertJsonPath('meta.total', 12);
    $this->actingAs($user)->postJson("/api/v1/me/dishes/{$dish->id}/restore")->assertOk();
    $this->actingAs($user)->getJson('/api/v1/me/dishes')->assertJsonPath('meta.total', 13);
});

it('cannot see another user’s dish', function () {
    $hina = appUser();
    $other = appUser(['email' => 'sara@example.com']);
    $dish = $hina->kitchenDishes()->first();

    $this->actingAs($other)->getJson("/api/v1/me/dishes/{$dish->id}")->assertNotFound();
});

it('creates, renames and deletes a custom dish', function () {
    Storage::fake('public');
    $user = appUser();

    $this->actingAs($user)->postJson('/api/v1/me/custom-dishes', ['name' => 'Bhindi', 'category' => 'vegetables', 'weather' => ['any']])
        ->assertJsonValidationErrors(['name' => '“Bhindi” is already in your dishes.']);

    $id = $this->actingAs($user)->post('/api/v1/me/custom-dishes', [
        'name' => 'Aunt Sara’s Haleem', 'category' => 'mutton', 'weather' => ['cold', 'rainy'],
        'effort' => 'heavy', 'cost' => 'medium', 'photo' => UploadedFile::fake()->image('haleem.jpg', 600, 600),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.is_custom', true)->json('data.id');

    expect(KitchenDish::find($id)->photo_path)->not->toBeNull();

    $this->actingAs($user)->patchJson("/api/v1/me/custom-dishes/{$id}", ['name' => 'Haleem'])->assertOk()->assertJsonPath('data.name', 'Haleem');
    $this->actingAs($user)->getJson('/api/v1/me/custom-dishes')->assertJsonCount(2, 'data');
    $this->actingAs($user)->deleteJson("/api/v1/me/custom-dishes/{$id}")->assertNoContent();
    $this->actingAs($user)->getJson('/api/v1/me/custom-dishes')->assertJsonCount(1, 'data');
});
