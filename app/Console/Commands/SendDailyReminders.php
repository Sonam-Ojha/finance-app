<?php

namespace App\Console\Commands;

use App\Models\AppExpense;
use App\Models\User;
use App\Support\Labels;
use App\Support\UserPush;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * The evening nudge: "Logged today's spending?" — pushed at each user's
 * reminder time (9 p.m. India time unless they changed it; Settings turns
 * it off), and only to someone who has not added an expense for today.
 */
class SendDailyReminders extends Command
{
    protected $signature = 'reminders:daily';

    protected $description = 'Push the daily "log today\'s spending" reminder to users whose time has come';

    /** Still sent this long after its time (the cron missed a run, say); later than that, not today. */
    public const WINDOW_MINUTES = 120;

    public function handle(): int
    {
        $now = Labels::now();
        $today = $now->toDateString();
        $sent = 0;

        User::query()
            ->whereNotNull('daily_reminder_at')
            ->where(fn ($q) => $q->whereNull('last_reminded_on')->orWhere('last_reminded_on', '<', $today))
            ->whereHas('deviceTokens')
            ->each(function (User $user) use ($now, $today, &$sent) {
                [$hour, $minute] = array_map('intval', explode(':', $user->daily_reminder_at));
                $at = $now->copy()->setTime($hour, $minute);
                if ($now->lt($at) || $now->gt($at->copy()->addMinutes(self::WINDOW_MINUTES))) return;

                $user->forceFill(['last_reminded_on' => $today])->save();
                // Already logged something for today: nothing to remind them of.
                if (AppExpense::where('user_id', $user->id)->whereDate('spent_at', $today)->exists()) return;

                if (UserPush::send($user, 'Logged today’s spending? ✍️',
                    'Two minutes now keeps your month accurate. Tap to add what you spent today.', ['type' => 'reminder'])) {
                    $sent++;
                }
            });

        $this->info("Reminded {$sent} " . Str::plural('user', $sent) . '.');

        return self::SUCCESS;
    }
}
