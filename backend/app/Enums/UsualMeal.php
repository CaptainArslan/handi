<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UsualMeal: string implements HasLabel
{
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Both = 'both';
    case Breakfast = 'breakfast';

    public function getLabel(): string
    {
        return match ($this) {
            self::Lunch => 'Lunch',
            self::Dinner => 'Dinner',
            self::Both => 'Lunch & dinner',
            self::Breakfast => 'Breakfast',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
