<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LocationMode: string implements HasLabel
{
    case Gps = 'gps';
    case City = 'city';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gps => 'Current location',
            self::City => 'Chosen city',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
