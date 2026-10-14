<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\Kitchen;
use App\Models\KitchenDish;
use App\Models\MealLog;
use App\Services\NotificationService;
use App\Services\PickService;
use App\Support\UserClock;
use Illuminate\Console\Command;

/**
 * Every minute: send the morning reminder to users whose reminder time is now.
 * Skipped when the user already logged a meal today, or was already notified today.
 */
class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch {--force : Ignore the time check (send to everyone due today)}';

    protected $description = 'Send each user today’s 3 picks at their reminder time.';

    public function handle(PickService $picks, NotificationService $notifications): int
    {
        $sent = 0;

        Kitchen::query()
            ->with('user')
            ->where('notifications_enabled', true)
            ->whereHas('user', fn ($q) => $q->where('is_admin', false))
            ->chunkById(200, function ($kitchens) use ($picks, $notifications, &$sent) {
                foreach ($kitchens as $kitchen) {
                    $user = $kitchen->user;
                    $now = UserClock::now($user);

                    if (! $this->option('force') && $now->format('H:i') !== $kitchen->notify_time) {
                        continue;
                    }

                    if (MealLog::where('user_id', $user->id)->where('date', $now->toDateString())->exists()) {
                        continue;
                    }

                    $pick = $picks->today($user);
                    if ($pick->notified_at) {
                        continue;
                    }

                    $names = KitchenDish::withDetails()->whereIn('id', $pick->dishIds())->get()
                        ->sortBy(fn ($d) => array_search($d->id, $pick->dishIds(), true))
                        ->map->effectiveName()->values();

                    if ($names->isEmpty()) {
                        continue;
                    }

                    $first = KitchenDish::withDetails()->find($pick->dishIds()[0] ?? 0);

                    $notifications->notify($user, NotificationType::DailyPicks, 'Today’s 3 picks are ready',
                        'Today’s picks: '.$names->join(', '), 'Pick one', '/today', $first?->imageKey(),
                        push: true, pushTitle: config('handi.app_name'));

                    $pick->update(['notified_at' => now()]);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
