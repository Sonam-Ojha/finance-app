<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tracker\Concerns\ReadsAmounts;
use App\Models\SavingsGoal;
use App\Support\DisplayCurrency;
use App\Support\Goals;
use App\Support\Labels;
use Illuminate\Http\Request;

/** Savings goals: the cards, the goal form, a goal's history, and money in and out. Same rules as the API (App\Support\Goals). */
class GoalController extends Controller
{
    use ReadsAmounts;

    public function index(Request $request)
    {
        $goals = Goals::forUser($request->user()->id);

        return view('tracker.goals', [
            'money'    => DisplayCurrency::for($request->user()),
            'active'   => $goals->reject(fn (SavingsGoal $g) => $g->isArchived())->values(),
            'archived' => $goals->filter(fn (SavingsGoal $g) => $g->isArchived())->values(),
        ]);
    }

    public function show(Request $request, SavingsGoal $goal)
    {
        $this->authorizeOwner($request, $goal);

        return view('tracker.goal', [
            'money'   => DisplayCurrency::for($request->user()),
            'goal'    => $goal,
            'history' => $goal->contributions()->orderByDesc('on_date')->orderByDesc('id')->paginate(30),
        ]);
    }

    public function create(Request $request)
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, SavingsGoal $goal)
    {
        $this->authorizeOwner($request, $goal);
        return $this->form($request, $goal);
    }

    public function store(Request $request)
    {
        $goal = Goals::create($request->user(), $this->validated($request, null));

        return redirect()->route('goals.index')->with('success', "Goal “{$goal->name}” created");
    }

    public function update(Request $request, SavingsGoal $goal)
    {
        $this->authorizeOwner($request, $goal);
        Goals::update($goal, $this->validated($request, $goal));

        return redirect()->route('goals.index')->with('success', "Goal “{$goal->name}” updated");
    }

    /** Archives an active goal, or brings back an archived one. */
    public function archive(Request $request, SavingsGoal $goal)
    {
        $this->authorizeOwner($request, $goal);
        $archive = ! $goal->isArchived();
        $goal->update(['archived_at' => $archive ? now() : null]);

        return redirect()->route('goals.index')
            ->with('success', $archive ? "Goal “{$goal->name}” archived" : "Goal “{$goal->name}” is active again");
    }

    public function destroy(Request $request, SavingsGoal $goal)
    {
        $this->authorizeOwner($request, $goal);
        $goal->delete();

        return redirect()->route('goals.index')->with('success', "Goal “{$goal->name}” deleted");
    }

    /** Add money or withdraw it: `direction` is add or withdraw; the amount is typed in the user's currency. */
    public function contribute(Request $request, SavingsGoal $goal)
    {
        $this->authorizeOwner($request, $goal);
        $money = DisplayCurrency::for($request->user());
        $request->validate([
            'direction' => 'required|in:add,withdraw',
            'amount'    => ['required', $this->amountRule($money, null, 'Enter an amount greater than zero.')],
            'note'      => 'nullable|string|max:' . Goals::NOTE_MAX,
            'on'        => 'nullable|date_format:Y-m-d|before_or_equal:' . Labels::now()->addDay()->toDateString(),
        ], [
            'direction.*'     => 'Pick Add money or Withdraw.',
            'amount.required' => 'Enter an amount greater than zero.',
            'note.max'        => 'Keep the note to 120 characters.',
            'on.*'            => 'Pick a day that is not in the future.',
        ]);

        $amount   = $this->amountValue($request->amount, $money, null);
        $withdraw = $request->direction === 'withdraw';
        $wasReached = $goal->reached_at !== null;
        Goals::contribute($goal, $withdraw ? -$amount : $amount, $request->note, $request->input('on') ?: Labels::now()->toDateString(), 'amount');

        $message = $withdraw
            ? $money->format($amount) . " withdrawn from {$goal->name}"
            : $money->format($amount) . " added to {$goal->name}";
        if (! $wasReached && $goal->reached_at !== null) $message = "🎉 You reached your goal “{$goal->name}”!";

        return redirect($this->back($request, route('goals.index')))->with('success', $message);
    }

    private function form(Request $request, ?SavingsGoal $goal)
    {
        return view('tracker.goal-form', [
            'money'  => DisplayCurrency::for($request->user()),
            'goal'   => $goal,
            'emojis' => Goals::EMOJIS,
            'colors' => Goals::COLORS,
        ]);
    }

    private function validated(Request $request, ?SavingsGoal $goal): array
    {
        $money = DisplayCurrency::for($request->user());
        $request->validate(Goals::rules($goal) + [
            'target' => ['required', $this->amountRule($money, $goal?->target_minor, 'Enter a target greater than zero.')],
        ], Goals::MESSAGES + [
            'target.required' => 'Enter a target greater than zero.',
        ]);

        return [
            'name'         => $request->name,
            'emoji'        => $request->emoji,
            'color'        => $request->color,
            'target_minor' => $this->amountValue($request->target, $money, $goal?->target_minor),
            'deadline'     => $request->deadline,
        ];
    }

    private function authorizeOwner(Request $request, SavingsGoal $goal): void
    {
        if ($goal->user_id !== $request->user()->id) abort(404);
    }
}
