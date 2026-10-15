<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MealLogResource;
use App\Models\KitchenDish;
use App\Models\MealLog;
use App\Models\User;
use App\Services\StatsService;
use App\Support\Activity;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * History and meal logging.
 */
#[Group('History', weight: 8)]
class MealController extends Controller
{
    /**
     * History for a month
     *
     * `month` = YYYY-MM (default: this month). Meals grouped by day, plus "{n} of {days} days logged".
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $today = UserClock::today($user);

        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = isset($data['month']) ? CarbonImmutable::createFromFormat('!Y-m', $data['month'], $today->timezone) : $today->startOfMonth();
        $start = $month->startOfMonth();
        $end = $month->endOfMonth()->startOfDay();

        $logs = MealLog::with('kitchenDish.dish.category', 'kitchenDish.ownCategory')
            ->where('user_id', $user->id)
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<=', $end->toDateString())
            ->orderByDesc('date')
            ->orderBy('logged_at')
            ->get();

        $days = $logs->groupBy(fn (MealLog $l) => $l->date->toDateString())
            ->map(fn ($group) => MealLogResource::collection($group->sortBy(fn ($l) => ['breakfast' => 0, 'lunch' => 1, 'dinner' => 2][$l->meal->value])->values())->resolve($request));

        $daysElapsed = $start->greaterThan($today) ? 0 : ($end->lessThan($today) ? $end->day : $today->day);

        return response()->json(['data' => [
            'month' => $start->format('Y-m'),
            'logged_days' => $days->count(),
            'days_elapsed' => $daysElapsed,
            'min_date' => $today->subDays((int) config('handi.meals.log_days_back', 60))->toDateString(),
            'max_date' => $today->toDateString(),
            'days' => $days,
        ]]);
    }

    /**
     * Log a meal / Mark as cooked
     *
     * `id` may be a client UUID (offline queue) — sending the same id twice is safe.
     * If the same dish is already logged for that date and meal, the existing entry is kept:
     * 200 with `meta.duplicate = true`.
     */
    #[BodyParameter('id', 'Optional client UUID (offline queue) — resending the same id is safe', type: 'string', format: 'uuid', example: '9f1c2a7e-3b4d-4c5e-8f60-1a2b3c4d5e6f')]
    #[BodyParameter('kitchen_dish_id', 'One of your dishes (GET /me/dishes)', required: true, type: 'integer', example: 12)]
    #[BodyParameter('date', 'Y-m-d, today or up to 60 days back', required: true, type: 'string', format: 'date', example: '2026-10-13')]
    #[BodyParameter('meal', 'breakfast | lunch | dinner', required: true, type: 'string', example: 'dinner')]
    #[BodyParameter('source', 'suggestion (from Today) or manual (default)', type: 'string', example: 'manual')]
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validated($request, $user);

        if (! empty($data['id']) && ($existing = MealLog::withTrashed()->where('user_id', $user->id)->find($data['id']))) {
            return $this->respond($request, $existing->trashed() ? tap($existing)->restore() : $existing, 200, false);
        }

        $dish = KitchenDish::withDetails()->where('user_id', $user->id)->findOrFail($data['kitchen_dish_id']);

        // Lock per user so a double tap or an offline sync racing an online log can't create two rows.
        $lock = \Illuminate\Support\Facades\Cache::lock('meal-log:'.$user->id, 10);
        $lock->block(5);

        try {
            $duplicate = MealLog::where('user_id', $user->id)
                ->where('kitchen_dish_id', $dish->id)
                ->where('date', $data['date'])
                ->where('meal', $data['meal'])
                ->first();

            if ($duplicate) {
                return $this->respond($request, $duplicate, 200, true);
            }

            $log = $this->createLog($user, $dish, $data);
        } finally {
            $lock->release();
        }

        Activity::log($user, 'meal.logged', "Logged {$log->dish_name} for {$log->meal->value} on {$data['date']}", $log, ['source' => $log->source->value]);

