<?php

namespace App\Http\Resources;

use App\Services\PickService;
use App\Support\Media;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A dish in the user's kitchen. Set the model attribute `last_cooked_date` (Y-m-d|null)
 * before passing it in; call ->detailed() for the dish-detail shape.
 *
 * @mixin \App\Models\KitchenDish
 */
class KitchenDishResource extends JsonResource
{
    public bool $detail = false;

    public static ?CarbonImmutable $today = null;

    public static ?int $repeatGap = null;

    public function detailed(): static
    {
        $this->detail = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $today = self::$today ?? CarbonImmutable::today(config('handi.timezone'));
        $last = $this->getAttribute('last_cooked_date');
        $category = $this->effectiveCategory();
        $imageKey = $this->imageKey();

        $data = [
            'id' => $this->id,
            'name' => $this->effectiveName(),
            'category' => $category ? ['key' => $category->key, 'name' => $category->name] : null,
            'is_custom' => $this->is_custom,
            'image_url' => $this->imageUrl('card'),
            'thumb_url' => $this->imageUrl('thumb'),
            'image_key' => $imageKey,
            'image_bg' => Media::artBg($imageKey),
            'weather' => $this->effectiveWeather(),
            'effort' => $this->effectiveEffort()->value,
            'cost' => $this->effectiveCost()->value,
            'usual_meal' => $this->effectiveUsualMeal()->value,
            'last_cooked_at' => $last,
            'days_since_cooked' => PickService::daysSince($last, $today),
            'removed' => $this->removed_at !== null,
        ];

        if ($this->detail) {
            $gap = self::$repeatGap ?? 5;
            $next = $last ? CarbonImmutable::parse($last, $today->timezone)->addDays($gap) : null;

            $data += [
                'library_dish_id' => $this->dish_id,
                'note' => $this->is_custom ? null : $this->dish?->note,
                'has_overrides' => $this->hasOverrides(),
                'library_defaults' => $this->is_custom || ! $this->dish ? null : [
                    'weather' => $this->dish->default_weather,
                    'effort' => $this->dish->default_effort?->value,
                    'cost' => $this->dish->default_cost?->value,
                ],
                'next_eligible_at' => $next && $next->greaterThan($today) ? $next->toDateString() : null,
                'photo_status' => $this->photo_status?->value,
                'has_photo' => $this->hasOwnPhoto(),
            ];
        }

        return $data;
    }
}
