<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(BankAccount::where('user_id', $request->user()->id)->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bank_name'              => 'required|string',
            'account_number_last4'   => 'nullable|string|max:4',
            'current_balance'        => 'required|numeric',
            'account_type'           => 'nullable|string',
            'ifsc_code'              => 'nullable|string',
            'notes'                  => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        return response()->json(BankAccount::create($data), 201);
    }

    public function show(Request $request, BankAccount $bank)
    {
        $this->authorizeOwner($bank, $request->user());
        $bank->load('transactions');
        return response()->json($bank);
    }

    public function update(Request $request, BankAccount $bank)
    {
        $this->authorizeOwner($bank, $request->user());
        $data = $request->validate([
            'bank_name'              => 'sometimes|string',
            'account_number_last4'   => 'nullable|string|max:4',
            'current_balance'        => 'sometimes|numeric',
            'account_type'           => 'nullable|string',
            'ifsc_code'              => 'nullable|string',
            'notes'                  => 'nullable|string',
        ]);
        $bank->update($data);
        return response()->json($bank);
    }

    public function destroy(Request $request, BankAccount $bank)
    {
        $this->authorizeOwner($bank, $request->user());
        $bank->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function storeTransaction(Request $request, BankAccount $bank)
    {
        $this->authorizeOwner($bank, $request->user());
        $data = $request->validate([
            'type'        => 'required|in:credit,debit',
            'amount'      => 'required|numeric|min:0',
            'date'        => 'required|date',
            'description' => 'nullable|string',
        ]);
        $transaction = $bank->transactions()->create($data);

        if ($data['type'] === 'credit') {
            $bank->increment('current_balance', $data['amount']);
        } else {
            $bank->decrement('current_balance', $data['amount']);
        }

        return response()->json($transaction, 201);
    }

    private function authorizeOwner(BankAccount $bank, $user)
    {
        if ($bank->user_id !== $user->id) abort(403);
    }
}
