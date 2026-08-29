<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // §33 — Month summary
    public function monthSummary(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $uid = $request->user()->id;

        [$curStart, $curEnd]   = $this->monthRange($request->month);
        $prevMonth = Carbon::parse($curStart)->subMonth()->format('Y-m');
        [$prevStart, $prevEnd] = $this->monthRange($prevMonth);

        $total = AppExpense::where('user_id', $uid)
            ->where('spent_at', '>=', $curStart)->where('spent_at', '<', $curEnd)
            ->sum('amount_minor');

        $count = AppExpense::where('user_id', $uid)
            ->where('spent_at', '>=', $curStart)->where('spent_at', '<', $curEnd)
            ->count();

        $prevTotal = AppExpense::where('user_id', $uid)
            ->where('spent_at', '>=', $prevStart)->where('spent_at', '<', $prevEnd)
            ->sum('amount_minor');

        return response()->json(['data' => [
            'month'                => $request->month,
            'total_minor'          => (int) $total,
            'expense_count'        => $count,
            'previous_month'       => $prevMonth,
            'previous_total_minor' => (int) $prevTotal,
        ]]);
    }

    // §34 — Category totals (LEFT JOIN — all categories, even zero-spend)
    public function categoryTotals(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $uid = $request->user()->id;
        [$start, $end] = $this->monthRange($request->month);

        $cats = Category::where('categories.user_id', $uid)
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
            ->map(fn($c) => [
                'category_id'    => $c->category_id,
                'category_name'  => $c->category_name,
                'category_icon'  => $c->category_icon,
                'category_color' => $c->category_color,
                'budget_minor'   => $c->budget_minor,
                'total_minor'    => (int) $c->total_minor,
                'count'          => (int) $c->count,
            ]);

        return response()->json(['data' => $cats]);
    }

    // §35 — Per-month totals (zero-filled range)
    public function months(Request $request)
    {
        $request->validate([
            'from' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'to'   => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $uid = $request->user()->id;

        $cursor = Carbon::parse($request->from . '-01');
        $toDate = Carbon::parse($request->to . '-01');
        $result = [];

        while ($cursor->lte($toDate)) {
            $key   = $cursor->format('Y-m');
            [$start, $end] = $this->monthRange($key);
            $total = AppExpense::where('user_id', $uid)
                ->where('spent_at', '>=', $start)->where('spent_at', '<', $end)
                ->sum('amount_minor');
            $result[] = ['month' => $key, 'total_minor' => (int) $total];
            $cursor->addMonth();
        }

        return response()->json(['data' => $result]);
    }

    // §36 — Flow chart (weekly/monthly/yearly)
    public function flow(Request $request)
    {
        $request->validate([
            'mode'  => 'required|in:weekly,monthly,yearly',
            'count' => 'nullable|integer|min:1|max:24',
            'today' => 'required|date_format:Y-m-d',
        ]);
        $uid   = $request->user()->id;
        $count = (int) ($request->count ?? 6);
        $today = Carbon::parse($request->today);
        $mode  = $request->mode;

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

        return response()->json(['data' => $buckets]);
    }

    // §37 — Day flow (recent active days)
    public function dayFlow(Request $request)
    {
        $uid   = $request->user()->id;
        $limit = (int) ($request->query('limit', 8));

        $expenseDays = AppExpense::where('user_id', $uid)
            ->select('spent_at as day', DB::raw('SUM(amount_minor) as expense_minor'))
            ->groupBy('spent_at');

        $incomeDays = AppIncome::where('user_id', $uid)
            ->select('received_at as day', DB::raw('SUM(amount_minor) as income_minor'))
            ->groupBy('received_at');

        $days = DB::table(DB::raw("(
            SELECT COALESCE(e.day, i.day) as day,
                   COALESCE(e.expense_minor, 0) as expense_minor,
                   COALESCE(i.income_minor, 0) as income_minor
            FROM ({$expenseDays->toSql()}) e
            FULL OUTER JOIN ({$incomeDays->toSql()}) i ON e.day = i.day
        ) as combined"))
        ->mergeBindings($expenseDays->getQuery())
        ->mergeBindings($incomeDays->getQuery())
        ->orderByDesc('day')
        ->limit($limit)
        ->get();

        // MySQL doesn't support FULL OUTER JOIN — use UNION approach
        $result = $this->dayFlowUnion($uid, $limit);

        return response()->json(['data' => $result]);
    }

    private function dayFlowUnion(int $uid, int $limit): array
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
        return array_map(fn($r) => [
            'day'           => $r->day,
            'income_minor'  => (int) $r->income_minor,
            'expense_minor' => (int) $r->expense_minor,
        ], $rows);
    }

    // §38 — Lifetime totals
    public function lifetime(Request $request)
    {
        $uid = $request->user()->id;
        return response()->json(['data' => [
            'income_minor'  => (int) AppIncome::where('user_id', $uid)->sum('amount_minor'),
            'expense_minor' => (int) AppExpense::where('user_id', $uid)->sum('amount_minor'),
        ]]);
    }

    // §39 — Dashboard composite
    public function dashboard(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $uid = $request->user()->id;
        [$start, $end] = $this->monthRange($request->month);
        $prevMonth = Carbon::parse($start)->subMonth()->format('Y-m');
        [$prevStart, $prevEnd] = $this->monthRange($prevMonth);

        $expenses = AppExpense::with('category')
            ->where('user_id', $uid)
            ->where('spent_at', '>=', $start)->where('spent_at', '<', $end)
            ->orderByDesc('spent_at')->orderByDesc('id')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id, 'amount_minor' => $e->amount_minor,
                'category_id' => $e->category_id, 'note' => $e->note,
                'spent_at' => $e->spent_at->toDateString(),
                'category_name' => $e->category->name,
                'category_icon' => $e->category->icon,
                'category_color'=> $e->category->color,
            ]);

        $totalMinor = $expenses->sum('amount_minor');
        $count      = $expenses->count();
        $prevTotal  = AppExpense::where('user_id', $uid)->where('spent_at', '>=', $prevStart)->where('spent_at', '<', $prevEnd)->sum('amount_minor');

        $catTotals = Category::where('categories.user_id', $uid)
            ->leftJoin('app_expenses as e', function ($j) use ($start, $end) {
                $j->on('e.category_id', '=', 'categories.id')
                  ->where('e.spent_at', '>=', $start)->where('e.spent_at', '<', $end);
            })
            ->select('categories.id as category_id','categories.name as category_name','categories.icon as category_icon','categories.color as category_color','categories.budget_minor',
                DB::raw('COALESCE(SUM(e.amount_minor),0) as total_minor'), DB::raw('COUNT(e.id) as count'))
            ->groupBy('categories.id','categories.name','categories.icon','categories.color','categories.budget_minor','categories.sort_order')
            ->orderByDesc('total_minor')->orderBy('categories.sort_order')
            ->get()->map(fn($c) => ['category_id' => $c->category_id,'category_name' => $c->category_name,'category_icon' => $c->category_icon,'category_color' => $c->category_color,'budget_minor' => $c->budget_minor,'total_minor' => (int)$c->total_minor,'count' => (int)$c->count]);

        return response()->json(['data' => [
            'month'    => $request->month,
            'expenses' => $expenses,
            'month_summary' => [
                'total_minor' => (int) $totalMinor,
                'expense_count' => $count,
                'previous_total_minor' => (int) $prevTotal,
            ],
            'category_totals' => $catTotals,
            'lifetime' => [
                'income_minor'  => (int) AppIncome::where('user_id', $uid)->sum('amount_minor'),
                'expense_minor' => (int) AppExpense::where('user_id', $uid)->sum('amount_minor'),
            ],
        ]]);
    }

    // §40 — Alerts
    public function alerts(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $uid = $request->user()->id;
        [$start, $end] = $this->monthRange($request->month);

        $cats = Category::where('categories.user_id', $uid)
            ->leftJoin('app_expenses as e', function ($j) use ($start, $end) {
                $j->on('e.category_id', '=', 'categories.id')
                  ->where('e.spent_at', '>=', $start)->where('e.spent_at', '<', $end);
            })
            ->select('categories.id','categories.budget_minor', DB::raw('COALESCE(SUM(e.amount_minor),0) as total_minor'))
            ->groupBy('categories.id','categories.budget_minor')
            ->get();

        $alerts = [];
        foreach ($cats as $c) {
            if (! $c->budget_minor) continue;
            if ($c->total_minor > $c->budget_minor) {
                $alerts[] = ['kind' => 'over', 'category_id' => $c->id, 'total_minor' => (int)$c->total_minor, 'budget_minor' => (int)$c->budget_minor];
            } elseif ($c->total_minor >= $c->budget_minor * 0.8) {
                $alerts[] = ['kind' => 'near', 'category_id' => $c->id, 'total_minor' => (int)$c->total_minor, 'budget_minor' => (int)$c->budget_minor];
            }
        }

        if (AppIncome::where('user_id', $uid)->count() === 0) $alerts[] = ['kind' => 'no-income'];
        if (Category::where('user_id', $uid)->whereNotNull('budget_minor')->count() === 0) $alerts[] = ['kind' => 'no-budgets'];

        return response()->json(['data' => $alerts]);
    }

    private function monthRange(string $month): array
    {
        $start = Carbon::parse($month . '-01');
        return [$start->toDateString(), $start->copy()->addMonth()->toDateString()];
    }
}
