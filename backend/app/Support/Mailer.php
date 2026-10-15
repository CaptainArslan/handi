<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notice emails (welcome, password/email changed, account deleted).
 * They must never break the request they follow: a mail-server problem is logged, not thrown.
 * (Code emails — reset / confirm — go through AuthCodeService and DO surface errors, because the user is waiting for them.)
 */
class Mailer
{
    public static function notice(string $to, Mailable $mail): void
    {
        try {
            Mail::to($to)->send($mail);
        } catch (Throwable $e) {
            Log::warning('Notice email not sent', ['to' => $to, 'mail' => $mail::class, 'error' => $e->getMessage()]);
        }
    }

    /** "Wed 15 Oct 2026, 9:41 PM PKT" in the user's own timezone. */
    public static function when(?User $user = null): string
    {
        return now($user?->timezone ?: config('handi.timezone'))->format('D j M Y, g:i A T');
    }

    /** A plain-language name for where the request came from. */
    public static function device(Request $request): string
    {
        $ua = (string) $request->userAgent();

        return match (true) {
            str_contains($ua, 'okhttp') || str_contains($ua, 'Android') && ! str_contains($ua, 'Mozilla') => 'Android app',
            str_contains($ua, 'CFNetwork') || str_contains($ua, 'Darwin') => 'iPhone app',
            str_contains($ua, 'Android') => 'Android browser',
            str_contains($ua, 'Mozilla') => 'Web browser',
            default => 'Handi app',
        };
    }
}
