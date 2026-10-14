<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PhotoStatus: string implements HasLabel
{
    case None = 'none';
    case Processing = 'processing';
    case Ready = 'ready';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'No photo',
            self::Processing => 'Processing',
            self::Ready => 'Ready',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
