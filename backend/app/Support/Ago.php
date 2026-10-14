<?php

namespace App\Support;

class Ago
{
    /** "Today", "Yesterday", "3 days ago" — the copy the app uses. */
    public static function days(?int $days): string
    {
        return match (true) {
            $days === null => 'Never',
            $days <= 0 => 'Today',
            $days === 1 => 'Yesterday',
            default => "{$days} days ago",
        };
    }
}
