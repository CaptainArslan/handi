<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Effort: string implements HasColor, HasLabel
{
    case Quick = 'quick';
    case Medium = 'medium';
    case Heavy = 'heavy';

    public function getColor(): string
    {
        return match ($this) {
            self::Quick => 'success',
            self::Medium => 'warning',
            self::Heavy => 'danger',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Quick => 'Quick',
            self::Medium => 'Medium',
            self::Heavy => 'Heavy',
        };
    }

    /** Longer label used in the app (e.g. budget subtitle, effort badge). */
    public function description(): string
    {
        return match ($this) {
            self::Quick => 'Quick to make',
            self::Medium => 'Medium effort',
            self::Heavy => 'Heavy effort',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
