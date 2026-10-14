<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MealLog */
class MealLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dish = $this->relationLoaded('kitchenDish') ? $this->kitchenDish : null;
        $imageKey = $dish?->imageKey();

        return [
            'id' => $this->id,
            'kitchen_dish_id' => $this->kitchen_dish_id,
            'dish_name' => $this->dish_name,
            'category_key' => $this->category_key,
            'image_url' => $dish?->imageUrl(),
            'image_key' => $imageKey,
            'image_bg' => Media::artBg($imageKey),
            'date' => $this->date?->toDateString(),
            'meal' => $this->meal?->value,
            'source' => $this->source?->value,
            'logged_at' => $this->logged_at?->toIso8601String(),
        ];
    }
}
