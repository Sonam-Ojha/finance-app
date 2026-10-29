<?php

namespace App\Support;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The sums behind the app's reports, shared by the API and the website so
 * both always show the same numbers. Months are "YYYY-MM"; amounts are
 * hundredths of a rupee.
 */
class Reports
{
    /** [first day, first day of the next month] as Y-m-d. */
    public static function monthRange(string $month): array
    {
        $start = Carbon::parse($month . '-01');
        return [$start->toDateString(), $start->copy()->addMonth()->toDateString()];
    }

    public static function previousMonth(string $month): string
    {
        return Carbon::parse($month . '-01')->subMonth()->format('Y-m');
    }

    public static function spentInMonth(int $uid, string $month): int
    {
        [$start, $end] = self::monthRange($month);
        return (int) AppExpense::where('user_id', $uid)
            ->where('spent_at', '>=', $start)->where('spent_at', '<', $end)
            ->sum('amount_minor');
    }

    public static function receivedInMonth(int $uid, string $month): int
    {
        [$start, $end] = self::monthRange($month);
        return (int) AppIncome::where('user_id', $uid)
            ->where('received_at', '>=', $start)->where('received_at', '<', $end)
            ->sum('amount_minor');
    }

    /** One month's expenses, newest first, with their category. */
    public static function monthExpenses(int $uid, string $month): Collection
    {
        [$start, $end] = self::monthRange($month);
        return AppExpense::with('category')
            ->where('user_id', $uid)
            ->where('spent_at', '>=', $start)->where('spent_at', '<', $end)
            ->orderByDesc('spent_at')->orderByDesc('id')
            ->get();
    }

    /** Every category with its spending that month — zero-spend ones too — biggest first. */
    public static function categoryTotals(int $uid, string $month): Collection
    {
        [$start, $end] = self::monthRange($month);

        return Category::where('categories.user_id', $uid)
            ->leftJoin('app_expenses as e', function ($join) use ($start, $end) {
                $join->on('e.category_id', '=', 'categories.id')
                     ->where('e.spent_at', '>=', $start)
                     ->where('e.spent_at', '<', $end);
            })
            ->select(
                'categories.id as category_id',
                'categories.name as category_name',
                'categories.icon as category_icon',
                'categories.color as category_color',
                'categories.budget_minor',
                DB::raw('COALESCE(SUM(e.amount_minor), 0) as total_minor'),
                DB::raw('COUNT(e.id) as count')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.icon', 'categories.color', 'categories.budget_minor', 'categories.sort_order')
            ->orderByDesc('total_minor')
            ->orderBy('categories.sort_order')
            ->get()
            ->map(fn ($c) => [
                'category_id'    => $c->category_id,
                'category_name'  => $c->category_name,
                'category_icon'  => $c->category_icon,
                'category_color' => $c->category_color,
                'budget_minor'   => $c->budget_minor,
                'total_minor'    => (int) $c->total_minor,
                'count'          => (int) $c->count,
            ]);
    }

    /**
     * The breakdown ring: categories with spending, biggest first; the five
     * largest by name and the rest folded into "Others".
     *
     * @return array{slices: array, total: int}
     */
    public static function slices(Collection $totals): array
    {
        $spent  = $totals->filter(fn ($c) => $c['total_minor'] > 0)->sortByDesc('total_minor')->values();
        $total  = (int) $spent->sum('total_minor');
        $slice  = fn ($id, $name, $icon, $color, $minor) => [
            'category_id' => $id, 'name' => $name, 'icon' => $icon, 'color' => $color, 'total_minor' => $minor,
            'share' => $total > 0 ? (int) round($minor / $total * 100) : 0,
        ];

        $slices = $spent->take(5)->map(fn ($c) => $slice($c['category_id'], $c['category_name'], $c['category_icon'], $c['category_color'], $c['total_minor']))->all();
        $rest   = (int) $spent->slice(5)->sum('total_minor');
        if ($rest > 0) $slices[] = $slice(null, 'Others', '•••', '#94A3B8', $rest);

        return ['slices' => $slices, 'total' => $total];
    }

    /**
     * Income and spending per week (from Sunday), month or year, oldest
     * first, ending with the one that holds $today.
     */
    public static function flow(int $uid, string $mode, int $count, Carbon $today): array
    {
        $buckets = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            if ($mode === 'weekly') {
                $start = $today->copy()->startOfWeek(Carbon::SUNDAY)->subWeeks($i);
                $end   = $start->copy()->addWeek();
                $key   = $start->toDateString();
            } elseif ($mode === 'monthly') {
                $start = $today->copy()->startOfMonth()->subMonths($i);
                $end   = $start->copy()->addMonth();
                $key   = $start->format('Y-m');
            } else {
                $start = $today->copy()->startOfYear()->subYears($i);
                $end   = $start->copy()->addYear();
                $key   = $start->format('Y');
            }
            $incomeMinor  = AppIncome::where('user_id', $uid)->where('received_at', '>=', $start->toDateString())->where('received_at', '<', $end->toDateString())->sum('amount_minor');
            $expenseMinor = AppExpense::where('user_id', $uid)->where('spent_at', '>=', $start->toDateString())->where('spent_at', '<', $end->toDateString())->sum('amount_minor');
            $buckets[] = [
                'key'           => $key,
                'start'         => $start->toDateString(),
                'end'           => $end->toDateString(),
                'income_minor'  => (int) $incomeMinor,
                'expense_minor' => (int) $expenseMinor,
            ];
        }

        return $buckets;
    }

