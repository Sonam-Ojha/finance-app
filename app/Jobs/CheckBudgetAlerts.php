<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\BudgetAlertPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * After an expense is saved: pushes any budget line (80%, 100%) it newly
 * crossed — App\Support\BudgetAlertPush::check, which claims each line in
 * `budget_alert_pushes` so it rings once, however often this runs.
 */
class CheckBudgetAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 75;

    public function __construct(
        public int $userId,
        public string $month,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if ($user && $user->budget_alert_push !== false) BudgetAlertPush::check($user, $this->month);
    }
}
