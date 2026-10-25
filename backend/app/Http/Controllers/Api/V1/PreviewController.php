<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\PreviewPicksRequest;
use App\Http\Resources\LibraryDishResource;
use App\Models\City;
use App\Models\Dish;
use App\Services\KitchenService;
use App\Services\PickService;
use App\Services\WeatherService;
use App\Support\UserClock;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Public catalog', weight: 0)]
class PreviewController extends Controller
{
    /**
     * Preview today’s picks
     *
     * Runs the real picking rules on a guest's choices before they sign up. Nothing is saved.
     *
     * @unauthenticated
     */
    public function __invoke(PreviewPicksRequest $request, PickService $picks, WeatherService $weather, KitchenService $kitchens): JsonResponse
    {
        $data = $request->validated();

        $forecast = null;
        if (($data['location']['mode'] ?? null) === 'city') {
            $forecast = $weather->forCity(City::with('province')->find($data['location']['city_id']));
        } elseif (($data['location']['mode'] ?? null) === 'gps') {
            $city = $kitchens->nearestCity((float) $data['location']['lat'], (float) $data['location']['lng']);
            $forecast = $weather->forCoordinates((float) $data['location']['lat'], (float) $data['location']['lng'], $city?->name);
        }

        $dishes = Dish::active()->with('category')->whereIn('id', $data['dish_ids'])->get()->keyBy('id');

        $chosen = $picks->preview(
            $dishes->map(fn (Dish $d) => [
                'id' => $d->id,
                'category' => $d->category?->key ?? 'other',
                'weather' => $d->default_weather ?? ['any'],
                'effort' => $d->default_effort?->value ?? 'medium',
                'cost' => $d->default_cost?->value ?? 'low',
                'usual_meal' => $d->usual_meal?->value ?? 'both',
            ])->values(),
            [
                'repeat_gap_days' => $data['repeat_gap_days'] ?? 5,
                'budget' => $data['budget'] ?? 'normal',
                'family_size' => $data['family_size'] ?? 4,
                'meals' => $data['meals'] ?? ['lunch', 'dinner'],
            ],
            $forecast['condition'] ?? null,
        );

        return response()->json(['data' => [
            'date' => UserClock::today()->toDateString(),
            'weather' => $forecast,
            'picks' => $chosen->map(fn (array $c) => [
                ...(new LibraryDishResource($dishes[$c['id']]))->resolve($request),
                'reason' => $c['reason'],
            ])->values(),
        ]]);
    }
}
