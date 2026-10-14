<?php

use App\Mail\AuthCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

it('shows the signed-in user with their kitchen', function () {
    $user = appUser();
    $this->actingAs($user)->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.email', 'hina@example.com')
        ->assertJsonPath('data.kitchen.family_size', 4)
        ->assertJsonPath('data.kitchen.location.city', 'Lahore');
});

it('renames and changes email with a code', function () {
    Mail::fake();
    $user = appUser();

    $this->actingAs($user)->patchJson('/api/v1/me', ['name' => 'H1na'])->assertJsonValidationErrors(['name' => 'Use letters and spaces only.']);
    $this->actingAs($user)->patchJson('/api/v1/me', ['name' => 'Hina Khan'])->assertOk();

    $this->actingAs($user)->patchJson('/api/v1/me', ['email' => 'new@example.com', 'current_password' => 'wrong123'])
        ->assertJsonValidationErrors(['current_password' => 'That password is incorrect.']);
    $this->actingAs($user)->patchJson('/api/v1/me', ['email' => 'new@example.com', 'current_password' => 'kitchen123'])
        ->assertOk()->assertJsonPath('meta.email_change_pending', true);

    $code = null;
    Mail::assertSent(AuthCodeMail::class, function ($mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $this->actingAs($user)->postJson('/api/v1/me/email/verify', ['code' => '000000'])->assertUnprocessable();
    $this->actingAs($user)->postJson('/api/v1/me/email/verify', ['code' => $code])->assertOk();
    expect($user->fresh()->email)->toBe('new@example.com');
});

it('changes the password', function () {
    $user = appUser();
    $this->actingAs($user)->putJson('/api/v1/me/password', ['current_password' => 'nope1234', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
        ->assertJsonValidationErrors(['current_password' => 'Current password is incorrect.']);
    $this->actingAs($user)->putJson('/api/v1/me/password', ['current_password' => 'kitchen123', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
        ->assertNoContent();
    expect(Hash::check('newpass123', $user->fresh()->password))->toBeTrue();
});

it('updates kitchen settings and location', function () {
    $user = appUser();
    $this->actingAs($user)->patchJson('/api/v1/me/kitchen', ['family_size' => 21])->assertUnprocessable();
    $this->actingAs($user)->patchJson('/api/v1/me/kitchen', ['family_size' => 6, 'budget' => 'low', 'repeat_gap_days' => 7])
        ->assertOk()->assertJsonPath('data.family_size', 6)->assertJsonPath('data.repeat_gap_days', 7);

    $this->actingAs($user)->putJson('/api/v1/me/location', ['mode' => 'gps', 'lat' => 24.86, 'lng' => 67.01])
        ->assertOk()->assertJsonPath('data.kitchen.location.mode', 'gps');
});

it('deletes the account only with password and DELETE', function () {
    $user = appUser();
    $this->actingAs($user)->deleteJson('/api/v1/me', ['password' => 'kitchen123', 'confirmation' => 'delete'])->assertUnprocessable();
    $this->actingAs($user)->deleteJson('/api/v1/me', ['password' => 'kitchen123', 'confirmation' => 'DELETE'])->assertNoContent();
    expect(User::find($user->id))->toBeNull();
});
