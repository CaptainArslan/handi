<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Kitchen */
class KitchenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'family_size' => $this->family_size,
            'budget' => $this->budget?->value,
            'repeat_gap_days' => $this->repeat_gap_days,
            'meals' => array_values($this->meals ?? []),
            'location' => [
                'mode' => $this->location_mode?->value,
                'city_id' => $this->city_id,
                'city' => $this->cityName(),
                'province' => $this->city?->province?->name,
                'province_short' => $this->city?->province?->short_name,
                'label' => $this->location_label,
            ],
            'notifications' => [
                'enabled' => $this->notifications_enabled,
                'time' => $this->notify_time,
            ],
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
