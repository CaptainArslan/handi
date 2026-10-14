<?php

namespace App\Filament\Support;

use App\Support\Media;

class ArtKeys
{
    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(Media::ART_KEYS)->mapWithKeys(fn (string $k) => [$k => ucfirst($k)])->all();
    }
}
