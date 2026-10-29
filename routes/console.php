<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Admin › Notifications › Schedule. The server's cron runs `schedule:run` every minute.
Schedule::command('notifications:send-scheduled')->everyMinute()->withoutOverlapping(10);
