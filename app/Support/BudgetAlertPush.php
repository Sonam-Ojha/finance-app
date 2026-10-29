<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

use function Illuminate\Support\defer;

/**
 * Budget alerts as pushes, the moment an expense crosses a line: 80% of a
 * budget used, then 100%. Each line is pushed once per budget per month
 * (the `budget_alert_pushes` table), and only for the current month — a
 * back-dated expense in an old month does not ring the phone.
 */
class BudgetAlertPush
{
    public const LEVELS = [100, 80];

    /** After an expense is saved: checks once the page has answered. */
    public static function afterExpense(User $user, CarbonInterface $spentAt): void
    {
        // Off only when turned off: a model not read back from the database has no value yet, and the default is on.
        if ($user->budget_alert_push === false) return;
        $month = $spentAt->format('Y-m');
        if ($month !== Labels::now()->format('Y-m')) return;

        defer(fn () => self::check($user, $month));
    }

    /** @return list<string> the titles pushed */
    public static function check(User $user, string $month): array
    {
        $money   = DisplayCurrency::for($user);
        $totals  = Reports::categoryTotals($user->id, $month);
        $budgets = MonthBudgets::load($user->id, $month);
        $pushed  = [];

        $whole = $budgets->wholeMinor();
        if ($whole > 0) {
            $spent = (int) $totals->sum('total_minor');
            if ($title = self::cross($user, 'month', $month, $spent, $whole, 'This month', $money)) $pushed[] = $title;
        }

        foreach ($totals as $row) {
            $budget = $budgets->inForce($row['category_id'], $row['budget_minor']);
            if (! ($budget > 0)) continue;
            $name = trim("{$row['category_icon']} {$row['category_name']}");
            if ($title = self::cross($user, "category:{$row['category_id']}", $month, $row['total_minor'], $budget, $name, $money)) $pushed[] = $title;
        }

        return $pushed;
    }

    /** Pushes the highest line newly crossed, if any; returns its title. */
    private static function cross(User $user, string $scope, string $month, int $spent, int $budget, string $name, DisplayCurrency $money): ?string
    {
        $ratio = $spent / $budget;
        $level = collect(self::LEVELS)->first(fn (int $line) => $ratio * 100 >= $line);
        if ($level === null) return null;

        // The unique row is the claim: a line already in the table was pushed
        // before. Lines below it are claimed too, so 100% is never followed by 80%.
        $claim = fn (int $line) => DB::table('budget_alert_pushes')->insertOrIgnore([
            'user_id' => $user->id, 'scope' => $scope, 'month' => $month, 'level' => $line, 'created_at' => now(),
        ]);
        $isNew = $claim($level) === 1;
        foreach (self::LEVELS as $line) {
            if ($line < $level) $claim($line);
        }
        if (! $isNew) return null;

        [$title, $body] = $level === 100
            ? ["{$name} is over budget", $money->format($spent) . ' spent of ' . $money->format($budget) . ' this month — ' . $money->format($spent - $budget) . ' over.']
            : ["{$name}: " . (int) floor($ratio * 100) . '% of budget used', $money->format($spent) . ' of ' . $money->format($budget) . ' spent. ' . $money->format($budget - $spent) . ' left this month.'];

        UserPush::send($user, $title, $body, ['type' => 'budget', 'month' => $month]);

        return $title;
    }
}
