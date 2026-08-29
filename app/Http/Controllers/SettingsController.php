<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\PaymentMode;
use App\Models\InvestmentCategory;
use App\Models\BankMaster;

class SettingsController extends Controller
{
    public function index()
    {
        $uid = Auth::id();
        return view('settings.index', [
            'expenseCategories'    => ExpenseCategory::where('user_id', $uid)->orderBy('group')->orderBy('name')->get(),
            'incomeCategories'     => IncomeCategory::where('user_id', $uid)->orderBy('name')->get(),
            'paymentModes'         => PaymentMode::where('user_id', $uid)->orderBy('name')->get(),
            'investmentCategories' => InvestmentCategory::where('user_id', $uid)->orderBy('name')->get(),
            'bankMasters'          => BankMaster::where('user_id', $uid)->orderBy('name')->get(),
        ]);
    }

    // ── Expense Categories ──────────────────────────────────
    public function storeExpenseCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100', 'group' => 'required|in:home,personal,business,other']);
        ExpenseCategory::create(['user_id' => Auth::id(), 'name' => $request->name, 'group' => $request->group]);
        return back()->with('success', 'Expense category added.')->withFragment('expense-cat');
    }

    public function destroyExpenseCategory(ExpenseCategory $expenseCategory)
    {
        abort_if($expenseCategory->user_id !== Auth::id(), 403);
        $expenseCategory->delete();
        return back()->with('success', 'Category deleted.')->withFragment('expense-cat');
    }

    // ── Income Categories ───────────────────────────────────
    public function storeIncomeCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100', 'type' => 'required|string|max:50']);
        IncomeCategory::create(['user_id' => Auth::id(), 'name' => $request->name, 'type' => $request->type]);
        return back()->with('success', 'Income category added.')->withFragment('income-cat');
    }

    public function destroyIncomeCategory(IncomeCategory $incomeCategory)
    {
        abort_if($incomeCategory->user_id !== Auth::id(), 403);
        $incomeCategory->delete();
        return back()->with('success', 'Category deleted.')->withFragment('income-cat');
    }

    // ── Payment Modes ───────────────────────────────────────
    public function storePaymentMode(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);
        PaymentMode::create(['user_id' => Auth::id(), 'name' => $request->name]);
        return back()->with('success', 'Payment mode added.')->withFragment('payment-modes');
    }

    public function destroyPaymentMode(PaymentMode $paymentMode)
    {
        abort_if($paymentMode->user_id !== Auth::id(), 403);
        if ($paymentMode->is_default) {
            return back()->with('error', 'Default payment modes cannot be deleted.');
        }
        $paymentMode->delete();
        return back()->with('success', 'Payment mode deleted.')->withFragment('payment-modes');
    }

    // ── Investment Categories ────────────────────────────────
    public function storeInvestmentCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);
        InvestmentCategory::create(['user_id' => Auth::id(), 'name' => $request->name]);
        return back()->with('success', 'Investment category added.')->withFragment('invest-cat');
    }

    public function destroyInvestmentCategory(InvestmentCategory $investmentCategory)
    {
        abort_if($investmentCategory->user_id !== Auth::id(), 403);
        if ($investmentCategory->is_default) {
            return back()->with('error', 'Default categories cannot be deleted.');
        }
        $investmentCategory->delete();
        return back()->with('success', 'Category deleted.')->withFragment('invest-cat');
    }

    // ── Bank Masters ─────────────────────────────────────────
    public function storeBankMaster(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:150',
            'short_name' => 'nullable|string|max:20',
        ]);
        BankMaster::create([
            'user_id'    => Auth::id(),
            'name'       => $request->name,
            'short_name' => $request->short_name,
        ]);
        return back()->with('success', 'Bank added.')->withFragment('bank-masters');
    }

    public function destroyBankMaster(BankMaster $bankMaster)
    {
        abort_if($bankMaster->user_id !== Auth::id(), 403);
        if ($bankMaster->is_default) {
            return back()->with('error', 'Default banks cannot be deleted.');
        }
        $bankMaster->delete();
        return back()->with('success', 'Bank deleted.')->withFragment('bank-masters');
    }
}
