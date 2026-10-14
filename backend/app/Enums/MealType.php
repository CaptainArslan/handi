<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MealType: string implements HasLabel
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';

    public function getLabel(): string
    {
        return match ($this) {
            self::Breakfast => 'Breakfast',
            self::Lunch => 'Lunch',
            self::Dinner => 'Dinner',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
