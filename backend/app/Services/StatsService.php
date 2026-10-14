<?php

namespace App\Services;

use App\Models\MealLog;
use App\Models\User;
use App\Support\UserClock;
use Carbon\CarbonImmutable;

class StatsService
{
    /** @return array{streak_days: int, meals_this_month: int} */
    public function forUser(User $user): array
    {
        $today = UserClock::today($user);

        $dates = MealLog::where('user_id', $user->id)
            ->where('date', '>=', $today->subDays(400)->toDateString())
            ->distinct()
            ->pluck('date')
            ->map(fn ($d) => $d instanceof CarbonImmutable ? $d->toDateString() : substr((string) $d, 0, 10))
            ->flip();

        // Streak counts back from today (or yesterday if nothing is logged yet today).
        $day = $dates->has($today->toDateString()) ? $today : $today->subDay();
        $streak = 0;
        while ($dates->has($day->toDateString())) {
            $streak++;
            $day = $day->subDay();
        }

        $monthMeals = MealLog::where('user_id', $user->id)
            ->where('date', '>=', $today->startOfMonth()->toDateString())
            ->where('date', '<=', $today->toDateString())
            ->count();

        return ['streak_days' => $streak, 'meals_this_month' => $monthMeals];
    }
}
