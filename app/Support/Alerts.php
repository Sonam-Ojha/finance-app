<?php

namespace App\Support;

use App\Models\User;

/**
 * The Notifications page's alerts, worked out from the user's own numbers
 * exactly as the app's notifications screen does — whole-month budget first,
 * then each category with a budget in force, then the nudges.
 */
class Alerts
{
    /**
     * @return array{items: array<int, array{tone: string, title: string, body: string, href: string}>, over: int}
     *               `over` counts only budgets already passed — the bell's red dot.
     */
    public static function forMonth(User $user, string $month, DisplayCurrency $money): array
    {
        $totals  = Reports::categoryTotals($user->id, $month);
        $budgets = MonthBudgets::load($user->id, $month);
        $items   = [];
        $over    = 0;

        $whole = $budgets->wholeMinor();
        if ($whole > 0) {
            $spent = (int) $totals->sum('total_minor');
            if ($spent > $whole) {
                $over++;
                $items[] = self::item('danger', 'This month is over budget',
                    $money->format($spent - $whole) . ' past the ' . $money->format($whole) . ' limit for the whole month.',
                    route('budgets.index'));
            } elseif ($spent >= $whole * MonthBudgets::NEAR_LIMIT) {
                $items[] = self::item('warning', 'This month is nearly spent',
                    round($spent / $whole * 100) . '% of the ' . $money->format($whole) . ' monthly budget is gone.',
                    route('budgets.index'));
            }
        }

        $anyInForce = false;
        foreach ($totals as $row) {
            $budget = $budgets->inForce($row['category_id'], $row['budget_minor']);
            if ($budget !== null) $anyInForce = true;
            if (! ($budget > 0)) continue;

            $total = $row['total_minor'];
            $href  = route('transactions.index', ['category' => $row['category_id']]);
            if ($total > $budget) {
                $over++;
                $items[] = self::item('danger', "{$row['category_name']} is over budget",
                    $money->format($total - $budget) . ' past its ' . $money->format($budget) . ' limit this month.', $href);
            } elseif ($total >= $budget * MonthBudgets::NEAR_LIMIT) {
                $items[] = self::item('warning', "{$row['category_name']} is nearly spent",
                    round($total / $budget * 100) . '% of its ' . $money->format($budget) . ' budget is gone.', $href);
            }
        }

        if (Reports::lifetime($user->id)['income_minor'] === 0) {
            $items[] = self::item('info', 'No income recorded yet',
                'Add what comes in and the home screen can show what is left, not just what went out.',
                route('incomes.create'));
        }

        if ($totals->isNotEmpty() && ! $anyInForce && $whole === null) {
            $items[] = self::item('info', 'No budgets set',
                'Give a category a monthly budget and this page will warn you before you pass it.',
                route('budgets.index'));
        }

        return ['items' => $items, 'over' => $over];
    }

    private static function item(string $tone, string $title, string $body, string $href): array
    {
        return compact('tone', 'title', 'body', 'href');
    }
}
