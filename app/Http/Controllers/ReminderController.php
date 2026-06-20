<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Reminder;
use Carbon\Carbon;

class ReminderController extends Controller
{
    public function index()
    {
        $upcoming = Reminder::where('user_id', Auth::id())
            ->where('is_done', false)
            ->where('due_date', '>=', Carbon::today())
            ->orderBy('due_date')->get();
        $past = Reminder::where('user_id', Auth::id())
            ->where(fn($q) => $q->where('is_done', true)->orWhere('due_date', '<', Carbon::today()))
            ->orderByDesc('due_date')->take(20)->get();
        return view('reminder.index', compact('upcoming', 'past'));
    }

    public function create()
    {
        return view('reminder.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:loan_emi,credit_card,pending_payment,investment_maturity,other',
            'title' => 'required|string|max:255',
            'due_date' => 'required|date',
            'amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        Reminder::create($data);
        return redirect()->route('reminder.index')->with('success', 'Reminder set!');
    }

    public function markDone(Reminder $reminder)
    {
        abort_if($reminder->user_id !== Auth::id(), 403);
        $reminder->update(['is_done' => true]);
        return redirect()->route('reminder.index')->with('success', 'Reminder marked as done!');
    }

    public function destroy(Reminder $reminder)
    {
        abort_if($reminder->user_id !== Auth::id(), 403);
        $reminder->delete();
        return redirect()->route('reminder.index')->with('success', 'Reminder deleted.');
    }

    public function edit(Reminder $reminder)
    {
        abort_if($reminder->user_id !== Auth::id(), 403);
        return view('reminder.edit', compact('reminder'));
    }

    public function update(Request $request, Reminder $reminder)
    {
        abort_if($reminder->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'type' => 'required|in:loan_emi,credit_card,pending_payment,investment_maturity,other',
            'title' => 'required|string|max:255',
            'due_date' => 'required|date',
            'amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        $reminder->update($data);
        return redirect()->route('reminder.index')->with('success', 'Reminder updated!');
    }

    public function show(Reminder $reminder)
    {
        abort_if($reminder->user_id !== Auth::id(), 403);
        return view('reminder.show', compact('reminder'));
    }
}
