<?php

use Illuminate\Support\Facades\Schedule;

// Each user's morning reminder at their chosen time (checked every minute, in their timezone).
Schedule::command('reminders:dispatch')
    ->everyMinute()
    ->withoutOverlapping();

// Telescope keeps 48 hours of entries (only when it's enabled).
if (config('telescope.enabled') && class_exists(\Laravel\Telescope\Telescope::class)) {
    Schedule::command('telescope:prune --hours=48')->daily();
}
