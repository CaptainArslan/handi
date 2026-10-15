<?php

namespace App\Services;

use App\Mail\AuthCodeMail;
use App\Models\AuthCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** 6-digit email codes: valid 15 minutes, 5 attempts, resend after 45 seconds. */
class AuthCodeService
{
    public const OK = 'ok';

    public const WRONG = 'wrong';

    public const LOCKED = 'locked';

    public const EXPIRED = 'expired';

    /** Latest usable code for this purpose and email. */
    public function current(string $purpose, string $email): ?AuthCode
    {
        return AuthCode::where('purpose', $purpose)
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();
    }

    /** Seconds until a new code may be sent (0 = now). */
    public function resendWait(?AuthCode $code): int
    {
        if (! $code) {
            return 0;
        }

        $ready = $code->last_sent_at->copy()->addSeconds((int) config('handi.auth.code_resend_seconds', 45));

        return max(0, (int) ceil(now()->diffInSeconds($ready, false)));
    }

    /** Create (replacing any older one) and email a new code. */
    public function send(string $purpose, string $email, ?User $user, string $name = ''): AuthCode
    {
        AuthCode::where('purpose', $purpose)->where('email', $email)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $plain = (string) random_int(100000, 999999);

        $code = AuthCode::create([
            'purpose' => $purpose,
            'email' => $email,
            'user_id' => $user?->id,
            'code_hash' => Hash::make($plain),
            'attempts_left' => (int) config('handi.auth.code_attempts', 5),
            'expires_at' => now()->addMinutes((int) config('handi.auth.code_ttl_minutes', 15)),
            'last_sent_at' => now(),
        ]);

        Mail::to($email)->send(new AuthCodeMail($plain, $purpose, $name, $email));

        return $code;
    }

    /**
     * Check a code. On success for a password reset, returns a one-time reset token.
     *
     * @return array{status: string, attempts_left?: int, reset_token?: string}
     */
    public function verify(AuthCode $code, string $plain): array
    {
        if (! $code->isUsable()) {
            return ['status' => self::EXPIRED];
        }

        // Use up one attempt BEFORE checking, atomically, so parallel guesses can't exceed the limit.
        $claimed = AuthCode::whereKey($code->id)->where('attempts_left', '>', 0)->decrement('attempts_left');
        if ($claimed === 0) {
            return ['status' => self::LOCKED, 'attempts_left' => 0];
        }
        $code->refresh();

        if (! Hash::check($plain, $code->code_hash)) {
            return $code->attempts_left <= 0
                ? ['status' => self::LOCKED, 'attempts_left' => 0]
                : ['status' => self::WRONG, 'attempts_left' => $code->attempts_left];
        }

        $token = Str::random(64);
        $code->forceFill(['verified_at' => now(), 'reset_token_hash' => hash('sha256', $token)])->save();

        return ['status' => self::OK, 'reset_token' => $token];
    }

    /** The verified reset code matching a reset token, if still valid. */
    public function findByResetToken(string $token): ?AuthCode
    {
        $code = AuthCode::where('purpose', AuthCode::PASSWORD_RESET)
            ->where('reset_token_hash', hash('sha256', $token))
            ->whereNotNull('verified_at')
            ->whereNull('consumed_at')
            ->first();

        return $code && $code->expires_at->copy()->addMinutes(15)->isFuture() ? $code : null;
    }
}
