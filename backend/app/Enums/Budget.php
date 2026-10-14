<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Budget: string implements HasColor, HasLabel
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'primary',
            self::High => 'warning',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
        };
    }

    /** Longer label used in the app (e.g. budget subtitle, effort badge). */
    public function description(): string
    {
        return match ($this) {
            self::Low => 'Save more',
            self::Normal => 'Everyday',
            self::High => 'Treat often',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
