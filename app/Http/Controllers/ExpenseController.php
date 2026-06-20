<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Expense;
use App\Models\ExpenseCategory;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with('category')->where('user_id', Auth::id())->latest('date');

        if ($request->category_id) $query->where('expense_category_id', $request->category_id);
        if ($request->month) $query->whereMonth('date', $request->month);
        if ($request->year) $query->whereYear('date', $request->year);

        $expenses = $query->paginate(15);
        $total = $query->sum('amount');
        $categories = ExpenseCategory::where('user_id', Auth::id())->orderBy('name')->get();

        return view('expense.index', compact('expenses', 'total', 'categories'));
    }

    public function create()
    {
        $categories = ExpenseCategory::where('user_id', Auth::id())->orderBy('group')->orderBy('name')->get();
        return view('expense.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'nullable|string',
            'description' => 'nullable|string',
            'receipt_photo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('receipt_photo')) {
            $data['receipt_photo'] = $request->file('receipt_photo')->store('receipts', 'public');
        }

        $data['user_id'] = Auth::id();
        Expense::create($data);

        return redirect()->route('expense.index')->with('success', 'Expense added successfully!');
    }

    public function edit(Expense $expense)
    {
        abort_if($expense->user_id !== Auth::id(), 403);
        $categories = ExpenseCategory::where('user_id', Auth::id())->orderBy('group')->orderBy('name')->get();
        return view('expense.edit', compact('expense', 'categories'));
    }

    public function update(Request $request, Expense $expense)
    {
        abort_if($expense->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'nullable|string',
            'description' => 'nullable|string',
            'receipt_photo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('receipt_photo')) {
            $data['receipt_photo'] = $request->file('receipt_photo')->store('receipts', 'public');
        }

        $expense->update($data);
        return redirect()->route('expense.index')->with('success', 'Expense updated successfully!');
    }

    public function destroy(Expense $expense)
    {
        abort_if($expense->user_id !== Auth::id(), 403);
        $expense->delete();
        return redirect()->route('expense.index')->with('success', 'Expense deleted.');
    }

    public function show(Expense $expense)
    {
        abort_if($expense->user_id !== Auth::id(), 403);
        return view('expense.show', compact('expense'));
    }

    public function categories()
    {
        $categories = ExpenseCategory::where('user_id', Auth::id())->orderBy('group')->orderBy('name')->get();
        return view('expense.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'group' => 'required|in:home,personal,business,other',
        ]);
        $data['user_id'] = Auth::id();
        ExpenseCategory::create($data);
        return redirect()->route('expense.categories')->with('success', 'Category created!');
    }

    public function destroyCategory(ExpenseCategory $expenseCategory)
    {
        abort_if($expenseCategory->user_id !== Auth::id(), 403);
        $expenseCategory->delete();
        return redirect()->route('expense.categories')->with('success', 'Category deleted.');
    }
}
