<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NotificationType: string implements HasLabel
{
    case DailyPicks = 'daily_picks';
    case Sync = 'sync';
    case PhotoReady = 'photo_ready';
    case KitchenReady = 'kitchen_ready';
    case Account = 'account';

    public function getLabel(): string
    {
        return match ($this) {
            self::DailyPicks => 'Daily picks',
            self::Sync => 'Sync',
            self::PhotoReady => 'Photo ready',
            self::KitchenReady => 'Kitchen ready',
            self::Account => 'Account',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
