<?php

use App\Filament\Widgets\TopCookedDishes;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('keeps the grouped "most cooked" query valid under MySQL ONLY_FULL_GROUP_BY', function () {
    $user = appUser();
    $dish = $user->kitchenDishes()->first();
    $this->actingAs($user)->postJson('/api/v1/me/meals', ['kitchen_dish_id' => $dish->id, 'date' => now('Asia/Karachi')->toDateString(), 'meal' => 'lunch'])->assertSuccessful();
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');

    DB::enableQueryLog();
    Livewire::actingAs(User::factory()->create(['is_admin' => true]))
        ->test(TopCookedDishes::class)
        ->assertSee($dish->name);

    $sql = collect(DB::getQueryLog())->pluck('query')->first(fn (string $q) => str_contains($q, 'COUNT(DISTINCT user_id)'));

    // Every ORDER BY column must be grouped or aggregated — no "meal_logs.id" tie-breaker.
    expect($sql)->not->toContain('meal_logs`.`id`')->not->toContain('"meal_logs"."id"');
});
