<?php

use App\Mail\AccountDeletedMail;
use App\Mail\AuthCodeMail;
use App\Mail\EmailChangedMail;
use App\Mail\PasswordChangedMail;
use App\Mail\WelcomeMail;
use App\Models\AuthCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => seedCatalog());

it('renders every email in the Handi design with a plain-text version', function () {
    $user = appUser();
    $mails = [
        new WelcomeMail($user),
        new AuthCodeMail('482913', AuthCode::PASSWORD_RESET, 'Hina', $user->email),
        new AuthCodeMail('731604', AuthCode::EMAIL_CHANGE, 'Hina', 'hina.new@example.com'),
        new PasswordChangedMail($user, 'Wed 15 Oct 2026, 9:41 PM PKT', 'Android app'),
        new EmailChangedMail($user->email, 'hina.new@example.com', 'Wed 15 Oct 2026, 9:52 PM PKT'),
        new AccountDeletedMail($user->email, 18, 64, 5),
    ];

    foreach ($mails as $mail) {
        $html = $mail->render();
        expect($html)->toContain('images/brand/handi-email-logo.png')
            ->toContain('#0F5C4D')
            ->toContain(config('handi.support_email'));
        expect(view($mail->content()->text, $mail->content()->with)->render())->not->toBe('');
    }

    expect($mails[1]->render())->toContain('482 913')->toContain('Expires in 15 minutes');
    expect($mails[1]->envelope()->subject)->toContain('482913');
    expect($mails[2]->render())->toContain('hina.new@example.com')->toContain('731 604');
    expect($mails[0]->render())->toContain('See today’s picks')->toContain('/app/today');
});

it('sends a welcome email after sign-up', function () {
    Mail::fake();
    $this->postJson('/api/v1/auth/register', registerPayload(['name' => 'John Doe', 'email' => 'john@example.com']))->assertCreated();

    Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $m) => $m->hasTo('john@example.com') && str_contains($m->render(), 'Your kitchen is ready, John') && str_contains($m->render(), 'Lahore’s weather'));
});

it('sends a security notice when the password changes', function () {
    Mail::fake();
    $user = appUser();

    $this->actingAs($user)->withHeader('User-Agent', 'okhttp/4.12.0')->putJson('/api/v1/me/password', [
        'current_password' => 'kitchen123', 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
    ])->assertNoContent();

    Mail::assertSent(PasswordChangedMail::class, fn (PasswordChangedMail $m) => $m->hasTo($user->email) && $m->device === 'Android app');
});

it('sends a goodbye email when the account is deleted', function () {
    Mail::fake();
    $user = appUser();
    $email = $user->email;

    $this->actingAs($user)->deleteJson('/api/v1/me', ['password' => 'kitchen123', 'confirmation' => 'DELETE'])->assertNoContent();

    Mail::assertSent(AccountDeletedMail::class, fn (AccountDeletedMail $m) => $m->hasTo($email) && $m->dishes > 0);
    expect(User::where('email', $email)->exists())->toBeFalse();
});

it('never fails the request when the mail server is down', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
    $user = appUser();

    $this->actingAs($user)->putJson('/api/v1/me/password', [
        'current_password' => 'kitchen123', 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
    ])->assertNoContent();
});