        return $this->respond($request, $log, 201, false);
    }

    private function createLog(\App\Models\User $user, KitchenDish $dish, array $data): MealLog
    {
        return MealLog::create([
            'id' => $data['id'] ?? null,
            'user_id' => $user->id,
            'kitchen_dish_id' => $dish->id,
            'dish_name' => $dish->effectiveName(),
            'category_key' => $dish->effectiveCategory()?->key,
            'date' => $data['date'],
            'meal' => $data['meal'],
            'source' => $data['source'] ?? 'manual',
            'logged_at' => now(),
        ]);
    }

    /**
     * Edit a logged meal
     */
    #[BodyParameter('kitchen_dish_id', 'One of your dishes (GET /me/dishes)', required: false, type: 'integer', example: 12)]
    #[BodyParameter('date', 'Y-m-d, today or up to 60 days back', required: false, type: 'string', format: 'date', example: '2026-10-13')]
    #[BodyParameter('meal', 'breakfast | lunch | dinner', required: false, type: 'string', example: 'dinner')]
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $log = MealLog::where('user_id', $user->id)->findOrFail($id);
        $data = $this->validated($request, $user, partial: true);

        if (isset($data['kitchen_dish_id'])) {
            $dish = KitchenDish::withDetails()->where('user_id', $user->id)->findOrFail($data['kitchen_dish_id']);
            $data['dish_name'] = $dish->effectiveName();
            $data['category_key'] = $dish->effectiveCategory()?->key;
        }

        $log->update(collect($data)->only(['kitchen_dish_id', 'dish_name', 'category_key', 'date', 'meal'])->all());
        Activity::log($user, 'meal.updated', "Edited a log of {$log->dish_name}", $log);

        return $this->respond($request, $log->fresh(), 200, false);
    }

    /**
     * Delete a logged meal
     *
     * Undo with restore within the snackbar window.
     */
    public function destroy(Request $request, string $id): Response
    {
        $log = MealLog::where('user_id', $request->user()->id)->findOrFail($id);
        $log->delete();
        Activity::log($request->user(), 'meal.deleted', "Deleted a log of {$log->dish_name}", $log);

        return response()->noContent();
    }

    /**
     * Undo delete
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $log = MealLog::onlyTrashed()->where('user_id', $request->user()->id)->findOrFail($id);
        $log->restore();

        return $this->respond($request, $log, 200, false);
    }

    /**
     * Stats
     *
     * Days logged in a row and meals this month.
     */
    public function stats(Request $request, StatsService $stats): JsonResponse
    {
        return response()->json(['data' => $stats->forUser($request->user())]);
    }

    private function validated(Request $request, User $user, bool $partial = false): array
    {
        $today = UserClock::today($user);
        $min = $today->subDays((int) config('handi.meals.log_days_back', 60));
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'id' => ['nullable', 'uuid'],
            'kitchen_dish_id' => [$req, 'integer', Rule::exists('kitchen_dishes', 'id')->where('user_id', $user->id)],
            'date' => [$req, 'date_format:Y-m-d', 'before_or_equal:'.$today->toDateString(), 'after_or_equal:'.$min->toDateString()],
            'meal' => [$req, Rule::in(['breakfast', 'lunch', 'dinner'])],
            'source' => ['nullable', Rule::in(['suggestion', 'manual'])],
        ], [
            'kitchen_dish_id.required' => 'Pick the dish you cooked.',
            'kitchen_dish_id.exists' => 'Pick the dish you cooked.',
            'date.required' => 'Choose a date.',
            'date.date_format' => 'Choose a date.',
            'date.before_or_equal' => 'You can’t log a meal in the future.',
            'date.after_or_equal' => 'You can log up to '.config('handi.meals.log_days_back', 60).' days back (from '.$min->format('j M').').',
        ]);
    }

    private function respond(Request $request, MealLog $log, int $status, bool $duplicate): JsonResponse
    {
        $log->load('kitchenDish.dish.category', 'kitchenDish.ownCategory');

        return response()->json([
            'data' => (new MealLogResource($log))->resolve($request),
            'meta' => ['duplicate' => $duplicate],
        ], $status);
    }
}
