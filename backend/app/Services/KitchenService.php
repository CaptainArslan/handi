<?php

namespace App\Services;

use App\Enums\LocationMode;
use App\Models\Category;
use App\Models\City;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\KitchenDish;
use App\Models\User;
use Illuminate\Support\Str;

/** Kitchen setup shared by sign-up and the settings screens. */
class KitchenService
{
    /**
     * Apply a location: {mode:"city", city_id} or {mode:"gps", lat, lng}.
     * GPS is rounded to ~1 km (2 decimals) and labelled with the nearest city.
     */
    public function applyLocation(Kitchen $kitchen, array $location): Kitchen
    {
        if (($location['mode'] ?? null) === LocationMode::Gps->value) {
            $lat = round((float) $location['lat'], 2);
            $lng = round((float) $location['lng'], 2);
            $city = $this->nearestCity($lat, $lng);

            $kitchen->fill([
                'location_mode' => LocationMode::Gps,
                'lat' => $lat,
                'lng' => $lng,
                'city_id' => $city?->id,
                'location_label' => $city ? $city->name.' · near you' : 'Your area',
            ]);
        } else {
            $city = City::with('province')->findOrFail($location['city_id']);

            $kitchen->fill([
                'location_mode' => LocationMode::City,
                'lat' => null,
                'lng' => null,
                'city_id' => $city->id,
                'location_label' => $city->label(),
            ]);
        }

        $kitchen->save();

        return $kitchen->load('city.province');
    }

    public function nearestCity(float $lat, float $lng): ?City
    {
        return City::with('province')->get()
            ->sortBy(fn (City $c) => ($c->lat - $lat) ** 2 + (($c->lng - $lng) * cos(deg2rad($lat))) ** 2)
            ->first();
    }

    /** Add library dishes to the kitchen (restoring removed ones). Returns how many were added. */
    public function addLibraryDishes(User $user, array $dishIds): int
    {
        $added = 0;

        foreach (Dish::active()->whereIn('id', $dishIds)->pluck('id') as $dishId) {
            $item = KitchenDish::firstOrNew(['user_id' => $user->id, 'dish_id' => $dishId]);

            if (! $item->exists || $item->removed_at) {
                $item->fill(['is_custom' => false, 'removed_at' => null])->save();
                $added++;
            }
        }

        return $added;
    }

    /** Is this name already used by a dish in the user's kitchen? (case-insensitive) */
    public function nameTaken(User $user, string $name, ?int $ignoreId = null): bool
    {
        $needle = Str::lower(self::cleanName($name));

        return KitchenDish::where('user_id', $user->id)
            ->inKitchen()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->withDetails()
            ->get()
            ->contains(fn (KitchenDish $d) => Str::lower($d->effectiveName()) === $needle);
    }

    public function createCustomDish(User $user, array $data): KitchenDish
    {
        $category = isset($data['category']) ? Category::where('key', $data['category'])->first() : null;

        return KitchenDish::create([
            'user_id' => $user->id,
            'is_custom' => true,
            'name' => self::cleanName($data['name']),
            'category_id' => $category?->id,
            'usual_meal' => $data['usual_meal'] ?? 'both',
            'weather' => $data['weather'] ?? ['any'],
            'effort' => $data['effort'] ?? 'medium',
            'cost' => $data['cost'] ?? 'low',
        ]);
    }

    /** Trim and collapse whitespace: "  Mom’s   karhi " → "Mom’s karhi". */
    public static function cleanName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }
}
