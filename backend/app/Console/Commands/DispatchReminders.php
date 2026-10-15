<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\DailyPick;
use App\Models\Kitchen;
use App\Models\KitchenDish;
use App\Models\MealLog;
use App\Services\NotificationService;
use App\Services\PickService;
use App\Support\UserClock;
use Illuminate\Console\Command;
use Throwable;

/**
 * Every minute: send the morning reminder to users whose reminder time has come (with a catch-up window).
 * Skipped when the user already logged a meal today, or was already notified today.
 */
class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch {--force : Ignore the time check (send to everyone due today)}';

    protected $description = 'Send each user today’s 3 picks at their reminder time.';

    /** Minutes after the chosen time in which a missed reminder is still sent (slow run, deploy, restart). */
    private const CATCH_UP_MINUTES = 60;

    public function handle(PickService $picks, NotificationService $notifications): int
    {
        $sent = 0;
        $failed = 0;

        Kitchen::query()
            ->with('user')
            ->where('notifications_enabled', true)
            ->whereHas('user', fn ($q) => $q->where('is_admin', false))
            ->chunkById(200, function ($kitchens) use ($picks, $notifications, &$sent, &$failed) {
                foreach ($kitchens as $kitchen) {
                    // One user's problem (bad data, push service down) must never stop everyone else's reminder.
                    try {
                        $sent += $this->remind($kitchen, $picks, $notifications) ? 1 : 0;
                    } catch (Throwable $e) {
                        $failed++;
                        report($e);
                    }
                }
            });

        $this->info("Sent {$sent} reminder(s)".($failed ? ", {$failed} failed" : '').'.');

        return self::SUCCESS;
    }

    private function remind(Kitchen $kitchen, PickService $picks, NotificationService $notifications): bool
    {
        $user = $kitchen->user;
        $now = UserClock::now($user);

        // Due from the chosen time until CATCH_UP_MINUTES later, so a slow or interrupted run catches up
        // instead of skipping everyone it didn't reach within that exact minute.
        if (! $this->option('force')) {
            [$h, $m] = array_map('intval', explode(':', (string) $kitchen->notify_time));
            $minutesLate = ($now->hour * 60 + $now->minute) - ($h * 60 + $m);
            if ($minutesLate < 0 || $minutesLate > self::CATCH_UP_MINUTES) {
                return false;
            }
        }

        if (MealLog::where('user_id', $user->id)->where('date', $now->toDateString())->exists()) {
            return false;
        }

        $pick = $picks->today($user);
        if ($pick->notified_at) {
            return false;
        }

        $names = KitchenDish::withDetails()->whereIn('id', $pick->dishIds())->get()
            ->sortBy(fn ($d) => array_search($d->id, $pick->dishIds(), true))
            ->map->effectiveName()->values();

        if ($names->isEmpty()) {
            return false;
        }

        // Claim the reminder atomically: if another run already sent it, stop here (no double reminders).
        if (DailyPick::whereKey($pick->id)->whereNull('notified_at')->update(['notified_at' => now()]) === 0) {
            return false;
        }

        $first = KitchenDish::withDetails()->find($pick->dishIds()[0] ?? 0);

        $notifications->notify($user, NotificationType::DailyPicks, 'Today’s 3 picks are ready',
            'Today’s picks: '.$names->join(', '), 'Pick one', '/today', $first?->imageKey(),
            push: true, pushTitle: config('handi.app_name'));

        return true;
    }
}
