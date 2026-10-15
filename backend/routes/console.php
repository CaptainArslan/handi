<?php

use Illuminate\Support\Facades\Schedule;

// Each user's morning reminder at their chosen time (checked every minute, in their timezone).
// The lock expires after 10 minutes, so a run killed mid-way (deploy, crash) can't block reminders for a day.
Schedule::command('reminders:dispatch')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();

// Telescope keeps 48 hours of entries (only when it's enabled).
if (config('telescope.enabled') && class_exists(\Laravel\Telescope\Telescope::class)) {
    Schedule::command('telescope:prune --hours=48')->daily();
}

// Housekeeping: drop read notifications and activity older than their retention (see the models' prunable()).
Schedule::command('model:prune')->dailyAt('03:15')->onOneServer();

// Expired app sign-in tokens.
Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();
