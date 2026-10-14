<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;

/** "Today" and "now" in the user's timezone (default Asia/Karachi). */
class UserClock
{
    public static function timezone(?User $user = null): string
    {
        return $user?->timezone ?: config('handi.timezone');
    }

    public static function now(?User $user = null): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone($user));
    }

    public static function today(?User $user = null): CarbonImmutable
    {
        return self::now($user)->startOfDay();
    }
}
