<?php

use App\Models\AppNotification;
use App\Models\MealLog;
use Illuminate\Support\Str;

function karachiToday(): string
{
    return now('Asia/Karachi')->toDateString();
}

it('logs a meal and keeps a single entry for duplicates', function () {
    $user = appUser();
    $dish = $user->kitchenDishes()->first();
    $payload = ['kitchen_dish_id' => $dish->id, 'date' => karachiToday(), 'meal' => 'dinner'];

    $this->actingAs($user)->postJson('/api/v1/me/meals', $payload)->assertCreated()
        ->assertJsonPath('meta.duplicate', false)->assertJsonPath('data.dish_name', $dish->effectiveName());
    $this->actingAs($user)->postJson('/api/v1/me/meals', $payload)->assertOk()->assertJsonPath('meta.duplicate', true);

    expect(MealLog::count())->toBe(1);
});

it('only allows the last 60 days', function () {
    $user = appUser();
    $dish = $user->kitchenDishes()->first();

    $this->actingAs($user)->postJson('/api/v1/me/meals', ['kitchen_dish_id' => $dish->id, 'date' => now('Asia/Karachi')->subDays(61)->toDateString(), 'meal' => 'lunch'])
        ->assertJsonValidationErrors('date');
    $this->actingAs($user)->postJson('/api/v1/me/meals', ['kitchen_dish_id' => $dish->id, 'date' => now('Asia/Karachi')->addDay()->toDateString(), 'meal' => 'lunch'])
        ->assertJsonValidationErrors('date');
});

it('edits, deletes and restores a log, and groups history by day', function () {
    $user = appUser();
    $dish = $user->kitchenDishes()->first();
    $id = $this->actingAs($user)->postJson('/api/v1/me/meals', ['kitchen_dish_id' => $dish->id, 'date' => karachiToday(), 'meal' => 'lunch'])->json('data.id');

    $this->actingAs($user)->patchJson("/api/v1/me/meals/{$id}", ['meal' => 'dinner'])->assertOk()->assertJsonPath('data.meal', 'dinner');

    $month = $this->actingAs($user)->getJson('/api/v1/me/meals')->assertOk();
    expect($month->json('data.logged_days'))->toBe(1)->and($month->json('data.days.'.karachiToday()))->toHaveCount(1);

    $this->actingAs($user)->deleteJson("/api/v1/me/meals/{$id}")->assertNoContent();
    expect(MealLog::count())->toBe(0);
    $this->actingAs($user)->postJson("/api/v1/me/meals/{$id}/restore")->assertOk();
    expect(MealLog::count())->toBe(1);

    $this->actingAs($user)->getJson('/api/v1/me/stats')->assertOk()->assertJsonPath('data.meals_this_month', 1);
});

it('syncs an offline queue idempotently', function () {
    $user = appUser();
    $dish = $user->kitchenDishes()->first();
    $uuid = (string) Str::uuid();
    $ops = ['operations' => [
        ['op' => 'meal.create', 'payload' => ['id' => $uuid, 'kitchen_dish_id' => $dish->id, 'date' => karachiToday(), 'meal' => 'lunch']],
        ['op' => 'meal.create', 'payload' => ['kitchen_dish_id' => $dish->id, 'date' => '1999-01-01', 'meal' => 'lunch']],
    ]];

    $res = $this->actingAs($user)->postJson('/api/v1/me/sync', $ops)->assertOk();
    expect($res->json('data.results.0'))->toMatchArray(['ok' => true, 'id' => $uuid])
        ->and($res->json('data.results.1.ok'))->toBeFalse();

    $this->actingAs($user)->postJson('/api/v1/me/sync', $ops)->assertOk();
    expect(MealLog::count())->toBe(1)
        ->and(AppNotification::where('title', '1 meal synced')->count())->toBe(1);
});
