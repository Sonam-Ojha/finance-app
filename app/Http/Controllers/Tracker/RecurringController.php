<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tracker\Concerns\ReadsAmounts;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Support\DisplayCurrency;
use App\Support\Labels;
use App\Support\RecurringRules;
use Illuminate\Http\Request;

/** Recurring: rent, EMIs, subscriptions and salary, added on their day by `recurring:post`. */
class RecurringController extends Controller
{
    use ReadsAmounts;

    public function index(Request $request)
    {
        $uid = $request->user()->id;

        return view('tracker.recurring', [
            'rules' => RecurringEntry::with('category')->where('user_id', $uid)
                ->orderByDesc('active')->orderBy('next_on')->get(),
            'money' => DisplayCurrency::for($request->user()),
        ]);
    }

    public function create(Request $request)
    {
        $rule = new RecurringEntry(['kind' => $request->query('kind') === 'income' ? 'income' : 'expense', 'frequency' => 'monthly']);
        $rule->next_on = Labels::now()->startOfDay();

        return $this->form($request, $rule);
    }

    public function edit(Request $request, RecurringEntry $recurring)
    {
        $this->authorizeOwner($request, $recurring);

        return $this->form($request, $recurring);
    }

    public function store(Request $request)
    {
        $rule = new RecurringEntry(['user_id' => $request->user()->id]);
        $this->save($request, $rule);

        return redirect()->route('recurring.index')->with('success', "Added: {$rule->describe()}. The first one goes in on " . $rule->next_on->format('D j M') . '.');
    }

    public function update(Request $request, RecurringEntry $recurring)
    {
        $this->authorizeOwner($request, $recurring);
        $this->save($request, $recurring);

        return redirect()->route('recurring.index')->with('success', 'Saved');
    }

    /** Pause, or resume from today on — the paused days are not added afterwards. */
    public function toggle(Request $request, RecurringEntry $recurring)
    {
        $this->authorizeOwner($request, $recurring);
        if ($recurring->active) {
            $recurring->update(['active' => false]);
        } else {
            $today = Labels::now()->toDateString();
            $next = $recurring->next_on;
            while ($next->toDateString() < $today) $next = $recurring->after($next);
            $recurring->update(['active' => true, 'next_on' => $next]);
        }

        return back()->with('success', $recurring->active ? 'Resumed. Next one on ' . $recurring->next_on->format('D j M') . '.' : 'Paused');
    }

    public function destroy(Request $request, RecurringEntry $recurring)
    {
        $this->authorizeOwner($request, $recurring);
        $recurring->delete();

        return redirect()->route('recurring.index')->with('success', 'Deleted. Entries it already added stay.');
    }

    private function save(Request $request, RecurringEntry $rule): void
    {
        $money = DisplayCurrency::for($request->user());
        RecurringRules::validate($request, $rule->exists ? $rule : null,
            ['amount' => ['required', $this->amountRule($money, $rule->amount_minor, 'Enter an amount greater than zero.')]],
            ['amount.required' => 'Enter an amount greater than zero.']);
        RecurringRules::fields($request, $rule);
        $rule->amount_minor = $this->amountValue($request->amount, $money, $rule->amount_minor);
        $rule->save();
    }

    private function form(Request $request, RecurringEntry $rule)
    {
        // A paused rule's day may have passed: offer its next day from today on.
        $today = Labels::now()->toDateString();
        if ($rule->exists) {
            while ($rule->next_on->toDateString() < $today) $rule->next_on = $rule->after($rule->next_on);
        }

        return view('tracker.recurring-form', [
            'rule'       => $rule,
            'money'      => DisplayCurrency::for($request->user()),
            'categories' => Category::where('user_id', $request->user()->id)->orderBy('sort_order')->orderBy('id')->get(),
            'today'      => Labels::now()->toDateString(),
        ]);
    }

    private function authorizeOwner(Request $request, RecurringEntry $rule): void
    {
        if ($rule->user_id !== $request->user()->id) abort(404);
    }
}
