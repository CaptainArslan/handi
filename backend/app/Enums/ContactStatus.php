<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/** Where a "Contact us" message is in the team's inbox. */
enum ContactStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case WaitingOnUser = 'waiting_on_user';
    case Resolved = 'resolved';
    case Spam = 'spam';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In progress',
            self::WaitingOnUser => 'Waiting on user',
            self::Resolved => 'Resolved',
            self::Spam => 'Spam',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Pending => 'New, nobody has looked at it yet',
            self::InProgress => 'Someone is working on it',
            self::WaitingOnUser => 'We replied and are waiting for their answer',
            self::Resolved => 'Answered or fixed, nothing left to do',
            self::Spam => 'Junk, ignore it',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InProgress => 'info',
            self::WaitingOnUser => 'primary',
            self::Resolved => 'success',
            self::Spam => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-m-inbox',
            self::InProgress => 'heroicon-m-wrench-screwdriver',
            self::WaitingOnUser => 'heroicon-m-clock',
            self::Resolved => 'heroicon-m-check-circle',
            self::Spam => 'heroicon-m-no-symbol',
        };
    }

    /** Still needs the team's attention. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::InProgress], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
