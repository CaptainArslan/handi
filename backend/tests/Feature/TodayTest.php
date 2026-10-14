<?php

use App\Models\DailyPick;
use App\Models\MealLog;

it('builds today with 3 picks from distinct categories', function () {
    $user = appUser();
    $res = $this->actingAs($user)->getJson('/api/v1/me/today')->assertOk();

    $picks = collect($res->json('data.picks'));
    expect($picks)->toHaveCount(3)
        ->and($picks->pluck('category.key')->unique())->toHaveCount(3)
        ->and($picks->first()['is_top'])->toBeTrue()
        ->and($res->json('data.stage'))->toBe('pick')
        ->and($res->json('data.shuffles_left'))->toBe(5)
        ->and($res->json('data.weather.condition'))->toBe('hot')
        ->and($res->json('data.greeting_name'))->toBe('Hina');

    // Same picks on a second visit
    $again = $this->actingAs($user)->getJson('/api/v1/me/today')->json('data.picks.*.id');
    expect($again)->toBe($picks->pluck('id')->all());
});

it('shuffles up to 5 times a day', function () {
    $user = appUser();
    $this->actingAs($user)->getJson('/api/v1/me/today');

    foreach (range(4, 0) as $left) {
        $this->actingAs($user)->postJson('/api/v1/me/today/shuffle')->assertOk()->assertJsonPath('data.shuffles_left', $left);
    }

    $this->actingAs($user)->postJson('/api/v1/me/today/shuffle')->assertStatus(429)->assertJsonPath('shuffles_left', 0);
});

it('plans, unplans and finishes the loop by logging', function () {
    $user = appUser();
    $first = $this->actingAs($user)->getJson('/api/v1/me/today')->json('data.picks.0.id');

    $this->actingAs($user)->putJson('/api/v1/me/today/plan', ['kitchen_dish_id' => 999999, 'meal' => 'lunch'])
        ->assertJsonValidationErrors(['kitchen_dish_id' => 'Pick one of today’s dishes.']);

    $this->actingAs($user)->putJson('/api/v1/me/today/plan', ['kitchen_dish_id' => $first, 'meal' => 'dinner'])
        ->assertOk()->assertJsonPath('data.stage', 'planned')->assertJsonPath('data.plan.meal', 'dinner');

    $this->actingAs($user)->deleteJson('/api/v1/me/today/plan')->assertOk()->assertJsonPath('data.stage', 'pick');

    $this->actingAs($user)->putJson('/api/v1/me/today/plan', ['kitchen_dish_id' => $first, 'meal' => 'lunch']);
    $this->actingAs($user)->postJson('/api/v1/me/meals', [
        'kitchen_dish_id' => $first, 'date' => now('Asia/Karachi')->toDateString(), 'meal' => 'lunch', 'source' => 'suggestion',
    ])->assertCreated();

    $this->actingAs($user)->getJson('/api/v1/me/today')->assertJsonPath('data.stage', 'done')->assertJsonPath('data.log.kitchen_dish_id', $first);
});

it('avoids dishes cooked within the repeat gap', function () {
    $user = appUser();
    $today = now('Asia/Karachi')->toDateString();

    // Cook everything except 3 dishes yesterday → those 3 must be the picks.
    $dishes = $user->kitchenDishes()->get();
    $keep = $dishes->groupBy(fn ($d) => $d->effectiveCategory()->key)->map->first()->take(3)->pluck('id');
    foreach ($dishes->whereNotIn('id', $keep) as $d) {
        MealLog::create(['user_id' => $user->id, 'kitchen_dish_id' => $d->id, 'dish_name' => $d->effectiveName(), 'date' => now('Asia/Karachi')->subDay()->toDateString(), 'meal' => 'lunch', 'source' => 'manual', 'logged_at' => now()]);
    }
    DailyPick::where('user_id', $user->id)->delete();

    $ids = $this->actingAs($user)->getJson('/api/v1/me/today')->json('data.picks.*.id');
    expect(collect($ids)->sort()->values()->all())->toBe($keep->sort()->values()->all());
    expect($today)->not->toBeEmpty();
});

it('previews picks before sign-up', function () {
    seedCatalog();
    $this->postJson('/api/v1/preview-picks', [
        'dish_ids' => dishIds(KITCHEN_DISHES),
        'location' => ['mode' => 'city', 'city_id' => \App\Models\City::where('slug', 'lahore')->value('id')],
    ])->assertOk()->assertJsonCount(3, 'data.picks');
});

it('needs a token', function () {
    $this->getJson('/api/v1/me/today')->assertUnauthorized();
});
