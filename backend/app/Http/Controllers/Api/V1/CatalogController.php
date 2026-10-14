<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CityResource;
use App\Http\Resources\LibraryDishResource;
use App\Models\Category;
use App\Models\City;
use App\Models\Dish;
use App\Models\Province;
use App\Services\WeatherService;
use App\Support\CatalogCache;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 */
#[Group('Public catalog', weight: 0)]
class CatalogController extends Controller
{
    /**
     * Dish library
     *
     * The global library users pick their kitchen from during onboarding. Cached; admin edits apply on the next request.
     *
     * @unauthenticated
     */
    public function dishes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:40'],
        ]);

        $all = CatalogCache::remember('library', fn () => LibraryDishResource::collection(
            Dish::active()->with('category')->orderBy('name')->get()
        )->resolve($request));

        $q = mb_strtolower(trim($data['q'] ?? ''));
        $category = $data['category'] ?? null;

        $dishes = array_values(array_filter($all, fn (array $d) => ($q === '' || str_contains(mb_strtolower($d['name']), $q))
            && (! $category || $category === 'all' || ($d['category']['key'] ?? null) === $category)));

        return response()->json(['data' => $dishes, 'meta' => ['version' => CatalogCache::version(), 'total' => count($dishes)]]);
    }

    /**
     * Categories
     *
     * @unauthenticated
     */
    public function categories(Request $request): JsonResponse
    {
        $data = CatalogCache::remember('categories', fn () => CategoryResource::collection(
            Category::active()->orderBy('sort_order')->get()
        )->resolve($request));

        return response()->json(['data' => $data]);
    }

    /**
     * Cities
     *
     * Popular cities plus every city grouped by province.
     *
     * @unauthenticated
     */
    public function cities(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:30']]);
        $q = mb_strtolower(trim($data['q'] ?? ''));

        $provinces = CatalogCache::remember('cities', fn () => Province::with('cities.province')->orderBy('sort_order')->get()
            ->map(fn (Province $p) => [
                'name' => $p->name,
                'short_name' => $p->short_name,
                'cities' => CityResource::collection($p->cities)->resolve($request),
            ])->all());

        $popular = collect($provinces)->flatMap(fn ($p) => $p['cities'])->where('is_popular', true)->values();

        if ($q !== '') {
            $provinces = collect($provinces)->map(fn ($p) => [...$p, 'cities' => array_values(array_filter($p['cities'], fn ($c) => str_contains(mb_strtolower($c['name']), $q)))])
                ->filter(fn ($p) => count($p['cities']) > 0)->values()->all();
        }

        return response()->json(['data' => ['popular' => $popular, 'provinces' => array_values($provinces)]]);
    }

    /**
     * Weather
     *
     * Today's weather for a city or coordinates, bucketed into hot / mild / cold / rainy.
     *
     * @unauthenticated
     */
    public function weather(Request $request, WeatherService $weather): JsonResponse
    {
        $data = $request->validate([
            'city_id' => ['required_without:lat', 'nullable', 'integer', 'exists:cities,id'],
            'lat' => ['required_without:city_id', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['required_with:lat', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        if (! empty($data['city_id'])) {
            $city = City::with('province')->findOrFail($data['city_id']);

            return response()->json(['data' => $weather->forCity($city) + ['province' => $city->province?->name]]);
        }

        return response()->json(['data' => $weather->forCoordinates((float) $data['lat'], (float) $data['lng'])]);
    }

    /**
     * Terms or privacy policy
     *
     * @unauthenticated
     */
    public function legal(string $document): JsonResponse
    {
        abort_unless(in_array($document, ['terms', 'privacy'], true), 404, 'Not found.');

        return response()->json(['data' => config('legal.'.$document) + ['updated_at' => config('legal.updated_at'), 'support_email' => config('handi.support_email')]]);
    }
}
