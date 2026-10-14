<?php

use App\Mail\AuthCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create(['email' => 'hina@example.com', 'password' => 'kitchen123']);
    $this->user->createToken('old-phone');
});

function sentCode(): string
{
    $code = null;
    Mail::assertSent(AuthCodeMail::class, function (AuthCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    return $code;
}

it('sends a code without revealing whether the email exists', function () {
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com'])->assertStatus(202)->assertJsonPath('data.masked_email', 'n***@example.com');
    Mail::assertNothingSent();

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'hina@example.com'])
        ->assertStatus(202)
        ->assertJsonPath('data.expires_in', 900)
        ->assertJsonPath('data.resend_in', 45);
    Mail::assertSent(AuthCodeMail::class, 1);
});

it('allows resending only after 45 seconds', function () {
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'hina@example.com']);
    $this->postJson('/api/v1/auth/password/resend', ['email' => 'hina@example.com'])->assertStatus(429)->assertJsonStructure(['retry_after']);

    $this->travel(46)->seconds();
    $this->postJson('/api/v1/auth/password/resend', ['email' => 'hina@example.com'])->assertStatus(202);
    Mail::assertSent(AuthCodeMail::class, 2);
});

it('counts down wrong codes and locks after 5', function () {
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'hina@example.com']);
    $real = sentCode();
    $wrong = $real === '111111' ? '222222' : '111111';

    $this->postJson('/api/v1/auth/password/verify', ['email' => 'hina@example.com', 'code' => $wrong])
        ->assertUnprocessable()->assertJsonPath('attempts_left', 4)->assertJsonPath('message', 'That code doesn’t match. 4 attempts left.');

    foreach (range(1, 3) as $i) {
        $this->postJson('/api/v1/auth/password/verify', ['email' => 'hina@example.com', 'code' => $wrong]);
    }

    $this->postJson('/api/v1/auth/password/verify', ['email' => 'hina@example.com', 'code' => $wrong])->assertStatus(429);
    $this->postJson('/api/v1/auth/password/verify', ['email' => 'hina@example.com', 'code' => $real])->assertStatus(429);
});

it('resets the password with a verified code and signs out every phone', function () {
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'hina@example.com']);
    $token = $this->postJson('/api/v1/auth/password/verify', ['email' => 'hina@example.com', 'code' => sentCode()])->assertOk()->json('data.reset_token');

    $this->postJson('/api/v1/auth/password/reset', ['reset_token' => $token, 'password' => ' spaced1', 'password_confirmation' => ' spaced1'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->postJson('/api/v1/auth/password/reset', ['reset_token' => $token, 'password' => 'newpass99', 'password_confirmation' => 'newpass99'])->assertNoContent();

    expect($this->user->tokens()->count())->toBe(0);
    $this->postJson('/api/v1/auth/login', ['email' => 'hina@example.com', 'password' => 'newpass99'])->assertOk();

    // The token works once.
    $this->postJson('/api/v1/auth/password/reset', ['reset_token' => $token, 'password' => 'another99', 'password_confirmation' => 'another99'])->assertUnprocessable();
});
