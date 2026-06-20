<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Investment;

class InvestmentController extends Controller
{
    public function index()
    {
        $investments = Investment::where('user_id', Auth::id())->latest('date')->paginate(15);
        $totalInvested = Investment::where('user_id', Auth::id())->sum('amount_invested');
        $totalCurrent = Investment::where('user_id', Auth::id())->sum('current_value');
        return view('investment.index', compact('investments', 'totalInvested', 'totalCurrent'));
    }

    public function create()
    {
        return view('investment.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'investment_name' => 'required|string|max:255',
            'category' => 'required|in:lic,mutual_fund,stocks,fd,gold,property,other',
            'date' => 'required|date',
            'amount_invested' => 'required|numeric|min:0.01',
            'current_value' => 'nullable|numeric|min:0',
            'maturity_date' => 'nullable|date',
            'returns' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        Investment::create($data);
        return redirect()->route('investment.index')->with('success', 'Investment added!');
    }

    public function edit(Investment $investment)
    {
        abort_if($investment->user_id !== Auth::id(), 403);
        return view('investment.edit', compact('investment'));
    }

    public function update(Request $request, Investment $investment)
    {
        abort_if($investment->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'investment_name' => 'required|string|max:255',
            'category' => 'required|in:lic,mutual_fund,stocks,fd,gold,property,other',
            'date' => 'required|date',
            'amount_invested' => 'required|numeric|min:0.01',
            'current_value' => 'nullable|numeric|min:0',
            'maturity_date' => 'nullable|date',
            'returns' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);
        $investment->update($data);
        return redirect()->route('investment.index')->with('success', 'Investment updated!');
    }

    public function destroy(Investment $investment)
    {
        abort_if($investment->user_id !== Auth::id(), 403);
        $investment->delete();
        return redirect()->route('investment.index')->with('success', 'Investment deleted.');
    }

    public function show(Investment $investment)
    {
        abort_if($investment->user_id !== Auth::id(), 403);
        return view('investment.show', compact('investment'));
    }
}
