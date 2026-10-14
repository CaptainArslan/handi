<?php

use Illuminate\Support\Facades\Schedule;

// Each user's morning reminder at their chosen time (checked every minute, in their timezone).
Schedule::command('reminders:dispatch')
    ->everyMinute()
    ->withoutOverlapping();
