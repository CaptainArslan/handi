<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationType;
use App\Enums\PhotoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dish\StoreCustomDishRequest;
use App\Http\Requests\Dish\UpdateCustomDishRequest;
use App\Http\Resources\KitchenDishResource;
use App\Models\Category;
use App\Models\KitchenDish;
use App\Services\KitchenService;
use App\Services\NotificationService;
use App\Services\PickService;
use App\Support\Activity;
use App\Support\UserClock;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Family recipes that aren't in the library.
 */
#[Group('My dishes · custom', weight: 7)]
class CustomDishController extends Controller
{
    public function __construct(private readonly KitchenService $kitchens, private readonly PickService $picks) {}

    /**
     * My custom dishes
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        KitchenDishResource::$today = UserClock::today($user);
        $lastCooked = $this->picks->lastCooked($user);

        $dishes = KitchenDish::withDetails()->where('user_id', $user->id)->where('is_custom', true)->inKitchen()->latest('id')->get();

        return response()->json(['data' => $dishes->map(function (KitchenDish $d) use ($request, $lastCooked) {
            $d->setAttribute('last_cooked_date', $lastCooked[$d->id] ?? null);

            return (new KitchenDishResource($d))->resolve($request);
        })->values()]);
    }

    /**
     * Add custom dish
     *
     * JSON or multipart (with optional `photo` up to 10 MB). `weather` is an array, e.g. ["rainy","mild"] or ["any"].
     */
    #[BodyParameter('name', '2–40 letters, spaces, apostrophes, ( ) and -', required: true, type: 'string', example: 'Aunt Sara’s Haleem')]
    #[BodyParameter('category', 'Category key (see GET /categories)', required: true, type: 'string', example: 'beef')]
    #[BodyParameter('weather', 'One or more of hot, mild, cold, rainy — or just any. Multipart: weather[]=cold&weather[]=rainy', required: true, type: 'array', example: ['cold', 'rainy'])]
    #[BodyParameter('usual_meal', 'breakfast | lunch | dinner | both (default both)', type: 'string', example: 'both')]
    #[BodyParameter('effort', 'quick | medium | heavy (default medium)', type: 'string', example: 'heavy')]
    #[BodyParameter('cost', 'low | medium | high (default low)', type: 'string', example: 'medium')]
    #[BodyParameter('photo', 'Optional image up to 10 MB (send as multipart/form-data)', type: 'string', format: 'binary')]
    public function store(StoreCustomDishRequest $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validated();

        $dish = $this->kitchens->createCustomDish($request->user(), $data);

        if ($request->hasFile('photo')) {
            $dish->attachPhoto($request->file('photo'));
            $dish->update(['photo_status' => PhotoStatus::Ready]);
            $notifications->notify($request->user(), NotificationType::PhotoReady, 'Photo added',
                'Your photo of '.$dish->effectiveName().' is ready and now shows in suggestions.', 'View dish', '/dishes/'.$dish->id, $dish->imageKey());
        }

        Activity::log($request->user(), 'dish.custom_created', 'Added custom dish '.$dish->name, $dish);

        return $this->respond($request, $dish->fresh(), 201);
    }

    /**
     * Edit custom dish
     */
    #[BodyParameter('name', '2–40 letters, spaces, apostrophes, ( ) and -', required: false, type: 'string', example: 'Aunt Sara’s Haleem')]
    #[BodyParameter('category', 'Category key (see GET /categories)', required: false, type: 'string', example: 'beef')]
    #[BodyParameter('weather', 'One or more of hot, mild, cold, rainy — or just any. Multipart: weather[]=cold&weather[]=rainy', required: false, type: 'array', example: ['cold', 'rainy'])]
    #[BodyParameter('usual_meal', 'breakfast | lunch | dinner | both (default both)', type: 'string', example: 'both')]
    #[BodyParameter('effort', 'quick | medium | heavy (default medium)', type: 'string', example: 'heavy')]
    #[BodyParameter('cost', 'low | medium | high (default low)', type: 'string', example: 'medium')]
    public function update(UpdateCustomDishRequest $request, int $id): JsonResponse
    {
        $dish = $this->find($request, $id);
        $data = $request->validated();

        $category = isset($data['category']) ? Category::where('key', $data['category'])->first() : null;

        $dish->update([
            'name' => isset($data['name']) ? KitchenService::cleanName($data['name']) : $dish->name,
            'category_id' => $category?->id ?? $dish->category_id,
            'usual_meal' => $data['usual_meal'] ?? $dish->usual_meal,
            'weather' => $data['weather'] ?? $dish->weather,
            'effort' => $data['effort'] ?? $dish->effort,
            'cost' => $data['cost'] ?? $dish->cost,
        ]);

        Activity::log($request->user(), 'dish.custom_updated', 'Edited custom dish '.$dish->name, $dish);

        return $this->respond($request, $dish->fresh());
    }

    /**
     * Remove custom dish
     *
     * Past meals with this dish stay in history. Undo with restore.
     */
    public function destroy(Request $request, int $id): Response
    {
        $dish = $this->find($request, $id);
        $dish->update(['removed_at' => now()]);
        Activity::log($request->user(), 'dish.removed', 'Removed custom dish '.$dish->name, $dish);

        return response()->noContent();
    }

    /**
     * Undo remove
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $dish = $this->find($request, $id, true);
        $dish->update(['removed_at' => null]);

        return $this->respond($request, $dish->fresh());
    }

    private function find(Request $request, int $id, bool $includeRemoved = false): KitchenDish
    {
        return KitchenDish::withDetails()->where('user_id', $request->user()->id)->where('is_custom', true)
            ->when(! $includeRemoved, fn ($q) => $q->inKitchen())->findOrFail($id);
    }

    private function respond(Request $request, KitchenDish $dish, int $status = 200): JsonResponse
    {
        KitchenDishResource::$today = UserClock::today($request->user());
        KitchenDishResource::$repeatGap = $request->user()->kitchenOrDefault()->repeat_gap_days;
        $dish->load(['dish.category', 'ownCategory']);
        $dish->setAttribute('last_cooked_date', $this->picks->lastCooked($request->user())[$dish->id] ?? null);

        return response()->json(['data' => (new KitchenDishResource($dish))->detailed()->resolve($request)], $status);
    }
}
