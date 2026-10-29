<?php

namespace App\Support;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\User;
use Carbon\Carbon;

/** What the monthly summary email says about one user's month (App\Console\Commands\SendMonthlySummaries). */
class MonthlySummary
{
    /**
     * Null for a month with nothing recorded — there is nothing to sum up.
     *
     * @return array{month: string, label: string, in: string, spent: string, saved: string, overspent: bool,
     *               change: ?string, categories: list<array{name: string, amount: string, share: int}>,
     *               over: list<string>, entries: int}|null
     */
    public static function for(User $user, string $month): ?array
    {
        [$start, $end] = Reports::monthRange($month);
        $entries = AppExpense::where('user_id', $user->id)->where('spent_at', '>=', $start)->where('spent_at', '<', $end)->count()
            + AppIncome::where('user_id', $user->id)->where('received_at', '>=', $start)->where('received_at', '<', $end)->count();
        if ($entries === 0) return null;

        $money    = DisplayCurrency::for($user);
        $spent    = Reports::spentInMonth($user->id, $month);
        $received = Reports::receivedInMonth($user->id, $month);
        $previous = Reports::spentInMonth($user->id, Reports::previousMonth($month));
        $label    = Carbon::createFromFormat('!Y-m', $month)->format('F Y');
        $prevName = Carbon::createFromFormat('!Y-m', Reports::previousMonth($month))->format('F');

        $change = MonthChange::of($spent, $previous);
        $changeText = match ($change['kind']) {
            'percent' => $change['value'] === 0
                ? "You spent about the same as in {$prevName}."
                : 'You spent ' . abs($change['value']) . '% ' . ($change['value'] > 0 ? 'more' : 'less') . " than in {$prevName}.",
            default   => null,
        };

        $totals = Reports::categoryTotals($user->id, $month);
        $budgets = MonthBudgets::load($user->id, $month);
        $over = [];
        foreach ($totals as $row) {
            $budget = $budgets->inForce($row['category_id'], $row['budget_minor']);
            if ($budget > 0 && $row['total_minor'] > $budget) {
                $over[] = "{$row['category_name']} went " . $money->format($row['total_minor'] - $budget) . ' over its ' . $money->format($budget) . ' budget.';
            }
        }
        $whole = $budgets->wholeMinor();
        if ($whole > 0 && $spent > $whole) {
            array_unshift($over, 'The month went ' . $money->format($spent - $whole) . ' over its ' . $money->format($whole) . ' budget.');
        }

        $categories = $totals->filter(fn ($row) => $row['total_minor'] > 0)->sortByDesc('total_minor')->take(5)
            ->map(fn ($row) => [
                'name'   => trim("{$row['category_icon']} {$row['category_name']}"),
                'amount' => $money->format($row['total_minor']),
                'share'  => $spent > 0 ? (int) round($row['total_minor'] / $spent * 100) : 0,
            ])->values()->all();

        return [
            'month'      => $month,
            'label'      => $label,
            'in'         => $money->format($received),
            'spent'      => $money->format($spent),
            'saved'      => $money->format(abs($received - $spent)),
            'overspent'  => $received - $spent < 0,
            'change'     => $changeText,
            'categories' => $categories,
            'over'       => $over,
            'entries'    => $entries,
        ];
    }
}
