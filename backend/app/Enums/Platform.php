<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Platform: string implements HasLabel
{
    case Android = 'android';
    case Ios = 'ios';

    public function getLabel(): string
    {
        return match ($this) {
            self::Android => 'Android',
            self::Ios => 'iOS',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
