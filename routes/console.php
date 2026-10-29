<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The server's cron runs `schedule:run` every minute. Each command checks
// India time itself, so a missed run is caught up by the next one.
Schedule::command('notifications:send-scheduled')->everyMinute()->withoutOverlapping(10); // Admin › Notifications › Schedule
Schedule::command('reminders:daily')->everyMinute()->withoutOverlapping(10);             // "Logged today's spending?"
Schedule::command('birthdays:wish')->everyThirtyMinutes()->withoutOverlapping(30);
Schedule::command('summary:monthly')->hourly()->withoutOverlapping(60);
Schedule::command('recurring:post')->everyThirtyMinutes()->withoutOverlapping(30);          // rent, EMIs, salary on their day
