<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\PaymentMode;
use App\Models\InvestmentCategory;
use App\Models\BankMaster;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $uid = $request->user()->id;
        return response()->json([
            'expense_categories'    => ExpenseCategory::where('user_id', $uid)->orderBy('group')->orderBy('name')->get(),
            'income_categories'     => IncomeCategory::where('user_id', $uid)->orderBy('name')->get(),
            'payment_modes'         => PaymentMode::where('user_id', $uid)->orderBy('name')->get(),
            'investment_categories' => InvestmentCategory::where('user_id', $uid)->orderBy('name')->get(),
            'bank_masters'          => BankMaster::where('user_id', $uid)->orderBy('name')->get(),
        ]);
    }

    public function storeExpenseCategory(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'group' => 'required|in:home,personal,business,other']);
        $cat = ExpenseCategory::create(['user_id' => $request->user()->id, 'name' => $data['name'], 'group' => $data['group']]);
        return response()->json($cat, 201);
    }

    public function destroyExpenseCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        abort_if($expenseCategory->user_id !== $request->user()->id, 403);
        $expenseCategory->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function storeIncomeCategory(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'type' => 'required|string|max:50']);
        $cat = IncomeCategory::create(['user_id' => $request->user()->id, 'name' => $data['name'], 'type' => $data['type']]);
        return response()->json($cat, 201);
    }

    public function destroyIncomeCategory(Request $request, IncomeCategory $incomeCategory)
    {
        abort_if($incomeCategory->user_id !== $request->user()->id, 403);
        $incomeCategory->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function storePaymentMode(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        $pm = PaymentMode::create(['user_id' => $request->user()->id, 'name' => $data['name']]);
        return response()->json($pm, 201);
    }

    public function destroyPaymentMode(Request $request, PaymentMode $paymentMode)
    {
        abort_if($paymentMode->user_id !== $request->user()->id, 403);
        if ($paymentMode->is_default) {
            return response()->json(['message' => 'Default payment modes cannot be deleted.'], 422);
        }
        $paymentMode->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function storeInvestmentCategory(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        $cat = InvestmentCategory::create(['user_id' => $request->user()->id, 'name' => $data['name']]);
        return response()->json($cat, 201);
    }

    public function destroyInvestmentCategory(Request $request, InvestmentCategory $investmentCategory)
    {
        abort_if($investmentCategory->user_id !== $request->user()->id, 403);
        if ($investmentCategory->is_default) {
            return response()->json(['message' => 'Default categories cannot be deleted.'], 422);
        }
        $investmentCategory->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function storeBankMaster(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'short_name' => 'nullable|string|max:20']);
        $bm = BankMaster::create(['user_id' => $request->user()->id, 'name' => $data['name'], 'short_name' => $data['short_name'] ?? null]);
        return response()->json($bm, 201);
    }

    public function destroyBankMaster(Request $request, BankMaster $bankMaster)
    {
        abort_if($bankMaster->user_id !== $request->user()->id, 403);
        if ($bankMaster->is_default) {
            return response()->json(['message' => 'Default banks cannot be deleted.'], 422);
        }
        $bankMaster->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
