<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Reports;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** The sums themselves live in App\Support\Reports, shared with the website. */
class ReportController extends Controller
{
    // §33 — Month summary
    public function monthSummary(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $uid = $request->user()->id;
        $prevMonth = Reports::previousMonth($request->month);

        return response()->json(['data' => [
            'month'                => $request->month,
            'total_minor'          => Reports::spentInMonth($uid, $request->month),
            'expense_count'        => Reports::monthExpenses($uid, $request->month)->count(),
            'previous_month'       => $prevMonth,
            'previous_total_minor' => Reports::spentInMonth($uid, $prevMonth),
        ]]);
    }

    // §34 — Category totals (LEFT JOIN — all categories, even zero-spend)
    public function categoryTotals(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);

        return response()->json(['data' => Reports::categoryTotals($request->user()->id, $request->month)]);
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
            $key = $cursor->format('Y-m');
            $result[] = ['month' => $key, 'total_minor' => Reports::spentInMonth($uid, $key)];
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

        return response()->json(['data' => Reports::flow(
            $request->user()->id,
            $request->mode,
            (int) ($request->count ?? 6),
            Carbon::parse($request->today),
        )]);
    }

    // §37 — Day flow (recent active days)
    public function dayFlow(Request $request)
    {
        return response()->json(['data' => Reports::dayFlow($request->user()->id, (int) $request->query('limit', 8))]);
    }

    // §38 — Lifetime totals
    public function lifetime(Request $request)
    {
        return response()->json(['data' => Reports::lifetime($request->user()->id)]);
    }

    // §39 — Dashboard composite
    public function dashboard(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        $uid = $request->user()->id;

        $expenses = Reports::monthExpenses($uid, $request->month)
            ->map(fn($e) => [
                'id' => $e->id, 'amount_minor' => $e->amount_minor,
                'category_id' => $e->category_id, 'note' => $e->note,
                'payment_method' => $e->payment_method,
                'spent_at' => $e->spent_at->toDateString(),
                'category_name' => $e->category->name,
                'category_icon' => $e->category->icon,
                'category_color'=> $e->category->color,
            ]);

        return response()->json(['data' => [
            'month'    => $request->month,
            'expenses' => $expenses,
            'month_summary' => [
                'total_minor' => (int) $expenses->sum('amount_minor'),
                'expense_count' => $expenses->count(),
                'previous_total_minor' => Reports::spentInMonth($uid, Reports::previousMonth($request->month)),
            ],
            'category_totals' => Reports::categoryTotals($uid, $request->month),
            'lifetime' => Reports::lifetime($uid),
        ]]);
    }

    // §40 — Alerts
    public function alerts(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);

        return response()->json(['data' => Reports::alerts($request->user()->id, $request->month)]);
    }
}
