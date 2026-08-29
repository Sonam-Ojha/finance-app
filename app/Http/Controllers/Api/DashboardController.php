<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\Expense;
use App\Models\BankAccount;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\Investment;
use App\Models\Commission;
use App\Models\Cashback;
use App\Models\BadDebt;
use App\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $currentMonth = Carbon::now()->month;
        $currentYear  = Carbon::now()->year;

        $totalIncome   = Income::where('user_id', $user->id)->whereMonth('date', $currentMonth)->whereYear('date', $currentYear)->sum('amount');
        $totalExpense  = Expense::where('user_id', $user->id)->whereMonth('date', $currentMonth)->whereYear('date', $currentYear)->sum('amount');
        $bankBalance   = BankAccount::where('user_id', $user->id)->sum('current_balance');
        $creditOutstanding = CreditCard::where('user_id', $user->id)->sum('outstanding_amount');
        $loanPending   = Loan::where('user_id', $user->id)->where('status', 'active')->sum('pending_amount');
        $investmentValue = Investment::where('user_id', $user->id)->sum('current_value');
        $commissionReceived = Commission::where('user_id', $user->id)->where('status', 'received')->whereMonth('date', $currentMonth)->whereYear('date', $currentYear)->sum('commission_amount');
        $cashbackReceived = Cashback::where('user_id', $user->id)->where('status', 'received')->whereMonth('date', $currentMonth)->whereYear('date', $currentYear)->sum('amount');
        $pendingReceivables = BadDebt::where('user_id', $user->id)->whereIn('status', ['pending', 'partial_received'])->selectRaw('SUM(amount - received_amount) as total')->value('total') ?? 0;

        $months = collect(range(5, 0))->map(fn($i) => Carbon::now()->subMonths($i));
        $monthlyChart = $months->map(fn($m) => [
            'month'   => $m->format('M Y'),
            'income'  => Income::where('user_id', $user->id)->whereMonth('date', $m->month)->whereYear('date', $m->year)->sum('amount'),
            'expense' => Expense::where('user_id', $user->id)->whereMonth('date', $m->month)->whereYear('date', $m->year)->sum('amount'),
        ])->values();

        $categoryExpenses = Expense::with('category')
            ->where('user_id', $user->id)
            ->whereMonth('date', $currentMonth)->whereYear('date', $currentYear)
            ->get()
            ->groupBy('expense_category_id')
            ->map(fn($items) => ['category' => $items->first()->category->name ?? 'Unknown', 'total' => $items->sum('amount')])
            ->values();

        $reminders = Reminder::where('user_id', $user->id)->where('is_done', false)
            ->where('due_date', '>=', Carbon::today())->orderBy('due_date')->take(5)->get();

        $recentIncomes  = Income::where('user_id', $user->id)->latest('date')->take(5)->get();
        $recentExpenses = Expense::with('category')->where('user_id', $user->id)->latest('date')->take(5)->get();

        return response()->json([
            'summary' => [
                'total_income'          => $totalIncome,
                'total_expense'         => $totalExpense,
                'net_savings'           => $totalIncome - $totalExpense,
                'bank_balance'          => $bankBalance,
                'credit_outstanding'    => $creditOutstanding,
                'loan_pending'          => $loanPending,
                'investment_value'      => $investmentValue,
                'commission_received'   => $commissionReceived,
                'cashback_received'     => $cashbackReceived,
                'pending_receivables'   => $pendingReceivables,
            ],
            'monthly_chart'     => $monthlyChart,
            'category_expenses' => $categoryExpenses,
            'upcoming_reminders'=> $reminders,
            'recent_incomes'    => $recentIncomes,
            'recent_expenses'   => $recentExpenses,
        ]);
    }
}
