<?php

namespace App\Console\Commands;

use App\Models\ScheduledNotification;
use App\Support\AdminNotifier;
use Illuminate\Console\Command;

/**
 * Sends the scheduled admin notifications whose time has come. Runs every
 * minute from the scheduler (routes/console.php), which the server's cron
 * starts: `* * * * * php artisan schedule:run`.
 */
class SendScheduledNotifications extends Command
{
    protected $signature = 'notifications:send-scheduled';

    protected $description = 'Send the admin notifications whose scheduled time has come';

    /** Later than this (the server was down, say) and a run is skipped: no "good evening" at 3 a.m. */
    public const GRACE_MINUTES = 60;

    public function handle(): int
    {
        $due = ScheduledNotification::with(['creator', 'target'])
            ->where('active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->get();

        foreach ($due as $schedule) {
            $dueAt = $schedule->next_run_at;
            $next = $schedule->nextRunAfter(now());

            // Claimed before sending, so an overlapping run cannot send it twice.
            $claimed = ScheduledNotification::whereKey($schedule->id)
                ->where('active', true)
                ->where('next_run_at', $dueAt)
                ->update(['next_run_at' => $next, 'active' => $next !== null]);
            if (! $claimed) continue;

            if ($dueAt->lt(now()->subMinutes(self::GRACE_MINUTES))) {
                $this->warn("Skipped #{$schedule->id} “{$schedule->title}”: it was due at {$dueAt}, too long ago.");
                continue;
            }
            // Sent to one user who has since gone: nothing to send (the schedule goes with them).
            if ($schedule->target_user_id && ! $schedule->target) continue;

            $result = AdminNotifier::send($schedule->creator, $schedule->title, $schedule->body, $schedule->target,
                $schedule->via_push, $schedule->via_email, schedule: $schedule);
            ScheduledNotification::whereKey($schedule->id)->increment('runs', 1, ['last_run_at' => now()]);

            $this->info("Sent #{$schedule->id} “{$schedule->title}”. " . implode(' ', $result['notes']));
        }

        return self::SUCCESS;
    }
}
