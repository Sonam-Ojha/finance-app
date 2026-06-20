<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CreditCard;
use App\Models\CreditCardTransaction;

class CreditCardController extends Controller
{
    public function index()
    {
        $cards = CreditCard::where('user_id', Auth::id())->get();
        return view('credit-card.index', compact('cards'));
    }

    public function create()
    {
        return view('credit-card.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'card_name' => 'required|string|max:100',
            'bank_name' => 'required|string|max:100',
            'credit_limit' => 'required|numeric|min:0',
            'outstanding_amount' => 'required|numeric|min:0',
            'due_date_day' => 'nullable|integer|min:1|max:31',
            'notes' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        CreditCard::create($data);
        return redirect()->route('credit-card.index')->with('success', 'Credit card added!');
    }

    public function show(CreditCard $creditCard)
    {
        abort_if($creditCard->user_id !== Auth::id(), 403);
        $transactions = CreditCardTransaction::where('credit_card_id', $creditCard->id)->latest('date')->paginate(20);
        return view('credit-card.show', compact('creditCard', 'transactions'));
    }

    public function edit(CreditCard $creditCard)
    {
        abort_if($creditCard->user_id !== Auth::id(), 403);
        return view('credit-card.edit', compact('creditCard'));
    }

    public function update(Request $request, CreditCard $creditCard)
    {
        abort_if($creditCard->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'card_name' => 'required|string|max:100',
            'bank_name' => 'required|string|max:100',
            'credit_limit' => 'required|numeric|min:0',
            'outstanding_amount' => 'required|numeric|min:0',
            'due_date_day' => 'nullable|integer|min:1|max:31',
            'notes' => 'nullable|string',
        ]);
        $creditCard->update($data);
        return redirect()->route('credit-card.index')->with('success', 'Credit card updated!');
    }

    public function destroy(CreditCard $creditCard)
    {
        abort_if($creditCard->user_id !== Auth::id(), 403);
        $creditCard->delete();
        return redirect()->route('credit-card.index')->with('success', 'Credit card deleted.');
    }

    public function storeTransaction(Request $request, CreditCard $creditCard)
    {
        abort_if($creditCard->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'type' => 'required|in:spend,payment',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        $data['credit_card_id'] = $creditCard->id;
        CreditCardTransaction::create($data);

        if ($data['type'] === 'spend') {
            $creditCard->increment('outstanding_amount', $data['amount']);
        } else {
            $creditCard->decrement('outstanding_amount', $data['amount']);
        }

        return redirect()->route('credit-card.show', $creditCard)->with('success', 'Transaction added!');
    }
}
