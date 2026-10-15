<?php

namespace App\Services;

use App\Enums\Budget;
use App\Models\DailyPick;
use App\Models\Kitchen;
use App\Models\KitchenDish;
use App\Models\MealLog;
use App\Models\User;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Chooses today's 3 dishes from the user's kitchen.
 *
 * Rules (from the design):
 *  - 3 picks from different categories;
 *  - skip anything cooked within the repeat gap (3/5/7/10 days);
 *  - prefer dishes that fit today's weather (or "any");
 *  - respect budget (low: no high-cost dishes; normal: fewer of them) and family size (big families: fewer heavy dishes);
 *  - only dishes for the meals the user wants ideas for;
 *  - shuffle swaps all 3, up to 5 times a day.
 * Rules are relaxed step by step when the kitchen is too small, so there are always picks.
 */
class PickService
{
    public function __construct(private readonly WeatherService $weather) {}

    /** Today's picks for the user, generated on first request of the day. */
    public function today(User $user): DailyPick
    {
        $date = UserClock::today($user);
        $pick = DailyPick::where('user_id', $user->id)->where('date', $date->toDateString())->first();

        if ($pick && $this->stillValid($user, $pick)) {
            return $pick;
        }

        return $this->generate($user, $date, $pick);
    }

    public function shufflesLeft(DailyPick $pick): int
    {
        return max(0, (int) config('handi.picks.shuffles_per_day', 5) - $pick->shuffles_used);
    }

    /** Swap all 3 picks. Throws when no shuffles are left. */
    public function shuffle(User $user): DailyPick
    {
        $pick = $this->today($user);

        // Claim one shuffle atomically, so parallel taps can't go past the daily limit.
        $claimed = DailyPick::whereKey($pick->id)
            ->where('shuffles_used', '<', (int) config('handi.picks.shuffles_per_day', 5))
            ->increment('shuffles_used');
        if ($claimed === 0) {
            throw new RuntimeException('No shuffles left today');
        }
        $pick->refresh();

        $current = $pick->dishIds();
        $pick->set_number++;

        return $this->generate($user, $pick->date, $pick, exclude: $current);
    }

    /**
     * Picks for a guest during onboarding (1.7), from library dish data. No database writes.
     *
     * @param  Collection<int, array>  $candidates  each: id, category, weather[], effort, cost, usual_meal
     * @return Collection<int, array>
     */
    public function preview(Collection $candidates, array $settings, ?string $condition): Collection
    {
        $scored = $candidates->map(fn (array $c) => $c + ['days_since' => null]);

        return $this->choose($scored, $settings, $condition, seed: 'preview:'.now()->toDateString(), exclude: []);
    }

    /** Last cooked date per kitchen dish id (Y-m-d). @return array<int, string> */
    public function lastCooked(User $user): array
    {
        return MealLog::query()
            ->where('user_id', $user->id)
            ->whereNotNull('kitchen_dish_id')
            ->groupBy('kitchen_dish_id')
            ->selectRaw('kitchen_dish_id, MAX(date) as last_date')
            ->pluck('last_date', 'kitchen_dish_id')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->all();
    }

    public static function daysSince(?string $date, CarbonImmutable $today): ?int
    {
        return $date ? (int) round(CarbonImmutable::parse(substr($date, 0, 10), $today->timezone)->startOfDay()->diffInDays($today->startOfDay())) : null;
    }

    private function stillValid(User $user, DailyPick $pick): bool
    {
        $ids = $pick->dishIds();

        if (count($ids) === 0) {
            return false;
        }

        $alive = KitchenDish::where('user_id', $user->id)->whereIn('id', $ids)->whereNull('removed_at')->count();

        return $alive === count($ids) || $pick->plan_kitchen_dish_id !== null;
    }

