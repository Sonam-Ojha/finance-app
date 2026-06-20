<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\BankAccount;
use App\Models\BankTransaction;

class BankAccountController extends Controller
{
    public function index()
    {
        $accounts = BankAccount::where('user_id', Auth::id())->get();
        return view('bank.index', compact('accounts'));
    }

    public function create()
    {
        return view('bank.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number_last4' => 'nullable|digits:4',
            'current_balance' => 'required|numeric',
            'account_type' => 'required|in:savings,current,salary',
            'ifsc_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        BankAccount::create($data);
        return redirect()->route('bank.index')->with('success', 'Bank account added!');
    }

    public function show(BankAccount $bank)
    {
        abort_if($bank->user_id !== Auth::id(), 403);
        $transactions = BankTransaction::where('bank_account_id', $bank->id)->latest('date')->paginate(20);
        return view('bank.show', compact('bank', 'transactions'));
    }

    public function edit(BankAccount $bank)
    {
        abort_if($bank->user_id !== Auth::id(), 403);
        return view('bank.edit', compact('bank'));
    }

    public function update(Request $request, BankAccount $bank)
    {
        abort_if($bank->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number_last4' => 'nullable|digits:4',
            'current_balance' => 'required|numeric',
            'account_type' => 'required|in:savings,current,salary',
            'ifsc_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);
        $bank->update($data);
        return redirect()->route('bank.index')->with('success', 'Bank account updated!');
    }

    public function destroy(BankAccount $bank)
    {
        abort_if($bank->user_id !== Auth::id(), 403);
        $bank->delete();
        return redirect()->route('bank.index')->with('success', 'Bank account deleted.');
    }

    public function storeTransaction(Request $request, BankAccount $bank)
    {
        abort_if($bank->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'type' => 'required|in:deposit,withdrawal',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        $data['bank_account_id'] = $bank->id;
        BankTransaction::create($data);

        if ($data['type'] === 'deposit') {
            $bank->increment('current_balance', $data['amount']);
        } else {
            $bank->decrement('current_balance', $data['amount']);
        }

        return redirect()->route('bank.show', $bank)->with('success', 'Transaction added!');
    }
}
