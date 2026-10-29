<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\MonthlySummaryMail;
use App\Support\Labels;
use App\Support\MonthlySummary;
use Illuminate\Console\Command;
use Throwable;

/**
 * The monthly summary email: on the 1st, from 9 a.m. India time, last
 * month's money in, spending, savings, top categories and budgets — to every
 * user who kept it on (Settings → Reminders) and recorded anything that
 * month. Days 2 and 3 catch up if the server was down on the 1st.
 */
class SendMonthlySummaries extends Command
{
    protected $signature = 'summary:monthly {--month= : Sum up this month (YYYY-MM) instead of last month, now}';

    protected $description = 'Email each user a summary of last month';

    /** Users per run; the hourly schedule sends the rest next time. */
    public const BATCH = 200;

    public function handle(): int
    {
        $now = Labels::now();
        $month = $this->option('month');
        if (! $month) {
            if ($now->day > 3 || ($now->day === 1 && $now->hour < 9)) return self::SUCCESS;
            $month = $now->copy()->subMonthNoOverflow()->format('Y-m');
        }

        $sent = 0;
        User::query()
            ->where('monthly_summary', true)
            ->whereNotNull('email')->where('email', '!=', '')
            ->where(fn ($q) => $q->whereNull('summary_sent_for')->orWhere('summary_sent_for', '!=', $month))
            ->limit(self::BATCH)
            ->get()
            ->each(function (User $user) use ($month, &$sent) {
                $user->forceFill(['summary_sent_for' => $month])->save();
                $summary = MonthlySummary::for($user, $month);
                if (! $summary) return;
                try {
                    $user->notify(new MonthlySummaryMail($summary));
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("Emailed {$sent} monthly " . ($sent === 1 ? 'summary' : 'summaries') . " for {$month}.");

        return self::SUCCESS;
    }
}
