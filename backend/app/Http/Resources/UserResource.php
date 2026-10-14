<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** The signed-in user ("/me"). @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $kitchen = $this->kitchenOrDefault()->loadMissing('city.province');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->firstName(),
            'initial' => Str::upper(Str::substr($this->name, 0, 1)),
            'email' => $this->email,
            'masked_email' => $this->maskedEmail(),
            'timezone' => $this->timezone,
            'password_changed_at' => $this->password_changed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'kitchen' => new KitchenResource($kitchen),
            'counts' => [
                'dishes' => $this->kitchenDishes()->whereNull('removed_at')->count(),
                'custom_dishes' => $this->kitchenDishes()->whereNull('removed_at')->where('is_custom', true)->count(),
                'unread_notifications' => $this->appNotifications()->whereNull('read_at')->count(),
            ],
        ];
    }
}
