<?php

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\AuthCode;
use App\Models\DailyPick;
use App\Services\AuthCodeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

it('still sends a reminder that was missed by a few minutes, but only once', function () {
    $user = appUser();
    $user->kitchen->update(['notifications_enabled' => true, 'notify_time' => '09:00']);
    Carbon::setTestNow(Carbon::parse('2026-10-16 09:07', $user->timezone ?: 'Asia/Karachi'));

    $this->artisan('reminders:dispatch')->assertSuccessful();
    $this->artisan('reminders:dispatch')->assertSuccessful();

    expect(AppNotification::where('user_id', $user->id)->where('type', 'daily_picks')->count())->toBe(1);
    Carbon::setTestNow();
});

it('does not send a reminder before the chosen time or long after it', function (string $time) {
    $user = appUser();
    $user->kitchen->update(['notifications_enabled' => true, 'notify_time' => '09:00']);
    Carbon::setTestNow(Carbon::parse("2026-10-16 {$time}", $user->timezone ?: 'Asia/Karachi'));

    $this->artisan('reminders:dispatch')->assertSuccessful();

    expect(AppNotification::where('user_id', $user->id)->where('type', 'daily_picks')->count())->toBe(0);
    Carbon::setTestNow();
})->with(['08:59', '10:30']);

it('never allows more code guesses than the limit', function () {
    Mail::fake();
    $user = appUser();
    $codes = app(AuthCodeService::class);
    $code = $codes->send(AuthCode::PASSWORD_RESET, $user->email, $user);

    $results = collect(range(1, 8))->map(fn () => $codes->verify($code->fresh(), '000000')['status']);

    expect($results->filter(fn ($s) => $s === AuthCodeService::WRONG)->count())->toBeLessThanOrEqual(4)
        ->and($code->fresh()->attempts_left)->toBe(0);
});

it('stops shuffling at the daily limit', function () {
    $user = appUser();
    $this->actingAs($user)->getJson('/api/v1/me/today')->assertOk();

    foreach (range(1, (int) config('handi.picks.shuffles_per_day', 5)) as $i) {
        $this->actingAs($user)->postJson('/api/v1/me/today/shuffle')->assertOk();
    }
    $this->actingAs($user)->postJson('/api/v1/me/today/shuffle')->assertStatus(429);

    expect(DailyPick::where('user_id', $user->id)->value('shuffles_used'))->toBe((int) config('handi.picks.shuffles_per_day', 5));
});

it('removes dish photos and sign-ins when an account is deleted', function () {
    Storage::fake('public');
    config(['handi.media_disk' => 'public', 'media-library.disk_name' => 'public']);
    Mail::fake();
    $user = appUser();
    $dish = $user->kitchenDishes()->first();
    $file = UploadedFile::fake()->image('dish.jpg', 400, 400);
    $this->actingAs($user)->post("/api/v1/me/dishes/{$dish->id}/photo", ['photo' => $file], ['Accept' => 'application/json'])->assertSuccessful();
    expect(Media::count())->toBe(1);

    $this->actingAs($user)->deleteJson('/api/v1/me', ['password' => 'kitchen123', 'confirmation' => 'DELETE'])->assertNoContent();

    expect(Media::count())->toBe(0)
        ->and(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(0)
        ->and(ActivityLog::where('event', 'account.deleted')->latest('id')->first()->description)->not->toContain($user->email);
});
