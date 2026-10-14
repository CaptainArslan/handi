<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationType;
use App\Enums\PhotoStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\KitchenDishResource;
use App\Models\KitchenDish;
use App\Models\User;
use App\Services\KitchenService;
use App\Services\NotificationService;
use App\Services\PickService;
use App\Support\Activity;
use App\Support\Rules;
use App\Support\UserClock;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "My dishes": the dishes in the user's kitchen, library and custom.
 */
#[Group('My dishes', weight: 6)]
class DishController extends Controller
{
    public function __construct(private readonly PickService $picks) {}

    /**
     * My dishes
     *
     * Filter by `q` (name) and `category` key; `custom=1` for custom only.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:40'],
            'custom' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->prime($user);
        $lastCooked = $this->picks->lastCooked($user);

        $all = KitchenDish::withDetails()->where('user_id', $user->id)->inKitchen()->latest('id')->get();
        $q = mb_strtolower(trim($data['q'] ?? ''));

        $dishes = $all->filter(fn (KitchenDish $d) => ($q === '' || str_contains(mb_strtolower($d->effectiveName()), $q))
            && (empty($data['category']) || $data['category'] === 'all' || $d->effectiveCategory()?->key === $data['category'])
            && (! $request->boolean('custom') || $d->is_custom))
            ->sortBy(fn (KitchenDish $d) => mb_strtolower($d->effectiveName()))
            ->values();

        return response()->json([
            'data' => $dishes->map(fn (KitchenDish $d) => $this->present($request, $d, $lastCooked))->values(),
            'meta' => ['total' => $all->count(), 'custom_count' => $all->where('is_custom', true)->count(), 'matches' => $dishes->count()],
        ]);
    }

    /**
     * Add library dishes
     */
    public function store(Request $request, KitchenService $kitchens): JsonResponse
    {
        $data = $request->validate([
            'library_dish_ids' => ['required', 'array', 'min:1'],
            'library_dish_ids.*' => ['integer', 'exists:dishes,id'],
        ]);

        $added = $kitchens->addLibraryDishes($request->user(), $data['library_dish_ids']);
        Activity::log($request->user(), 'dish.added', "Added {$added} library dish(es)");

        return response()->json(['data' => ['added' => $added]], 201);
    }

    /**
     * Dish detail
     *
     * Includes library defaults, overrides, last cooked and when it can be suggested again.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $dish = $this->find($request, $id);
        $this->prime($request->user());

        return response()->json(['data' => $this->present($request, $dish, $this->picks->lastCooked($request->user()), true)]);
    }

    /**
     * Change weather, effort or cost
     *
     * For library dishes this stores your own tags; "Reset to library tags" undoes it.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $dish = $this->find($request, $id);

        $data = $request->validate([
            ...Rules::weather(required: false),
            'effort' => ['sometimes', 'in:quick,medium,heavy'],
            'cost' => ['sometimes', 'in:low,medium,high'],
        ], Rules::weatherMessages(message: 'Pick at least one weather, or “Any”.'));

        $dish->update($data);
        Activity::log($request->user(), 'dish.updated', 'Changed tags of '.$dish->effectiveName(), $dish, $data);

        return $this->show($request, $id);
    }

    /**
     * Reset to library tags
     */
    public function resetOverrides(Request $request, int $id): JsonResponse
    {
        $dish = $this->find($request, $id);
        abort_if($dish->is_custom, 422, 'Custom dishes have no library tags.');

        $dish->update(['weather' => null, 'effort' => null, 'cost' => null]);
        Activity::log($request->user(), 'dish.reset', 'Reset tags of '.$dish->effectiveName(), $dish);

        return $this->show($request, $id);
    }

    /**
     * Add or change photo
     *
     * Multipart field `photo`, image up to 10 MB. Adds a "Photo added" notification.
     */
    public function photo(Request $request, int $id, NotificationService $notifications): JsonResponse
    {
        $dish = $this->find($request, $id);

        $request->validate([
            'photo' => ['required', 'image', 'max:'.config('handi.photo_max_kb', 10240)],
        ], ['photo.max' => 'Photos can be up to 10 MB.', 'photo.uploaded' => 'That photo is too large to upload. Try a smaller one.', 'photo.image' => 'Choose a photo (JPG, PNG or WebP).']);

        // Media library: replaces any earlier photo and makes the thumb/card sizes.
        $dish->attachPhoto($request->file('photo'));

        // A photo stored before the media library is no longer needed.
        if ($dish->photo_path) {
            Storage::disk(config('handi.media_disk'))->delete($dish->photo_path);
        }
        $dish->update(['photo_path' => null, 'photo_status' => PhotoStatus::Ready]);

        $notifications->notify($request->user(), NotificationType::PhotoReady, 'Photo added',
            'Your photo of '.$dish->effectiveName().' is ready and now shows in suggestions.',
            'View dish', '/dishes/'.$dish->id, $dish->imageKey());
        Activity::log($request->user(), 'dish.photo_added', 'Added a photo of '.$dish->effectiveName(), $dish);

        return $this->show($request, $id);
    }

    /**
     * Remove from my dishes
     *
     * It won't be suggested anymore; past history stays. Undo with `POST /me/dishes/{id}/restore`.
     */
    public function destroy(Request $request, int $id): Response
    {
        $dish = $this->find($request, $id);
        $dish->update(['removed_at' => now()]);
        Activity::log($request->user(), 'dish.removed', 'Removed '.$dish->effectiveName(), $dish);

        return response()->noContent();
    }

    /**
     * Undo remove
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $dish = $this->find($request, $id, includeRemoved: true);
        $dish->update(['removed_at' => null]);
        Activity::log($request->user(), 'dish.restored', 'Restored '.$dish->effectiveName(), $dish);

        return $this->show($request, $id);
    }

    private function find(Request $request, int $id, bool $includeRemoved = false): KitchenDish
    {
        return KitchenDish::withDetails()
            ->where('user_id', $request->user()->id)
            ->when(! $includeRemoved, fn ($q) => $q->inKitchen())
            ->findOrFail($id);
    }

    private function prime(User $user): void
    {
        KitchenDishResource::$today = UserClock::today($user);
        KitchenDishResource::$repeatGap = $user->kitchenOrDefault()->repeat_gap_days;
    }

    private function present(Request $request, KitchenDish $dish, array $lastCooked, bool $detail = false): array
    {
        $dish->setAttribute('last_cooked_date', $lastCooked[$dish->id] ?? null);
        $resource = new KitchenDishResource($dish);

        return ($detail ? $resource->detailed() : $resource)->resolve($request);
    }
}