    private function generate(User $user, CarbonImmutable $date, ?DailyPick $pick, array $exclude = []): DailyPick
    {
        $kitchen = $user->kitchenOrDefault()->loadMissing('city.province');
        $weather = $this->weather->forKitchen($kitchen);
        $condition = $weather['condition'] ?? null;
        $lastCooked = $this->lastCooked($user);

        $dishes = KitchenDish::query()
            ->where('user_id', $user->id)
            ->inKitchen()
            ->withDetails()
            ->get();

        $candidates = $dishes
            ->filter(fn (KitchenDish $d) => $d->effectiveCategory() !== null || $d->is_custom)
            ->map(fn (KitchenDish $d) => [
                'id' => $d->id,
                'category' => $d->effectiveCategory()?->key ?? 'other',
                'weather' => $d->effectiveWeather(),
                'effort' => $d->effectiveEffort()->value,
                'cost' => $d->effectiveCost()->value,
                'usual_meal' => $d->effectiveUsualMeal()->value,
                'days_since' => self::daysSince($lastCooked[$d->id] ?? null, $date),
            ])
            ->values();

        $setNumber = $pick?->set_number ?? 0;
        $chosen = $this->choose($candidates, $this->settings($kitchen), $condition, "{$user->id}:{$date->toDateString()}:{$setNumber}", $exclude);

        $attributes = [
            'items' => $chosen->map(fn (array $c) => ['kitchen_dish_id' => $c['id'], 'reason' => $c['reason']])->values()->all(),
            'weather' => $weather,
            'generated_at' => now(),
        ];

        if ($pick) {
            $pick->fill($attributes);
            // A new set of picks clears a plan that is no longer among them.
            if ($pick->plan_kitchen_dish_id && ! in_array($pick->plan_kitchen_dish_id, $chosen->pluck('id')->all(), true)) {
                $pick->plan_kitchen_dish_id = null;
                $pick->plan_meal = null;
                $pick->planned_at = null;
            }
            $pick->save();

            return $pick;
        }

        try {
            return DailyPick::create($attributes + [
                'user_id' => $user->id,
                'date' => $date,
                'set_number' => 0,
                'shuffles_used' => 0,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Another request (app + reminder at the same moment) created today's pick first: use that one.
            return DailyPick::where('user_id', $user->id)->where('date', $date->toDateString())->firstOrFail();
        }
    }

    private function settings(Kitchen $kitchen): array
    {
        return [
            'repeat_gap_days' => $kitchen->repeat_gap_days,
            'budget' => $kitchen->budget?->value ?? Budget::Normal->value,
            'family_size' => $kitchen->family_size,
            'meals' => $kitchen->meals ?: Kitchen::DEFAULT_MEALS,
        ];
    }

    /**
     * @param  Collection<int, array>  $candidates
     * @return Collection<int, array> up to 3 candidates, best first, each with a "reason"
     */
    private function choose(Collection $candidates, array $settings, ?string $condition, string $seed, array $exclude): Collection
    {
        $perDay = (int) config('handi.picks.per_day', 3);
        $gap = (int) ($settings['repeat_gap_days'] ?? 5);
        $budget = $settings['budget'] ?? 'normal';
        $meals = $settings['meals'] ?? Kitchen::DEFAULT_MEALS;

        $mealOk = fn (array $c) => $c['usual_meal'] === 'both'
            ? (in_array('lunch', $meals, true) || in_array('dinner', $meals, true))
            : in_array($c['usual_meal'], $meals, true);

        $pool = $candidates->filter($mealOk);
        if ($pool->count() < $perDay) {
            $pool = $candidates;
        }

        $scored = $pool->map(function (array $c) use ($condition, $budget, $settings, $seed) {
            $fits = in_array('any', $c['weather'], true) || ($condition && in_array($condition, $c['weather'], true));
            $score = ($fits ? 3 : 0)
                + ($c['days_since'] === null ? 2 : min(2, $c['days_since'] / 7))
                + match (true) {
                    $budget === 'low' && $c['cost'] === 'high' => -4,
                    $budget === 'low' && $c['cost'] === 'medium' => -0.5,
                    $budget === 'normal' && $c['cost'] === 'high' => -1,
                    default => 0,
                }
                + (($settings['family_size'] ?? 4) >= 7 && $c['effort'] === 'heavy' ? -0.5 : 0)
                + (crc32($seed.':'.$c['id']) % 1000) / 1000 * 2.5;

            return $c + ['score' => $score, 'fits' => $fits];
        })->sortByDesc('score')->values();

        $notRecent = fn (array $c) => $c['days_since'] === null || $c['days_since'] >= $gap;
        $notExcluded = fn (array $c) => ! in_array($c['id'], $exclude, true);

        // Strictest first; relax step by step.
        $passes = [
            [[$notRecent, $notExcluded], true],
            [[$notRecent], true],
            [[$notRecent], false],
            [[$notExcluded], true],
            [[], true],
            [[], false],
        ];

        $chosen = collect();

        foreach ($passes as [$rules, $distinctCategory]) {
            foreach ($scored as $c) {
                if ($chosen->count() >= $perDay) {
                    break 2;
                }
                if ($chosen->contains('id', $c['id'])) {
                    continue;
                }
                if ($distinctCategory && $chosen->contains('category', $c['category'])) {
                    continue;
                }
                foreach ($rules as $rule) {
                    if (! $rule($c)) {
                        continue 2;
                    }
                }
                $chosen->push($c);
            }
        }

        return $chosen->map(fn (array $c) => $c + [
            'reason' => $c['fits'] && $condition ? 'Fits '.$condition.' weather' : ($c['days_since'] === null ? 'New for you' : 'Not cooked in '.$c['days_since'].' days'),
        ])->values();
    }
}