    /** The latest days with any income or spending, newest first. */
    public static function dayFlow(int $uid, int $limit): array
    {
        $sql = "
            SELECT day, SUM(income_minor) as income_minor, SUM(expense_minor) as expense_minor
            FROM (
                SELECT spent_at as day, 0 as income_minor, amount_minor as expense_minor
                FROM app_expenses WHERE user_id = ?
                UNION ALL
                SELECT received_at as day, amount_minor as income_minor, 0 as expense_minor
                FROM app_incomes WHERE user_id = ?
            ) t
            GROUP BY day
            ORDER BY day DESC
            LIMIT ?
        ";

        $rows = DB::select($sql, [$uid, $uid, $limit]);
        return array_map(fn ($r) => [
            'day'           => $r->day,
            'income_minor'  => (int) $r->income_minor,
            'expense_minor' => (int) $r->expense_minor,
        ], $rows);
    }

    /** Everything ever received and spent. */
    public static function lifetime(int $uid): array
    {
        return [
            'income_minor'  => (int) AppIncome::where('user_id', $uid)->sum('amount_minor'),
            'expense_minor' => (int) AppExpense::where('user_id', $uid)->sum('amount_minor'),
        ];
    }

    /**
     * Budget warnings for the month — a category at 80% of its budget is
     * "near", past it "over" — plus nudges when there is no income or no
     * budget at all.
     */
    public static function alerts(int $uid, string $month): array
    {
        [$start, $end] = self::monthRange($month);

        $cats = Category::where('categories.user_id', $uid)
            ->leftJoin('app_expenses as e', function ($j) use ($start, $end) {
                $j->on('e.category_id', '=', 'categories.id')
                  ->where('e.spent_at', '>=', $start)->where('e.spent_at', '<', $end);
            })
            ->select('categories.id', 'categories.budget_minor', DB::raw('COALESCE(SUM(e.amount_minor),0) as total_minor'))
            ->groupBy('categories.id', 'categories.budget_minor')
            ->get();

        $alerts = [];
        foreach ($cats as $c) {
            if (! $c->budget_minor) continue;
            if ($c->total_minor > $c->budget_minor) {
                $alerts[] = ['kind' => 'over', 'category_id' => $c->id, 'total_minor' => (int) $c->total_minor, 'budget_minor' => (int) $c->budget_minor];
            } elseif ($c->total_minor >= $c->budget_minor * 0.8) {
                $alerts[] = ['kind' => 'near', 'category_id' => $c->id, 'total_minor' => (int) $c->total_minor, 'budget_minor' => (int) $c->budget_minor];
            }
        }

        if (AppIncome::where('user_id', $uid)->count() === 0) $alerts[] = ['kind' => 'no-income'];
        if (Category::where('user_id', $uid)->whereNotNull('budget_minor')->count() === 0) $alerts[] = ['kind' => 'no-budgets'];

        return $alerts;
    }
}
