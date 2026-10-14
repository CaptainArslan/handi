<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Weather: string implements HasColor, HasLabel
{
    case Hot = 'hot';
    case Mild = 'mild';
    case Cold = 'cold';
    case Rainy = 'rainy';
    case Any = 'any';

    public function getColor(): string
    {
        return match ($this) {
            self::Hot => 'warning',
            self::Mild => 'success',
            self::Cold, self::Rainy => 'info',
            self::Any => 'gray',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Hot => 'Hot',
            self::Mild => 'Mild',
            self::Cold => 'Cold',
            self::Rainy => 'Rainy',
            self::Any => 'Any',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
