<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A dish from the global library (onboarding "What does your family eat?"). @mixin \App\Models\Dish */
class LibraryDishResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageKey = $this->image_key ?? $this->category?->image_key;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category ? ['key' => $this->category->key, 'name' => $this->category->name] : null,
            'note' => $this->note,
            'image_url' => $this->image ? Media::url($this->image) : Media::artUrl($imageKey),
            'image_key' => $imageKey,
            'image_bg' => Media::artBg($imageKey),
            'default_weather' => $this->default_weather,
            'default_effort' => $this->default_effort?->value,
            'default_cost' => $this->default_cost?->value,
            'usual_meal' => $this->usual_meal?->value,
        ];
    }
}
