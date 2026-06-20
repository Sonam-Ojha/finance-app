<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Income;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $query = Income::where('user_id', Auth::id())->latest('date');

        if ($request->type) $query->where('type', $request->type);
        if ($request->month) $query->whereMonth('date', $request->month);
        if ($request->year) $query->whereYear('date', $request->year);

        $incomes = $query->paginate(15);
        $total = $query->sum('amount');

        return view('income.index', compact('incomes', 'total'));
    }

    public function create()
    {
        return view('income.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:salary,lic_commission,business,received_from,other',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'nullable|string',
            'note' => 'nullable|string|max:500',
            'company_name' => 'nullable|string|max:255',
            'salary_month' => 'nullable|string|max:50',
            'client_name' => 'nullable|string|max:255',
            'policy_number' => 'nullable|string|max:100',
            'plan_name' => 'nullable|string|max:255',
            'commission_type' => 'nullable|string|max:100',
            'business_name' => 'nullable|string|max:255',
            'person_name' => 'nullable|string|max:255',
            'mobile_number' => 'nullable|string|max:15',
            'reason' => 'nullable|string|max:255',
            'category_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $data['user_id'] = Auth::id();
        Income::create($data);

        return redirect()->route('income.index')->with('success', 'Income added successfully!');
    }

    public function edit(Income $income)
    {
        abort_if($income->user_id !== Auth::id(), 403);
        return view('income.edit', compact('income'));
    }

    public function update(Request $request, Income $income)
    {
        abort_if($income->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'type' => 'required|in:salary,lic_commission,business,received_from,other',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'nullable|string',
            'note' => 'nullable|string|max:500',
            'company_name' => 'nullable|string|max:255',
            'salary_month' => 'nullable|string|max:50',
            'client_name' => 'nullable|string|max:255',
            'policy_number' => 'nullable|string|max:100',
            'plan_name' => 'nullable|string|max:255',
            'commission_type' => 'nullable|string|max:100',
            'business_name' => 'nullable|string|max:255',
            'person_name' => 'nullable|string|max:255',
            'mobile_number' => 'nullable|string|max:15',
            'reason' => 'nullable|string|max:255',
            'category_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $income->update($data);

        return redirect()->route('income.index')->with('success', 'Income updated successfully!');
    }

    public function destroy(Income $income)
    {
        abort_if($income->user_id !== Auth::id(), 403);
        $income->delete();
        return redirect()->route('income.index')->with('success', 'Income deleted.');
    }

    public function show(Income $income)
    {
        abort_if($income->user_id !== Auth::id(), 403);
        return view('income.show', compact('income'));
    }
}
