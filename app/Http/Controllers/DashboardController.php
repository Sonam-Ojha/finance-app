<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $totalIncome = Income::where('user_id', $user->id)
            ->whereMonth('date', $currentMonth)->whereYear('date', $currentYear)
            ->sum('amount');

        $totalExpense = Expense::where('user_id', $user->id)
            ->whereMonth('date', $currentMonth)->whereYear('date', $currentYear)
            ->sum('amount');

        $bankBalance = BankAccount::where('user_id', $user->id)->sum('current_balance');
        $creditOutstanding = CreditCard::where('user_id', $user->id)->sum('outstanding_amount');
        $loanPending = Loan::where('user_id', $user->id)->where('status', 'active')->sum('pending_amount');
        $investmentValue = Investment::where('user_id', $user->id)->sum('current_value');
        $commissionReceived = Commission::where('user_id', $user->id)->where('status', 'received')
            ->whereMonth('date', $currentMonth)->whereYear('date', $currentYear)->sum('commission_amount');
        $cashbackReceived = Cashback::where('user_id', $user->id)->where('status', 'received')
            ->whereMonth('date', $currentMonth)->whereYear('date', $currentYear)->sum('amount');
        $pendingReceivables = BadDebt::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'partial_received'])
            ->selectRaw('SUM(amount - received_amount) as total')->value('total') ?? 0;

        // Monthly income vs expense for last 6 months
        $months = collect(range(5, 0))->map(fn($i) => Carbon::now()->subMonths($i));
        $monthlyIncome = $months->map(fn($m) => Income::where('user_id', $user->id)
            ->whereMonth('date', $m->month)->whereYear('date', $m->year)->sum('amount'));
        $monthlyExpense = $months->map(fn($m) => Expense::where('user_id', $user->id)
            ->whereMonth('date', $m->month)->whereYear('date', $m->year)->sum('amount'));
        $monthLabels = $months->map(fn($m) => $m->format('M Y'));

        // Category-wise expense this month
        $categoryExpenses = Expense::with('category')
            ->where('user_id', $user->id)
            ->whereMonth('date', $currentMonth)->whereYear('date', $currentYear)
            ->get()
            ->groupBy('expense_category_id')
            ->map(fn($items) => ['name' => $items->first()->category->name ?? 'Unknown', 'total' => $items->sum('amount')]);

        // Upcoming reminders
        $reminders = Reminder::where('user_id', $user->id)
            ->where('is_done', false)
            ->where('due_date', '>=', Carbon::today())
            ->orderBy('due_date')
            ->take(5)->get();

        // Recent transactions
        $recentIncomes = Income::where('user_id', $user->id)->latest('date')->take(5)->get();
        $recentExpenses = Expense::with('category')->where('user_id', $user->id)->latest('date')->take(5)->get();

        return view('dashboard', compact(
            'totalIncome', 'totalExpense', 'bankBalance', 'creditOutstanding',
            'loanPending', 'investmentValue', 'commissionReceived', 'cashbackReceived',
            'pendingReceivables', 'monthlyIncome', 'monthlyExpense', 'monthLabels',
            'categoryExpenses', 'reminders', 'recentIncomes', 'recentExpenses'
        ));
    }
}
