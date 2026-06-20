<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\BadDebt;

class BadDebtController extends Controller
{
    public function index()
    {
        $debts = BadDebt::where('user_id', Auth::id())->latest('date_given')->paginate(15);
        $totalPending = BadDebt::where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'partial_received'])
            ->selectRaw('SUM(amount - received_amount) as total')->value('total') ?? 0;
        return view('bad-debt.index', compact('debts', 'totalPending'));
    }

    public function create()
    {
        return view('bad-debt.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'person_name' => 'required|string|max:255',
            'mobile_number' => 'nullable|string|max:15',
            'amount' => 'required|numeric|min:0.01',
            'date_given' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'expected_return_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        BadDebt::create($data);
        return redirect()->route('bad-debt.index')->with('success', 'Pending money record added!');
    }

    public function edit(BadDebt $badDebt)
    {
        abort_if($badDebt->user_id !== Auth::id(), 403);
        return view('bad-debt.edit', compact('badDebt'));
    }

    public function update(Request $request, BadDebt $badDebt)
    {
        abort_if($badDebt->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'person_name' => 'required|string|max:255',
            'mobile_number' => 'nullable|string|max:15',
            'amount' => 'required|numeric|min:0.01',
            'date_given' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'expected_return_date' => 'nullable|date',
            'status' => 'required|in:pending,received,partial_received',
            'received_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        $badDebt->update($data);
        return redirect()->route('bad-debt.index')->with('success', 'Record updated!');
    }

    public function destroy(BadDebt $badDebt)
    {
        abort_if($badDebt->user_id !== Auth::id(), 403);
        $badDebt->delete();
        return redirect()->route('bad-debt.index')->with('success', 'Record deleted.');
    }

    public function show(BadDebt $badDebt)
    {
        abort_if($badDebt->user_id !== Auth::id(), 403);
        return view('bad-debt.show', compact('badDebt'));
    }
}
