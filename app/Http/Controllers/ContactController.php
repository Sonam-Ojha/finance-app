<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Contact;
use App\Models\ContactTransaction;

class ContactController extends Controller
{
    public function index()
    {
        $contacts = Contact::with('transactions')->where('user_id', Auth::id())->orderBy('name')->paginate(15);
        return view('contact.index', compact('contacts'));
    }

    public function create()
    {
        return view('contact.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'nullable|string|max:15',
            'notes' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        Contact::create($data);
        return redirect()->route('contact.index')->with('success', 'Contact added!');
    }

    public function show(Contact $contact)
    {
        abort_if($contact->user_id !== Auth::id(), 403);
        $transactions = ContactTransaction::where('contact_id', $contact->id)->latest('date')->get();
        $balance = $transactions->where('type', 'lent')->sum('amount') - $transactions->where('type', 'borrowed')->sum('amount');
        return view('contact.show', compact('contact', 'transactions', 'balance'));
    }

    public function edit(Contact $contact)
    {
        abort_if($contact->user_id !== Auth::id(), 403);
        return view('contact.edit', compact('contact'));
    }

    public function update(Request $request, Contact $contact)
    {
        abort_if($contact->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'nullable|string|max:15',
            'notes' => 'nullable|string',
        ]);
        $contact->update($data);
        return redirect()->route('contact.index')->with('success', 'Contact updated!');
    }

    public function destroy(Contact $contact)
    {
        abort_if($contact->user_id !== Auth::id(), 403);
        $contact->delete();
        return redirect()->route('contact.index')->with('success', 'Contact deleted.');
    }

    public function storeTransaction(Request $request, Contact $contact)
    {
        abort_if($contact->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'type' => 'required|in:lent,borrowed',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ]);
        $data['user_id'] = Auth::id();
        $data['contact_id'] = $contact->id;
        ContactTransaction::create($data);
        return redirect()->route('contact.show', $contact)->with('success', 'Transaction added!');
    }
}
