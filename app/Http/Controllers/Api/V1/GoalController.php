<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GoalContribution;
use App\Models\SavingsGoal;
use App\Support\Goals;
use App\Support\Labels;
use Illuminate\Http\Request;

/**
 * Savings goals and the money put in and taken out of them. Goal and
 * Contribution objects are SavingsGoal::toApiShape() and
 * GoalContribution::toApiShape(); the rules live in App\Support\Goals.
 */
class GoalController extends Controller
{
    private const TARGET_RULE = 'required|integer|min:1|max:99999999999999';

    private const TARGET_MESSAGES = [
        'target_minor.*' => 'Enter a target greater than zero.',
    ];

    public function index(Request $request)
    {
        return response()->json([
            'data' => Goals::forUser($request->user()->id)->map(fn (SavingsGoal $goal) => $goal->toApiShape())->values(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(Goals::rules(null) + ['target_minor' => self::TARGET_RULE], Goals::MESSAGES + self::TARGET_MESSAGES);

        $goal = Goals::create($request->user(), $request->only(['name', 'emoji', 'color', 'target_minor', 'deadline']));

        return response()->json(['data' => $goal->toApiShape()], 201);
    }

    public function show(Request $request, SavingsGoal $goal)
    {
        $this->authorizeOwner($goal, $request->user());

        return response()->json(['data' => $goal->toApiShape()]);
    }

    /** Same body as store, plus an optional `archived` (bool). */
    public function update(Request $request, SavingsGoal $goal)
    {
        $this->authorizeOwner($goal, $request->user());
        $request->validate(Goals::rules($goal) + [
            'target_minor' => self::TARGET_RULE,
            'archived'     => 'sometimes|boolean',
        ], Goals::MESSAGES + self::TARGET_MESSAGES + [
            'archived.boolean' => 'Archived must be true or false.',
        ]);

        Goals::update(
            $goal,
            $request->only(['name', 'emoji', 'color', 'target_minor', 'deadline']),
            $request->has('archived') ? $request->boolean('archived') : null,
        );

        return response()->json(['data' => $goal->fresh()->toApiShape()]);
    }

    public function destroy(Request $request, SavingsGoal $goal)
    {
        $this->authorizeOwner($goal, $request->user());
        $goal->delete(); // its contributions go with it (foreign key)

        return response()->noContent();
    }

    /** Newest first. */
    public function contributions(Request $request, SavingsGoal $goal)
    {
        $this->authorizeOwner($goal, $request->user());

        return response()->json([
            'data' => $goal->contributions()->orderByDesc('on_date')->orderByDesc('id')->get()
                ->map(fn (GoalContribution $c) => $c->toApiShape())->values(),
        ]);
    }

    /** Body: { amount_minor (non-zero; negative withdraws), note?, on? }. Returns the goal. */
    public function contribute(Request $request, SavingsGoal $goal)
    {
        $this->authorizeOwner($goal, $request->user());
        $request->validate([
            'amount_minor' => 'required|integer|not_in:0|min:-99999999999999|max:99999999999999',
            'note'         => 'nullable|string|max:' . Goals::NOTE_MAX,
            'on'           => 'nullable|date_format:Y-m-d|before_or_equal:' . Labels::now()->addDay()->toDateString(),
        ], [
            'amount_minor.*' => 'Enter an amount to add, or a negative one to withdraw.',
            'note.max'       => 'Keep the note to 120 characters.',
            'note.string'    => 'Keep the note to 120 characters.',
            'on.*'           => 'Pick a day that is not in the future.',
        ]);

        Goals::contribute($goal, (int) $request->amount_minor, $request->note, $request->input('on') ?: Labels::now()->toDateString());

        return response()->json(['data' => $goal->fresh()->toApiShape()]);
    }

    private function authorizeOwner(SavingsGoal $goal, $user): void
    {
        if ($goal->user_id !== $user->id) abort(404, 'Not found.');
    }
}
