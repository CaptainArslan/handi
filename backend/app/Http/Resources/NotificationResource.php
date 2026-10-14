<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AppNotification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'title' => $this->title,
            'body' => $this->body,
            'cta_label' => $this->cta_label,
            'deep_link' => $this->deep_link,
            'image_url' => Media::artUrl($this->image_key),
            'image_key' => $this->image_key,
            'image_bg' => $this->image_key ? Media::artBg($this->image_key) : null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
