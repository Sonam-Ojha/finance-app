<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::with('category')
            ->where('user_id', $request->user()->id)
            ->when($request->category_id, fn($q) => $q->where('expense_category_id', $request->category_id))
            ->when($request->month, fn($q) => $q->whereMonth('date', $request->month))
            ->when($request->year, fn($q) => $q->whereYear('date', $request->year))
            ->latest('date')->paginate(20);
        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'date'                => 'required|date',
            'amount'              => 'required|numeric|min:0',
            'payment_mode'        => 'nullable|string',
            'description'         => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        $expense = Expense::create($data);
        return response()->json($expense->load('category'), 201);
    }

    public function show(Request $request, Expense $expense)
    {
        $this->authorizeOwner($expense, $request->user());
        return response()->json($expense->load('category'));
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizeOwner($expense, $request->user());
        $data = $request->validate([
            'expense_category_id' => 'sometimes|exists:expense_categories,id',
            'date'                => 'sometimes|date',
            'amount'              => 'sometimes|numeric|min:0',
            'payment_mode'        => 'nullable|string',
            'description'         => 'nullable|string',
        ]);
        $expense->update($data);
        return response()->json($expense->load('category'));
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->authorizeOwner($expense, $request->user());
        $expense->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    private function authorizeOwner(Expense $expense, $user)
    {
        if ($expense->user_id !== $user->id) abort(403);
    }
}
