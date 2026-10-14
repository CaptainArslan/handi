<?php

use App\Models\AppNotification;
use App\Models\DailyPick;
use Illuminate\Support\Facades\Http;

it('lists, reads, deletes and restores notifications', function () {
    $user = appUser();
    $res = $this->actingAs($user)->getJson('/api/v1/me/notifications')->assertOk();
    $id = $res->json('data.0.id');
    expect($res->json('meta.unread_count'))->toBe(1);

    $this->actingAs($user)->patchJson("/api/v1/me/notifications/{$id}", ['read' => true])->assertOk();
    $this->actingAs($user)->getJson('/api/v1/me/notifications?filter=unread')->assertJsonCount(0, 'data');

    $this->actingAs($user)->deleteJson("/api/v1/me/notifications/{$id}")->assertNoContent();
    $this->actingAs($user)->postJson("/api/v1/me/notifications/{$id}/restore")->assertOk();
    $this->actingAs($user)->postJson('/api/v1/me/notifications/read-all')->assertNoContent();
});

it('saves reminder settings and registers devices', function () {
    $user = appUser();
    $this->actingAs($user)->putJson('/api/v1/me/notification-settings', ['enabled' => true, 'time' => '07:30'])
        ->assertOk()->assertJsonPath('data.time', '07:30');

    $this->actingAs($user)->postJson('/api/v1/me/devices', ['device_id' => 'abc', 'platform' => 'android', 'expo_push_token' => 'ExponentPushToken[xyz]'])
        ->assertCreated();
    $this->actingAs($user)->postJson('/api/v1/me/notification-settings/test')->assertStatus(202)->assertJsonPath('data.devices', 1);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'push.test'));
});

it('sends the morning reminder once', function () {
    $user = appUser();
    $user->kitchen->update(['notifications_enabled' => true]);
    $before = AppNotification::where('type', 'daily_picks')->count();

    $this->artisan('reminders:dispatch --force')->assertSuccessful();
    $this->artisan('reminders:dispatch --force')->assertSuccessful();

    expect(AppNotification::where('type', 'daily_picks')->count())->toBe($before + 1)
        ->and(DailyPick::where('user_id', $user->id)->first()->notified_at)->not->toBeNull();
});
