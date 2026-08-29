<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Investment;
use App\Models\Loan;
use App\Models\Commission;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'type'  => 'required|in:income,expense,profit_loss,investment,loan,commission',
            'month' => 'nullable|integer|min:1|max:12',
            'year'  => 'required|integer|min:2000|max:2100',
        ]);

        $userId = $request->user()->id;
        $type   = $request->type;
        $month  = $request->month;
        $year   = $request->year;

        $data = match($type) {
            'income'      => $this->incomeReport($userId, $month, $year),
            'expense'     => $this->expenseReport($userId, $month, $year),
            'profit_loss' => $this->profitLossReport($userId, $month, $year),
            'investment'  => $this->investmentReport($userId),
            'loan'        => $this->loanReport($userId),
            'commission'  => $this->commissionReport($userId, $month, $year),
        };

        return response()->json(array_merge($data, ['type' => $type, 'month' => $month, 'year' => $year]));
    }

    private function incomeReport($userId, $month, $year): array
    {
        $query = Income::where('user_id', $userId)->whereYear('date', $year);
        if ($month) $query->whereMonth('date', $month);
        $records = $query->orderBy('date')->get();
        return ['records' => $records, 'total' => $records->sum('amount')];
    }

    private function expenseReport($userId, $month, $year): array
    {
        $query = Expense::with('category')->where('user_id', $userId)->whereYear('date', $year);
        if ($month) $query->whereMonth('date', $month);
        $records = $query->orderBy('date')->get();
        return ['records' => $records, 'total' => $records->sum('amount')];
    }

    private function profitLossReport($userId, $month, $year): array
    {
        $incomeQuery  = Income::where('user_id', $userId)->whereYear('date', $year);
        $expenseQuery = Expense::where('user_id', $userId)->whereYear('date', $year);
        if ($month) {
            $incomeQuery->whereMonth('date', $month);
            $expenseQuery->whereMonth('date', $month);
        }
        $totalIncome  = $incomeQuery->sum('amount');
        $totalExpense = $expenseQuery->sum('amount');
        return ['total_income' => $totalIncome, 'total_expense' => $totalExpense, 'net' => $totalIncome - $totalExpense];
    }

    private function investmentReport($userId): array
    {
        $records = Investment::where('user_id', $userId)->orderBy('date')->get();
        return ['records' => $records, 'total_invested' => $records->sum('amount_invested'), 'total_current_value' => $records->sum('current_value')];
    }

    private function loanReport($userId): array
    {
        $records = Loan::where('user_id', $userId)->get();
        return ['records' => $records, 'total_pending' => $records->sum('pending_amount')];
    }

    private function commissionReport($userId, $month, $year): array
    {
        $query = Commission::where('user_id', $userId)->whereYear('date', $year);
        if ($month) $query->whereMonth('date', $month);
        $records = $query->orderBy('date')->get();
        return ['records' => $records, 'total' => $records->sum('commission_amount')];
    }
}
