<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Contact::with('transactions')->where('user_id', $request->user()->id)->get()
                ->map(fn($c) => array_merge($c->toArray(), ['balance' => $c->balance]))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string',
            'mobile'=> 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        return response()->json(Contact::create($data), 201);
    }

    public function show(Request $request, Contact $contact)
    {
        $this->authorizeOwner($contact, $request->user());
        $contact->load('transactions');
        return response()->json(array_merge($contact->toArray(), ['balance' => $contact->balance]));
    }

    public function update(Request $request, Contact $contact)
    {
        $this->authorizeOwner($contact, $request->user());
        $data = $request->validate([
            'name'  => 'sometimes|string',
            'mobile'=> 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        $contact->update($data);
        return response()->json($contact);
    }

    public function destroy(Request $request, Contact $contact)
    {
        $this->authorizeOwner($contact, $request->user());
        $contact->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function storeTransaction(Request $request, Contact $contact)
    {
        $this->authorizeOwner($contact, $request->user());
        $data = $request->validate([
            'type'   => 'required|in:lent,borrowed',
            'date'   => 'required|date',
            'amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string',
            'status' => 'nullable|in:pending,settled',
        ]);
        $data['user_id']    = $request->user()->id;
        $data['contact_id'] = $contact->id;
        $transaction = $contact->transactions()->create($data);
        return response()->json($transaction, 201);
    }

    private function authorizeOwner(Contact $contact, $user)
    {
        if ($contact->user_id !== $user->id) abort(403);
    }
}
