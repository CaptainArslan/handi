<?php

use App\Models\ActivityLog;
use App\Models\User;

beforeEach(fn () => seedCatalog());

it('signs up with the whole onboarding kitchen', function () {
    $response = $this->postJson('/api/v1/auth/register', registerPayload())
        ->assertCreated()
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'first_name', 'email', 'masked_email', 'kitchen' => ['family_size', 'budget', 'repeat_gap_days', 'meals', 'location', 'notifications'], 'counts']]]);

    expect($response->json('data.user.first_name'))->toBe('Hina')
        ->and($response->json('data.user.masked_email'))->toBe('h***@example.com')
        ->and($response->json('data.user.kitchen.location.city'))->toBe('Lahore')
        ->and($response->json('data.user.counts.dishes'))->toBe(count(KITCHEN_DISHES) + 1)
        ->and($response->json('data.user.counts.custom_dishes'))->toBe(1);

    $user = User::first();
    expect($user->appNotifications()->first()->title)->toBe('Your kitchen is ready')
        ->and(ActivityLog::where('event', 'auth.registered')->count())->toBe(1);
});

it('needs at least 10 dishes', function () {
    $this->postJson('/api/v1/auth/register', registerPayload(['dish_ids' => dishIds(['Bhindi', 'Biryani']), 'custom_dishes' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['dish_ids' => 'Pick at least 10 dishes.']);
});

it('uses the design’s sign-up error copy', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', registerPayload([
        'name' => 'H', 'email' => 'taken@example.com', 'password' => 'short', 'password_confirmation' => 'nope', 'terms_accepted' => false,
    ]))->assertUnprocessable()->assertJsonValidationErrors([
        'name' => 'Name is too short.',
        'email' => 'This email already has an account.',
        'password' => 'Use at least 8 characters.',
        'terms_accepted' => 'Please accept the terms to continue.',
    ]);
});

it('logs in and counts down wrong passwords, then locks for 60 seconds', function () {
    User::factory()->create(['email' => 'ali@example.com', 'password' => 'kitchen123']);

    $this->postJson('/api/v1/auth/login', ['email' => 'ali@example.com', 'password' => 'wrong'])
        ->assertUnauthorized()
        ->assertJsonPath('attempts_left', 4)
        ->assertJsonPath('message', 'Email or password is incorrect. 4 tries left.');

    foreach (range(1, 3) as $i) {
        $this->postJson('/api/v1/auth/login', ['email' => 'ali@example.com', 'password' => 'wrong']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'ali@example.com', 'password' => 'wrong'])->assertStatus(429)->assertJsonStructure(['retry_after']);
    $this->postJson('/api/v1/auth/login', ['email' => 'ali@example.com', 'password' => 'kitchen123'])->assertStatus(429);

    $this->travel(61)->seconds();
    $this->postJson('/api/v1/auth/login', ['email' => 'ALI@example.com ', 'password' => 'kitchen123'])->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);
    expect(User::first()->last_login_at)->not->toBeNull();
});

it('logs out this phone only', function () {
    $user = User::factory()->create();
    $token = $user->createToken('a')->plainTextToken;
    $user->createToken('b');

    $this->withToken($token)->postJson('/api/v1/me/devices', ['device_id' => 'phone-a', 'platform' => 'android', 'expo_push_token' => 'ExponentPushToken[a]'])->assertCreated();
    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($user->tokens()->count())->toBe(1)->and($user->devices()->count())->toBe(0);
});

it('rejects protected routes without a token', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    $this->getJson('/api/v1/me/today')->assertUnauthorized();
});
