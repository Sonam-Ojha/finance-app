<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Support\GroupLedger;
use App\Support\Groups;
use App\Support\Labels;
use Illuminate\Http\Request;

/**
 * Groups for the app: list, create, rename, delete, members and settling
 * up. A group someone is not in is a 404 to them. Shapes: App\Support\Groups.
 */
class GroupController extends Controller
{
    public function index(Request $request)
    {
        $user     = $request->user();
        $groups   = Groups::listFor($user);
        $balances = GroupLedger::balancesFor($groups->pluck('id')->all());

        return response()->json(['data' => $groups->map(fn (Group $g) => Groups::groupShape($g, $user, $balances[$g->id]))->values()]);
    }

    public function store(Request $request)
    {
        $request->validate(Groups::GROUP_RULES, Groups::GROUP_MESSAGES);
        $group = Groups::create($request->user(), $request->name, $request->emoji);

        return response()->json(['data' => Groups::groupShape($group, $request->user(), [])], 201);
    }

    public function show(Request $request, string $id)
    {
        $group = Groups::find($request->user(), $id);

        return response()->json(['data' => Groups::detailShape($group, $request->user())]);
    }

    public function update(Request $request, string $id)
    {
        $user  = $request->user();
        $group = Groups::find($user, $id);
        $request->validate(Groups::GROUP_RULES, Groups::GROUP_MESSAGES);
        Groups::rename($group, $request->name, $request->emoji);

        return response()->json(['data' => Groups::groupShape($group, $user, GroupLedger::balances($group->id))]);
    }

    public function destroy(Request $request, string $id)
    {
        $group = Groups::find($request->user(), $id);
        if (! Groups::canDelete($group, $request->user())) {
            return response()->json(['message' => Groups::cannotDeleteMessage($group)], 403);
        }
        Groups::delete($group);

        return response()->noContent();
    }

    public function addMember(Request $request, string $id)
    {
        $user  = $request->user();
        $group = Groups::find($user, $id);
        $request->validate(['email' => 'required|string|email|max:255'], ['email.*' => 'Enter the email address of the person to add.']);
        Groups::addMember($group, $user, $request->email);

        return response()->json(['data' => Groups::detailShape($group, $user)]);
    }

    /** Removing yourself is leaving: 204, as the group is no longer yours to see. */
    public function removeMember(Request $request, string $id, string $userId)
    {
        $user  = $request->user();
        $group = Groups::find($user, $id);
        if (! ctype_digit($userId)) abort(404, 'Not found.');

        if (Groups::removeMember($group, $user, (int) $userId)) return response()->noContent();

        return response()->json(['data' => Groups::detailShape($group, $user)]);
    }

    public function settle(Request $request, string $id)
    {
        $user  = $request->user();
        $group = Groups::find($user, $id);
        $request->validate([
            'from_user_id' => 'required|integer',
            'to_user_id'   => 'required|integer',
            'amount_minor' => 'required|integer|min:1|max:99999999999999',
            'on'           => 'nullable|date_format:Y-m-d|before_or_equal:' . now()->addDay()->toDateString(),
        ], [
            'from_user_id.*' => 'Pick who paid and who was paid — two different people in this group.',
            'to_user_id.*'   => 'Pick who paid and who was paid — two different people in this group.',
            'amount_minor.*' => 'Enter an amount greater than zero.',
            'on.*'           => 'Pick a day that is not in the future.',
        ]);

        Groups::settle($group, $user, (int) $request->from_user_id, (int) $request->to_user_id, (int) $request->amount_minor,
            $request->on ?: Labels::now()->toDateString());

        return response()->json(['data' => Groups::detailShape($group, $user)], 201);
    }
}
