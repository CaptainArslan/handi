<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Fills `slug` from `name` on create when it is empty, adding -2, -3… if taken.
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (filled($model->slug)) {
                return;
            }

            $base = Str::slug((string) $model->name) ?: Str::lower(Str::random(8));
            $slug = $base;
            $i = 2;

            while (static::query()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$i++;
            }

            $model->slug = $slug;
        });
    }
}
