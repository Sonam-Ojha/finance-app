<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Investment;
use App\Models\Loan;
use App\Models\Commission;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index()
    {
        return view('report.index');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'type' => 'required|in:income,expense,profit_loss,investment,loan,commission',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $type = $request->type;
        $month = $request->month;
        $year = $request->year;
        $userId = Auth::id();

        $data = match($type) {
            'income' => $this->incomeReport($userId, $month, $year),
            'expense' => $this->expenseReport($userId, $month, $year),
            'profit_loss' => $this->profitLossReport($userId, $month, $year),
            'investment' => $this->investmentReport($userId),
            'loan' => $this->loanReport($userId),
            'commission' => $this->commissionReport($userId, $month, $year),
        };

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('report.pdf', array_merge($data, ['type' => $type, 'month' => $month, 'year' => $year]));
            return $pdf->download("finance-report-{$type}-{$year}.pdf");
        }

        return view('report.show', array_merge($data, ['type' => $type, 'month' => $month, 'year' => $year]));
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
        $incomeQuery = Income::where('user_id', $userId)->whereYear('date', $year);
        $expenseQuery = Expense::where('user_id', $userId)->whereYear('date', $year);
        if ($month) {
            $incomeQuery->whereMonth('date', $month);
            $expenseQuery->whereMonth('date', $month);
        }
        $totalIncome = $incomeQuery->sum('amount');
        $totalExpense = $expenseQuery->sum('amount');
        return ['totalIncome' => $totalIncome, 'totalExpense' => $totalExpense, 'net' => $totalIncome - $totalExpense, 'records' => collect()];
    }

    private function investmentReport($userId): array
    {
        $records = Investment::where('user_id', $userId)->orderBy('date')->get();
        return ['records' => $records, 'total' => $records->sum('amount_invested'), 'currentTotal' => $records->sum('current_value')];
    }

    private function loanReport($userId): array
    {
        $records = Loan::where('user_id', $userId)->get();
        return ['records' => $records, 'totalPending' => $records->sum('pending_amount')];
    }

    private function commissionReport($userId, $month, $year): array
    {
        $query = Commission::where('user_id', $userId)->whereYear('date', $year);
        if ($month) $query->whereMonth('date', $month);
        $records = $query->orderBy('date')->get();
        return ['records' => $records, 'total' => $records->sum('commission_amount')];
    }
}
