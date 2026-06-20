<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Loan;
use App\Models\LoanPayment;

class LoanController extends Controller
{
    public function index()
    {
        $loans = Loan::where('user_id', Auth::id())->get();
        return view('loan.index', compact('loans'));
    }

    public function create()
    {
        return view('loan.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'loan_name' => 'required|string|max:100',
            'bank_or_person_name' => 'required|string|max:100',
            'total_amount' => 'required|numeric|min:0.01',
            'interest_rate' => 'nullable|numeric|min:0',
            'emi_amount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'pending_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        Loan::create($data);
        return redirect()->route('loan.index')->with('success', 'Loan added!');
    }

    public function show(Loan $loan)
    {
        abort_if($loan->user_id !== Auth::id(), 403);
        $payments = LoanPayment::where('loan_id', $loan->id)->latest('date')->get();
        return view('loan.show', compact('loan', 'payments'));
    }

    public function edit(Loan $loan)
    {
        abort_if($loan->user_id !== Auth::id(), 403);
        return view('loan.edit', compact('loan'));
    }

    public function update(Request $request, Loan $loan)
    {
        abort_if($loan->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'loan_name' => 'required|string|max:100',
            'bank_or_person_name' => 'required|string|max:100',
            'total_amount' => 'required|numeric|min:0.01',
            'interest_rate' => 'nullable|numeric|min:0',
            'emi_amount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'pending_amount' => 'required|numeric|min:0',
            'status' => 'required|in:active,closed',
            'notes' => 'nullable|string',
        ]);
        $loan->update($data);
        return redirect()->route('loan.index')->with('success', 'Loan updated!');
    }

    public function destroy(Loan $loan)
    {
        abort_if($loan->user_id !== Auth::id(), 403);
        $loan->delete();
        return redirect()->route('loan.index')->with('success', 'Loan deleted.');
    }

    public function storePayment(Request $request, Loan $loan)
    {
        abort_if($loan->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        $data['loan_id'] = $loan->id;
        LoanPayment::create($data);
        $loan->decrement('pending_amount', $data['amount']);
        if ($loan->fresh()->pending_amount <= 0) {
            $loan->update(['status' => 'closed', 'pending_amount' => 0]);
        }
        return redirect()->route('loan.show', $loan)->with('success', 'Payment recorded!');
    }
}
