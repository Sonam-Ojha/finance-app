<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupExpense;
use App\Support\GroupLedger;
use App\Support\Groups;
use Illuminate\Http\Request;

/**
 * A group's expenses for the app. An equal split sends `participants`
 * (user ids); an exact one sends `shares` ([{user_id, amount_minor}]) that
 * add up to amount_minor.
 */
class GroupExpenseController extends Controller
{
    public function index(Request $request, string $id)
    {
        $user     = $request->user();
        $group    = Groups::find($user, $id);
        $expenses = $group->expenses()->with('shares')->orderByDesc('spent_on')->orderByDesc('id')->get();
        $names    = Groups::expenseNames($group, $expenses);

        return response()->json(['data' => $expenses->map(fn (GroupExpense $e) => Groups::expenseShape($e, $user, $names))->values()]);
    }

    public function store(Request $request, string $id)
    {
        $group   = Groups::find($request->user(), $id);
        $expense = $this->save($request, $group, null);

        return response()->json(['data' => Groups::expenseShape($expense, $request->user(), Groups::expenseNames($group, [$expense]))], 201);
    }

    public function update(Request $request, string $id, string $expenseId)
    {
        $group   = Groups::find($request->user(), $id);
        $expense = $this->save($request, $group, Groups::findExpense($group, $expenseId));

        return response()->json(['data' => Groups::expenseShape($expense, $request->user(), Groups::expenseNames($group, [$expense]))]);
    }

    public function destroy(Request $request, string $id, string $expenseId)
    {
        $group = Groups::find($request->user(), $id);
        Groups::deleteExpense($group, Groups::findExpense($group, $expenseId));

        return response()->noContent();
    }

    private function save(Request $request, Group $group, ?GroupExpense $expense): GroupExpense
    {
        $request->validate(Groups::expenseRules() + [
            'amount_minor'          => 'required|integer|min:1|max:99999999999999',
            'paid_by'               => 'required|integer',
            'participants'          => 'required_if:split,equal|array',
            'participants.*'        => 'integer|distinct',
            'shares'                => 'required_if:split,exact|array',
            'shares.*.user_id'      => 'required|integer|distinct',
            'shares.*.amount_minor' => 'required|integer|min:0|max:99999999999999',
        ], Groups::EXPENSE_MESSAGES + [
            'amount_minor.*'            => 'Enter an amount greater than zero.',
            'paid_by.*'                 => 'Pick who paid — someone in this group.',
            // The parent's own rules before the wildcard, which matches them too.
            'participants.required_if'  => 'Pick at least one person to split it with.',
            'participants.array'        => 'Pick at least one person to split it with.',
            'participants.*'            => 'Everyone sharing the expense must be in this group.',
            'shares.required_if'        => 'Give each person their share.',
            'shares.array'              => 'Give each person their share.',
            'shares.*.user_id.distinct' => 'Each person can have only one share.',
            'shares.*.user_id.*'        => 'Everyone sharing the expense must be in this group.',
            'shares.*.amount_minor.*'   => 'Each share must be an amount of zero or more.',
            'shares.*'                  => 'Give each person their share.',
        ]);

        $amount = (int) $request->amount_minor;
        $equal  = $request->split === 'equal';
        $shares = $equal
            ? GroupLedger::equalShares($amount, $request->participants)
            : collect($request->shares)->mapWithKeys(fn (array $s) => [(int) $s['user_id'] => (int) $s['amount_minor']])->all();

        return Groups::saveExpense($group, $request->user(), [
            'description'  => $request->description,
            'amount_minor' => $amount,
            'paid_by'      => (int) $request->paid_by,
            'spent_on'     => $request->spent_on,
            'split'        => $request->split,
            'shares'       => $shares,
        ], $expense, $equal ? 'participants' : 'shares');
    }
}
