<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\MealLog;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Activity;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 */
#[Group('History', weight: 8)]
class SyncController extends Controller
{
    /**
     * Sync offline changes
     *
     * Replays meals logged or deleted while offline. Each operation:
     * `{op: "meal.create", payload: {id, kitchen_dish_id, date, meal, source}}` or `{op: "meal.delete", payload: {id}}`.
     * Results come back in the same order.
     */
    public function __invoke(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'operations' => ['required', 'array', 'max:200'],
            'operations.*.op' => ['required', 'in:meal.create,meal.delete'],
            'operations.*.payload' => ['required', 'array'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $results = [];
        $created = 0;

        foreach ($data['operations'] as $operation) {
            try {
                if ($operation['op'] === 'meal.create') {
                    $sub = Request::create('/', 'POST', $operation['payload']);
                    $sub->setUserResolver(fn () => $user);
                    $response = app(MealController::class)->store($sub);
                    $body = $response->getData(true);
                    $results[] = ['ok' => true, 'id' => $body['data']['id'], 'duplicate' => $body['meta']['duplicate'] ?? false];
                    $created += $response->getStatusCode() === 201 ? 1 : 0;
                } else {
                    MealLog::where('user_id', $user->id)->whereKey($operation['payload']['id'] ?? '')->delete();
                    $results[] = ['ok' => true, 'id' => $operation['payload']['id'] ?? null];
                }
            } catch (ValidationException $e) {
                $results[] = ['ok' => false, 'error' => collect($e->errors())->flatten()->first()];
            } catch (Throwable $e) {
                report($e);
                $results[] = ['ok' => false, 'error' => 'Could not sync this change.'];
            }
        }

        $user->kitchenOrDefault()->update(['last_synced_at' => now()]);

        if ($created > 0) {
            $notifications->notify($user, NotificationType::Sync, $created.' meal'.($created === 1 ? '' : 's').' synced',
                'The meals you logged offline are now saved to your account.', 'See history', '/history');
            Activity::log($user, 'sync.completed', "Synced {$created} offline meal(s)");
        }

        return response()->json(['data' => ['results' => $results, 'synced_at' => now()->toIso8601String()]]);
    }
}
