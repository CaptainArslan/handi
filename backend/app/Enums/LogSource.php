<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LogSource: string implements HasLabel
{
    case Suggestion = 'suggestion';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Suggestion => 'From today’s picks',
            self::Manual => 'Logged manually',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
