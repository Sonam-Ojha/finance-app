<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditCard;
use Illuminate\Http\Request;

class CreditCardController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(CreditCard::where('user_id', $request->user()->id)->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'card_name'          => 'required|string',
            'bank_name'          => 'required|string',
            'credit_limit'       => 'required|numeric|min:0',
            'outstanding_amount' => 'nullable|numeric|min:0',
            'due_date_day'       => 'nullable|integer|min:1|max:31',
            'notes'              => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        return response()->json(CreditCard::create($data), 201);
    }

    public function show(Request $request, CreditCard $creditCard)
    {
        $this->authorizeOwner($creditCard, $request->user());
        return response()->json($creditCard->load('transactions'));
    }

    public function update(Request $request, CreditCard $creditCard)
    {
        $this->authorizeOwner($creditCard, $request->user());
        $data = $request->validate([
            'card_name'          => 'sometimes|string',
            'bank_name'          => 'sometimes|string',
            'credit_limit'       => 'sometimes|numeric|min:0',
            'outstanding_amount' => 'nullable|numeric|min:0',
            'due_date_day'       => 'nullable|integer|min:1|max:31',
            'notes'              => 'nullable|string',
        ]);
        $creditCard->update($data);
        return response()->json($creditCard);
    }

    public function destroy(Request $request, CreditCard $creditCard)
    {
        $this->authorizeOwner($creditCard, $request->user());
        $creditCard->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function storeTransaction(Request $request, CreditCard $creditCard)
    {
        $this->authorizeOwner($creditCard, $request->user());
        $data = $request->validate([
            'type'        => 'required|in:purchase,payment',
            'amount'      => 'required|numeric|min:0',
            'date'        => 'required|date',
            'description' => 'nullable|string',
        ]);
        $transaction = $creditCard->transactions()->create($data);

        if ($data['type'] === 'purchase') {
            $creditCard->increment('outstanding_amount', $data['amount']);
        } else {
            $creditCard->decrement('outstanding_amount', $data['amount']);
        }

        return response()->json($transaction, 201);
    }

    private function authorizeOwner(CreditCard $creditCard, $user)
    {
        if ($creditCard->user_id !== $user->id) abort(403);
    }
}
