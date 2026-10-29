<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Support\DisplayCurrency;
use App\Support\Labels;
use App\Support\MonthChange;
use App\Support\Reports;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** The app's Home tab: always the current month. */
class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $uid   = $user->id;
        $money = DisplayCurrency::for($user);
        $month = Labels::now()->format('Y-m');
        $prev  = Reports::previousMonth($month);

        $expenses = Reports::monthExpenses($uid, $month);
        $incomes  = self::monthIncomes($uid, $month);

        $spent          = (int) $expenses->sum('amount_minor');
        $received       = (int) $incomes->sum('amount_minor');
        $previousSpent  = Reports::spentInMonth($uid, $prev);
        $previousIncome = Reports::receivedInMonth($uid, $prev);
        $saved          = $received - $spent;
        $lifetime       = Reports::lifetime($uid);
        $balance        = $lifetime['income_minor'] - $lifetime['expense_minor'];

        $prevExpenses = Reports::monthExpenses($uid, $prev);
        $prevIncomes  = self::monthIncomes($uid, $prev);

        return view('tracker.home', [
            'money'    => $money,
            'user'     => $user,
            'balance'  => $balance,
            'noIncome' => $lifetime['income_minor'] === 0,
            'balanceChange' => MonthChange::of($balance, $balance - $saved),
            'tiles' => [
                ['Income', $received, MonthChange::of($received, $previousIncome), true, 'money-in'],
                ['Expenses', $spent, MonthChange::of($spent, $previousSpent), false, 'money-out'],
                ['Savings', $saved, MonthChange::of($saved, $previousIncome - $previousSpent), true, 'text-brand-navy dark:text-brand-cyan'],
            ],
            'overview' => [
                'this' => self::weeks($month, $expenses, $incomes, true),
                'last' => self::weeks($prev, $prevExpenses, $prevIncomes, false),
            ],
            'recent'    => self::merged($expenses, $incomes)->take(5),
            'breakdown' => Reports::slices(Reports::categoryTotals($uid, $month)),
        ]);
    }

    private static function monthIncomes(int $uid, string $month): Collection
    {
        [$start, $end] = Reports::monthRange($month);

        return AppIncome::where('user_id', $uid)
            ->where('received_at', '>=', $start)->where('received_at', '<', $end)
            ->orderByDesc('received_at')->orderByDesc('id')
            ->get();
    }

    /**
     * Spending Overview: days 1–7, 8–14, 15–21 and 22 to the month's end,
     * with the current week picked out on the current month.
     */
    private static function weeks(string $month, Collection $expenses, Collection $incomes, bool $current): array
    {
        $first  = Carbon::parse($month . '-01');
        $last   = $first->daysInMonth;
        $ranges = [[1, 7], [8, 14], [15, 21], [22, $last]];
        $week   = fn (int $day) => min((int) ceil($day / 7), 4) - 1;

        $income  = [0, 0, 0, 0];
        $expense = [0, 0, 0, 0];
        foreach ($incomes as $i) $income[$week($i->received_at->day)] += $i->amount_minor;
        foreach ($expenses as $e) $expense[$week($e->spent_at->day)] += $e->amount_minor;

        return [
            'labels'    => ['W1', 'W2', 'W3', 'W4'],
            'captions'  => array_map(fn ($r) => "{$r[0]}–{$r[1]} " . $first->format('M'), $ranges),
            'income'    => $income,
            'expense'   => $expense,
            'highlight' => $current ? $week(Labels::now()->day) : -1,
        ];
    }

    /** The month's expenses and incomes as one list, newest day first, as Transactions shapes them. */
    public static function merged(Collection $expenses, Collection $incomes): Collection
    {
        $rows = $expenses->map(fn (AppExpense $e) => [
            'type' => 'expense', 'id' => $e->id, 'amount_minor' => $e->amount_minor,
            'date' => $e->spent_at->toDateString(), 'note' => $e->note, 'payment_method' => $e->payment_method,
            'category_id' => $e->category_id, 'category_name' => $e->category->name,
            'category_icon' => $e->category->icon, 'category_color' => $e->category->color, 'source' => null,
        ])->concat($incomes->map(fn (AppIncome $i) => [
            'type' => 'income', 'id' => $i->id, 'amount_minor' => $i->amount_minor,
            'date' => $i->received_at->toDateString(), 'note' => $i->note, 'payment_method' => $i->payment_method,
            'category_id' => null, 'category_name' => null, 'category_icon' => null, 'category_color' => null,
            'source' => $i->source,
        ]));

        return $rows->sort(fn ($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']])->values();
    }
}
