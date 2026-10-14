<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LogSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\KitchenDishResource;
use App\Http\Resources\MealLogResource;
use App\Models\DailyPick;
use App\Models\KitchenDish;
use App\Models\MealLog;
use App\Models\User;
use App\Services\PickService;
use App\Services\StatsService;
use App\Support\Activity;
use App\Support\UserClock;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The daily loop: Pick → Cook → Log.
 */
#[Group('Today', weight: 5)]
class TodayController extends Controller
{
    public function __construct(private readonly PickService $picks, private readonly StatsService $stats) {}

    /**
     * Today
     *
     * Everything the Today tab needs: weather, the 3 picks (first = top pick), shuffles left,
     * the planned dish, today's log from the picks, stats and when the next picks arrive.
     * `stage` is pick | planned | done.
     */
    public function show(Request $request): JsonResponse
    {
        return $this->respond($request, $this->picks->today($request->user()));
    }

    /**
     * Shuffle all 3
     *
     * Up to 5 times a day. 429 when none are left.
     */
    public function shuffle(Request $request): JsonResponse
    {
        try {
            $pick = $this->picks->shuffle($request->user());
        } catch (RuntimeException) {
            return response()->json(['message' => 'No shuffles left today', 'shuffles_left' => 0], 429);
        }

        Activity::log($request->user(), 'today.shuffled', 'Shuffled today’s picks', $pick, ['shuffles_used' => $pick->shuffles_used]);

        return $this->respond($request, $pick);
    }

    /**
     * Cook this today
     *
     * Plans one of today's picks for lunch or dinner. Nothing is logged yet.
     */
    public function plan(Request $request): JsonResponse
    {
        $pick = $this->picks->today($request->user());

        $data = $request->validate([
            'kitchen_dish_id' => ['required', 'integer', Rule::in($pick->dishIds())],
            'meal' => ['required', Rule::in(['lunch', 'dinner'])],
        ], ['kitchen_dish_id.in' => 'Pick one of today’s dishes.']);

        $pick->update(['plan_kitchen_dish_id' => $data['kitchen_dish_id'], 'plan_meal' => $data['meal'], 'planned_at' => now()]);

        $dish = KitchenDish::withDetails()->find($data['kitchen_dish_id']);
        Activity::log($request->user(), 'today.planned', 'Planned '.$dish?->effectiveName().' for '.$data['meal'], $pick);

        return $this->respond($request, $pick->fresh());
    }

    /**
     * Back to picks
     */
    public function unplan(Request $request): JsonResponse
    {
        $pick = $this->picks->today($request->user());
        $pick->update(['plan_kitchen_dish_id' => null, 'plan_meal' => null, 'planned_at' => null]);

        return $this->respond($request, $pick);
    }

    private function respond(Request $request, DailyPick $pick): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $kitchen = $user->kitchenOrDefault();
        $today = UserClock::today($user);
        $lastCooked = $this->picks->lastCooked($user);

        KitchenDishResource::$today = $today;
        KitchenDishResource::$repeatGap = $kitchen->repeat_gap_days;

        $dishes = KitchenDish::withDetails()->whereIn('id', $pick->dishIds())->get()->keyBy('id');
        $items = collect($pick->items)->filter(fn ($i) => $dishes->has($i['kitchen_dish_id']))->values();

        $present = function (KitchenDish $dish) use ($lastCooked, $request) {
            $dish->setAttribute('last_cooked_date', $lastCooked[$dish->id] ?? null);

            return (new KitchenDishResource($dish))->resolve($request);
        };

        // A suggestion-sourced log today means the loop is done.
        $log = MealLog::with('kitchenDish.dish.category', 'kitchenDish.ownCategory')
            ->where('user_id', $user->id)
            ->where('date', $today->toDateString())
            ->where('source', LogSource::Suggestion)
            ->latest('logged_at')
            ->first();

        $planDish = $pick->plan_kitchen_dish_id ? KitchenDish::withDetails()->find($pick->plan_kitchen_dish_id) : null;
        $stage = $log ? 'done' : ($planDish ? 'planned' : 'pick');

        $nextAt = $today->addDay()->setTimeFromTimeString($kitchen->notify_time);

        return response()->json(['data' => [
            'date' => $today->toDateString(),
            'greeting_name' => $user->firstName(),
            'initial' => mb_strtoupper(mb_substr($user->name, 0, 1)),
            'unread_notifications' => $user->appNotifications()->whereNull('read_at')->count(),
            'weather' => $pick->weather,
            'generated_at' => $pick->generated_at?->toIso8601String(),
            'stage' => $stage,
            'shuffles_left' => $this->picks->shufflesLeft($pick),
            'shuffles_per_day' => (int) config('handi.picks.shuffles_per_day', 5),
            'picks' => $items->map(fn ($i, $index) => [
                ...$present($dishes[$i['kitchen_dish_id']]),
                'reason' => $i['reason'] ?? null,
                'is_top' => $index === 0,
            ])->values(),
            'plan' => $planDish ? [
                'dish' => $present($planDish),
                'meal' => $pick->plan_meal,
                'picked_at' => $pick->planned_at?->toIso8601String(),
            ] : null,
            'log' => $log ? (new MealLogResource($log))->resolve($request) : null,
            'stats' => $this->stats->forUser($user),
            'repeat_gap_days' => $kitchen->repeat_gap_days,
            'reminder' => ['enabled' => $kitchen->notifications_enabled, 'time' => $kitchen->notify_time],
            'next_picks_at' => $nextAt->toIso8601String(),
        ]]);
    }
}
