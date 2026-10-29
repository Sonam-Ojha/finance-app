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

// ── Security: the app's sign-ins (config/sanctum.php) ──
// Tokens past a year old, then ones unused for 90 days and the phones left
// without a sign-in (App\Console\Commands\PruneIdleTokens).
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:10');
Schedule::command('tokens:prune-idle --hours=24')->dailyAt('03:20');
// ── end Security ──

// ── Queue: no long-running worker on shared hosting, so the scheduler runs ──
// one each minute until the queue is empty (at most ~50 s). It carries the
// emails (activity, groups) and pushes (groups, goals, budget alerts, admin
// notifications) that requests used to send inline. Kept last, so the jobs the
// commands above queue go out in the same minute. Failed jobs: `failed_jobs`.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(2);
// ── end Queue ──
