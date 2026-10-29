<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tracker\Concerns\ReadsAmounts;
use App\Models\Group;
use App\Models\GroupExpense;
use App\Support\DisplayCurrency;
use App\Support\GroupLedger;
use App\Support\Groups;
use App\Support\Labels;
use Illuminate\Http\Request;

/**
 * A group expense on the website: add, edit, delete. Amounts are typed in
 * the user's currency, like every other form; split equally between the
 * people ticked, or by exact amounts that must add up to the total.
 */
class GroupExpenseController extends Controller
{
    use ReadsAmounts;

    public function create(Request $request, string $id)
    {
        return $this->form($request, Groups::find($request->user(), $id), null);
    }

    public function edit(Request $request, string $id, string $expenseId)
    {
        $group = Groups::find($request->user(), $id);

        return $this->form($request, $group, Groups::findExpense($group, $expenseId));
    }

    public function store(Request $request, string $id)
    {
        $user    = $request->user();
        $group   = Groups::find($user, $id);
        $money   = DisplayCurrency::for($user);
        $expense = $this->save($request, $group, $money, null);

        return redirect()->route('groups.show', $group)
            ->with('success', 'Expense of ' . $money->format($expense->amount_minor) . " added · {$expense->description}");
    }

    public function update(Request $request, string $id, string $expenseId)
    {
        $user  = $request->user();
        $group = Groups::find($user, $id);
        $this->save($request, $group, DisplayCurrency::for($user), Groups::findExpense($group, $expenseId));

        return redirect()->route('groups.show', $group)->with('success', 'Expense updated');
    }

    public function destroy(Request $request, string $id, string $expenseId)
    {
        $group = Groups::find($request->user(), $id);
        Groups::deleteExpense($group, Groups::findExpense($group, $expenseId));

        return redirect()->to(route('groups.show', $group) . '#expenses')->with('success', 'Expense deleted');
    }

    private function form(Request $request, Group $group, ?GroupExpense $expense)
    {
        $user  = $request->user();
        $money = DisplayCurrency::for($user);

        // The members, and on an edit anyone on the expense who has left since.
        $ids = $group->members->pluck('user_id')->all();
        if ($expense) $ids = array_values(array_unique(array_merge($ids, [$expense->paid_by], $expense->shares->pluck('user_id')->all())));
        $names  = Groups::names($group, $ids);
        $people = array_map(fn (int $id) => [
            'id'   => $id,
            'name' => $id === $user->id ? 'You' : $names[$id],
            'user' => $group->members->firstWhere('user_id', $id)?->user,
        ], $ids);

        return view('tracker.group-expense-form', [
            'money'   => $money,
            'group'   => $group,
            'expense' => $expense,
            'people'  => $people,
            'init'    => [
                'amount'       => old('amount', $expense ? $money->toInput($expense->amount_minor) : ''),
                'split'        => old('split', $expense->split ?? 'equal'),
                'participants' => array_map('strval', old('participants', $expense ? $expense->shares->pluck('user_id')->all() : $group->members->pluck('user_id')->all())),
                'shares'       => (object) old('shares', collect($ids)->mapWithKeys(fn (int $id) => [
                    $id => $expense && $expense->split === 'exact' && $expense->shareOf($id) ? $money->toInput($expense->shareOf($id)) : '',
                ])->all()),
            ],
            'paidBy'  => (int) old('paid_by', $expense->paid_by ?? $user->id),
            'today'   => Labels::now()->toDateString(),
        ]);
    }

    private function save(Request $request, Group $group, DisplayCurrency $money, ?GroupExpense $expense): GroupExpense
    {
        $shareRule = function (string $attribute, $value, \Closure $fail) use ($money) {
            $text = trim((string) $value);
            if ($text === '' || preg_match('/^0*\.?0*$/', $text)) return;
            if ($money->parse($text) === null) $fail('Enter each share as an amount, like 250 or 250.50.');
        };

        $request->validate(Groups::expenseRules() + [
            'amount'         => ['required', $this->amountRule($money, $expense?->amount_minor, 'Enter an amount greater than zero.')],
            'paid_by'        => 'required|integer',
            'participants'   => 'required_if:split,equal|array',
            'participants.*' => 'integer',
            'shares'         => 'nullable|array',
            'shares.*'       => ['nullable', $shareRule],
        ], Groups::EXPENSE_MESSAGES + [
            'amount.required'          => 'Enter an amount greater than zero.',
            'paid_by.*'                => 'Pick who paid — someone in this group.',
            'participants.required_if' => 'Pick at least one person to split it with.',
            'participants.array'       => 'Pick at least one person to split it with.',
            'participants.*'           => 'Everyone sharing the expense must be in this group.',
            'shares.array'             => 'Give each person their share.',
        ]);

        $amount = $this->amountValue($request->amount, $money, $expense?->amount_minor);
        $equal  = $request->split === 'equal';

        return Groups::saveExpense($group, $request->user(), [
            'description'  => $request->description,
            'amount_minor' => $amount,
            'paid_by'      => (int) $request->paid_by,
            'spent_on'     => $request->spent_on,
            'split'        => $request->split,
            'shares'       => $equal
                ? GroupLedger::equalShares($amount, (array) $request->participants)
                : $this->exactShares((array) $request->shares, $amount, $money, $expense),
        ], $expense, $equal ? 'participants' : 'shares');
    }

    /**
     * Typed shares as paise. An untouched share keeps its stored value. In
     * a currency other than rupees each share rounds back to paise on its
     * own, so shares that add up on screen can miss the total by a paisa or
     * so; the largest share takes up that difference.
     *
     * @return array<int, int>
     */
    private function exactShares(array $typed, int $amount, DisplayCurrency $money, ?GroupExpense $expense): array
    {
        $shares = [];
        foreach ($typed as $userId => $text) {
            if (! ctype_digit((string) $userId)) continue;
            $original = $expense?->split === 'exact' ? ($expense->shareOf((int) $userId) ?: null) : null;
            $value = $this->amountValue((string) $text, $money, $original) ?? 0;
            if ($value > 0) $shares[(int) $userId] = $value;
        }

        $drift = $amount - array_sum($shares);
        if ($drift !== 0 && $shares && $money->rate && $money->rate != 1.0 && abs($drift) <= count($shares)) {
            $largest = array_search(max($shares), $shares, true);
            $shares[$largest] += $drift;
        }

        return $shares;
    }
}
