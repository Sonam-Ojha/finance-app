<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tracker\Concerns\ReadsAmounts;
use App\Models\Group;
use App\Support\DisplayCurrency;
use App\Support\GroupLedger;
use App\Support\Groups;
use App\Support\Labels;
use Illuminate\Http\Request;

/**
 * Groups on the website: the list, a group's page (balances, settling up,
 * expenses, members), and making, editing and deleting a group. Same rules
 * as the API (App\Support\Groups).
 */
class GroupController extends Controller
{
    use ReadsAmounts;

    private const EXPENSES_PER_PAGE = 20;

    public function index(Request $request)
    {
        $user   = $request->user();
        $groups = Groups::listFor($user);

        return view('tracker.groups', [
            'money'    => DisplayCurrency::for($user),
            'groups'   => $groups,
            'balances' => GroupLedger::balancesFor($groups->pluck('id')->all()),
        ]);
    }

    public function create()
    {
        return view('tracker.group-form', ['group' => null, 'canDelete' => false]);
    }

    public function store(Request $request)
    {
        $request->validate(Groups::GROUP_RULES, Groups::GROUP_MESSAGES);
        $group = Groups::create($request->user(), $request->name, $request->emoji);

        return redirect()->route('groups.show', $group)
            ->with('success', "{$group->label()} created. Add the people you share expenses with.");
    }

    public function show(Request $request, string $id)
    {
        $user     = $request->user();
        $group    = Groups::find($user, $id);
        $balances = GroupLedger::balances($group->id);
        $expenses = $group->expenses()->with('shares')->orderByDesc('spent_on')->orderByDesc('id')
            ->paginate(self::EXPENSES_PER_PAGE)->fragment('expenses');
        $settlements = $group->settlements()->orderByDesc('settled_on')->orderByDesc('id')->limit(5)->get();

        // Everyone on this page of expenses and payments, members or not.
        $ids = $settlements->pluck('from_user_id')->merge($settlements->pluck('to_user_id'))->all();
        foreach ($expenses->items() as $expense) {
            $ids = array_merge($ids, [$expense->paid_by], $expense->shares->pluck('user_id')->all());
        }

        return view('tracker.group', [
            'money'       => DisplayCurrency::for($user),
            'group'       => $group,
            'me'          => $user->id,
            'people'      => Groups::people($group, $balances),
            'suggestions' => Groups::suggestions($group, $balances),
            'expenses'    => $expenses,
            'settlements' => $settlements,
            'names'       => Groups::names($group, $ids),
            'canDelete'   => Groups::canDelete($group, $user),
            'today'       => Labels::now()->toDateString(),
        ]);
    }

    public function edit(Request $request, string $id)
    {
        $group = Groups::find($request->user(), $id);

        return view('tracker.group-form', ['group' => $group, 'canDelete' => Groups::canDelete($group, $request->user())]);
    }

    public function update(Request $request, string $id)
    {
        $group = Groups::find($request->user(), $id);
        $request->validate(Groups::GROUP_RULES, Groups::GROUP_MESSAGES);
        Groups::rename($group, $request->name, $request->emoji);

        return redirect()->route('groups.show', $group)->with('success', 'Group saved');
    }

    public function destroy(Request $request, string $id)
    {
        $group = Groups::find($request->user(), $id);
        if (! Groups::canDelete($group, $request->user())) {
            return back()->with('error', Groups::cannotDeleteMessage($group));
        }
        Groups::delete($group);

        return redirect()->route('groups.index')->with('success', "{$group->label()} deleted");
    }

    public function addMember(Request $request, string $id)
    {
        $group = Groups::find($request->user(), $id);
        $request->validate(['email' => 'required|string|email|max:255'], ['email.*' => 'Enter the email address of the person to add.']);
        $added = Groups::addMember($group, $request->user(), $request->email);

        return redirect()->to(route('groups.show', $group) . '#members')
            ->with('success', Groups::name($added) . ' added to the group');
    }

    public function removeMember(Request $request, string $id, string $userId)
    {
        $user  = $request->user();
        $group = Groups::find($user, $id);
        if (! ctype_digit($userId)) abort(404);
        $name = Groups::name($group->members->firstWhere('user_id', (int) $userId)?->user);

        if (Groups::removeMember($group, $user, (int) $userId)) {
            return redirect()->route('groups.index')->with('success', "You left {$group->label()}");
        }

        return redirect()->to(route('groups.show', $group) . '#members')->with('success', "{$name} removed from the group");
    }

    /** From a suggestion (amount_minor, exactly) or the "Record a payment" form (amount, typed). */
    public function settle(Request $request, string $id)
    {
        $user  = $request->user();
        $group = Groups::find($user, $id);
        $money = DisplayCurrency::for($user);
        $exact = $request->filled('amount_minor');
        $request->validate([
            'from_user_id' => 'required|integer',
            'to_user_id'   => 'required|integer',
            'amount_minor' => 'nullable|integer|min:1|max:99999999999999',
            'amount'       => $exact ? 'nullable' : ['required', $this->amountRule($money, null, 'Enter an amount greater than zero.')],
            'on'           => 'nullable|date_format:Y-m-d|before_or_equal:' . now()->addDay()->toDateString(),
        ], [
            'from_user_id.*' => 'Pick who paid and who was paid — two different people in this group.',
            'to_user_id.*'   => 'Pick who paid and who was paid — two different people in this group.',
            'amount_minor.*' => 'Enter an amount greater than zero.',
            'amount.required' => 'Enter an amount greater than zero.',
            'on.*'           => 'Pick a day that is not in the future.',
        ]);
        $amount = $exact ? (int) $request->amount_minor : $this->amountValue($request->amount, $money, null);

        $settlement = Groups::settle($group, $user, (int) $request->from_user_id, (int) $request->to_user_id, $amount,
            $request->on ?: Labels::now()->toDateString());
        $names = Groups::names($group, [$settlement->from_user_id, $settlement->to_user_id]);

        return redirect()->route('groups.show', $group)->with('success',
            "Payment recorded · {$names[$settlement->from_user_id]} paid {$names[$settlement->to_user_id]} " . $money->format($amount));
    }
}
