<?php

namespace App\Support;

use App\Jobs\SendPush;
use App\Models\DeviceToken;
use App\Models\Group;
use App\Models\GroupExpense;
use App\Models\GroupMember;
use App\Models\GroupSettlement;
use App\Models\User;
use App\Notifications\AddedToGroup;
use App\Notifications\GroupActivity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Groups' rules, shared by the API and the website: who is in a group, what
 * an expense or a settlement may be, the pushes they send, and the shapes
 * the API returns. The maths is App\Support\GroupLedger.
 *
 * Only members see a group (everyone else gets a 404). Any member can add
 * people, expenses and settlements; nobody leaves, or is removed, while
 * owing or being owed; the creator deletes the group.
 */
class Groups
{
    public const MAX_MEMBERS = 50;
    public const RECENT_SETTLEMENTS = 20;

    /** The emoji the website offers; the API takes any. */
    public const EMOJIS = ['👥', '🏖️', '✈️', '🏠', '🍽️', '🎉', '🚗', '🏕️', '🛒', '💼', '🎓', '⚽'];

    public const NO_ACCOUNT = 'No Expense Tracker account uses that email — ask them to sign up first.';
    public const FORMER_MEMBER = 'Former member';

    /** Validation for a group's name and emoji, in the API's and the website's words. */
    public const GROUP_RULES = [
        'name'  => 'required|string|max:40',
        'emoji' => 'nullable|string|max:16',
    ];
    public const GROUP_MESSAGES = [
        'name.required' => 'Give the group a name.',
        'name.string'   => 'Give the group a name.',
        'name.max'      => 'Keep the group name to 40 characters.',
        'emoji.*'       => 'Pick one emoji for the group.',
    ];

    /** The parts of an expense both forms check the same way (amounts and people differ). */
    public static function expenseRules(): array
    {
        return [
            'description' => 'required|string|max:80',
            'spent_on'    => 'required|date_format:Y-m-d|before_or_equal:' . now()->addDay()->toDateString(),
            'split'       => 'required|in:equal,exact',
        ];
    }

    public const EXPENSE_MESSAGES = [
        'description.required' => 'Say what the expense was for.',
        'description.string'   => 'Say what the expense was for.',
        'description.max'      => 'Keep the description to 80 characters.',
        'spent_on.*'           => 'Pick a day that is not in the future.',
        'split.*'              => 'Split it equally or by exact amounts.',
    ];

    /*
     * ── Finding ─────────────────────────────────────────────────────────
     */

    /** The group with its members, if $user is one of them; a 404 for anyone else. */
    public static function find(User $user, int|string $id): Group
    {
        $group = ctype_digit((string) $id) ? Group::with(Group::withMembers())->find((int) $id) : null;
        if (! $group || ! $group->hasMember($user->id)) abort(404, 'Not found.');

        return $group;
    }

    /** One of the group's expenses, with its shares; a 404 when it belongs to another group. */
    public static function findExpense(Group $group, int|string $id): GroupExpense
    {
        $expense = ctype_digit((string) $id) ? GroupExpense::with('shares')->where('group_id', $group->id)->find((int) $id) : null;
        if (! $expense) abort(404, 'Not found.');

        return $expense;
    }

    /** $user's groups, most recently active first, with members loaded. */
    public static function listFor(User $user)
    {
        return Group::forUser($user)->with(Group::withMembers())->orderByDesc('updated_at')->orderByDesc('id')->get();
    }

    /*
     * ── Groups ──────────────────────────────────────────────────────────
     */

    public static function create(User $user, string $name, ?string $emoji): Group
    {
        return DB::transaction(function () use ($user, $name, $emoji) {
            $group = Group::create(['name' => trim($name), 'emoji' => self::emoji($emoji), 'created_by' => $user->id]);
            GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'joined_at' => now()]);

            return $group->load(Group::withMembers());
        });
    }

    public static function rename(Group $group, string $name, ?string $emoji): Group
    {
        $group->update(['name' => trim($name), 'emoji' => self::emoji($emoji)]);

        return $group;
    }

    /** The creator; or, once the creator has left or deleted their account, any member. */
    public static function canDelete(Group $group, User $user): bool
    {
        if ($group->created_by === $user->id) return true;

        return (! $group->created_by || ! $group->hasMember($group->created_by)) && $group->hasMember($user->id);
    }

    public static function cannotDeleteMessage(Group $group): string
    {
        $creator = $group->members->firstWhere('user_id', $group->created_by)?->user;

        return 'Only ' . ($creator ? self::name($creator) : 'the person who made it') . ', who made this group, can delete it.';
    }

    public static function delete(Group $group): void
    {
        // Shares go with their expenses, everything else with the group (foreign keys).
        $group->delete();
    }

    /*
     * ── Members ─────────────────────────────────────────────────────────
     */

    /** Adds the account with that email, and tells them by push and email. */
    public static function addMember(Group $group, User $actor, string $email): User
    {
        $email = mb_strtolower(trim($email));
        $user  = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) throw ValidationException::withMessages(['email' => self::NO_ACCOUNT]);
        $already = ValidationException::withMessages(['email' => ($user->id === $actor->id ? 'You are' : self::name($user) . ' is') . ' already in this group.']);
        if ($group->hasMember($user->id)) throw $already;
        if ($group->members()->count() >= self::MAX_MEMBERS) {
            throw ValidationException::withMessages(['email' => 'A group can have up to ' . self::MAX_MEMBERS . ' people.']);
        }

        try {
            GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'joined_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            // Two adds racing past the check above meet the unique index.
            throw $already;
        }
        $group->touch();
        $group->load(Group::withMembers());

        // A push to their phones, and always the "added you" email (it is how
        // someone without the app finds the group).
        $others = $group->members->count() - 1;
        self::tell($user, self::firstName($actor) . ' added you to ' . $group->label(),
            $others > 1 ? "Split expenses with {$others} others and see who owes whom." : 'Split expenses and see who owes whom.', $group);
        self::mail($user, new AddedToGroup($group->label(), self::name($actor), $group->id));

        return $user;
    }

    /**
     * Removes a member — or, when it is $actor, leaves. Refused while they
     * owe or are owed anything. The last one out deletes the group.
     *
     * @return bool whether $actor left
     */
    public static function removeMember(Group $group, User $actor, int $userId): bool
    {
        $member = $group->members->firstWhere('user_id', $userId);
        if (! $member) abort(404, 'Not found.');

        $self    = $userId === $actor->id;
        $balance = GroupLedger::balances($group->id)[$userId] ?? 0;
        if ($balance !== 0) {
            $amount = DisplayCurrency::for($actor)->format(abs($balance));
            $state  = $balance < 0 ? ($self ? "you owe {$amount}" : "they owe {$amount}") : ($self ? "you are owed {$amount}" : "they are owed {$amount}");
            throw ValidationException::withMessages(['member' => $self
                ? "You can't leave while {$state} — settle up first."
                : self::name($member->user) . " can't be removed while {$state} — settle up first."]);
        }

        $member->delete();
        $group->load(Group::withMembers());
        if ($group->members->isEmpty()) {
            self::delete($group);
        } else {
            $group->touch();
        }

        return $self;
    }

    /*
     * ── Expenses ────────────────────────────────────────────────────────
     */

    /**
     * Saves an expense whose shares are worked out already (equal shares
     * from GroupLedger::equalShares). Checks that the payer and everyone
     * sharing are in the group — on an edit, people already on the expense
     * may stay — and that the shares add up to the amount.
     *
     * @param  array{description: string, amount_minor: int, paid_by: int, spent_on: string, split: string, shares: array<int, int>}  $data
     * @param  string  $sharesField  where a problem with the people sharing is reported ("participants" or "shares")
     */
    public static function saveExpense(Group $group, User $actor, array $data, ?GroupExpense $expense, string $sharesField): GroupExpense
    {
        $allowed = $group->members->pluck('user_id')->all();
        if ($expense) $allowed = array_merge($allowed, [$expense->paid_by], $expense->shares->pluck('user_id')->all());

        if (! in_array($data['paid_by'], $allowed, true)) {
            throw ValidationException::withMessages(['paid_by' => 'Pick who paid — someone in this group.']);
        }
        $shares = array_filter($data['shares'], fn (int $amount) => $amount > 0);
        if ($shares === []) {
            throw ValidationException::withMessages([$sharesField => 'Pick at least one person to split it with.']);
        }
        if (array_diff(array_keys($shares), $allowed)) {
            throw ValidationException::withMessages([$sharesField => 'Everyone sharing the expense must be in this group.']);
        }
        $sum = array_sum($shares);
        if ($sum !== $data['amount_minor']) {
            $money = DisplayCurrency::for($actor);
            throw ValidationException::withMessages(['shares' => 'The shares add up to ' . $money->format($sum) . ', but the expense is ' . $money->format($data['amount_minor']) . '.']);
        }

        $isNew = $expense === null;
        $expense = DB::transaction(function () use ($group, $actor, $data, $expense, $shares) {
            $fields = [
                'description'  => trim($data['description']),
                'amount_minor' => $data['amount_minor'],
                'paid_by'      => $data['paid_by'],
                'spent_on'     => $data['spent_on'],
                'split'        => $data['split'],
            ];
            if ($expense) {
                $expense->update($fields);
                $expense->shares()->delete();
            } else {
                $expense = GroupExpense::create($fields + ['group_id' => $group->id, 'created_by' => $actor->id]);
            }
            ksort($shares);
            foreach ($shares as $userId => $amount) {
                $expense->shares()->create(['user_id' => $userId, 'amount_minor' => $amount]);
            }
            $group->touch();

            return $expense->load('shares');
        });

        if ($isNew) self::pushExpense($group, $actor, $expense);

        return $expense;
    }

    public static function deleteExpense(Group $group, GroupExpense $expense): void
    {
        $expense->delete();
        $group->touch();
    }

    /*
     * ── Settling up ─────────────────────────────────────────────────────
     */

    /** Records that $from paid $to. Both must be members, or have left owing or owed. */
    public static function settle(Group $group, User $actor, int $from, int $to, int $amount, string $on): GroupSettlement
    {
        $balances = GroupLedger::balances($group->id);
        $allowed  = array_merge($group->members->pluck('user_id')->all(), array_keys(array_filter($balances)));
        if ($from === $to || ! in_array($from, $allowed, true) || ! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['from_user_id' => 'Pick who paid and who was paid — two different people in this group.']);
        }

        $settlement = GroupSettlement::create([
            'group_id' => $group->id, 'from_user_id' => $from, 'to_user_id' => $to,
            'amount_minor' => $amount, 'settled_on' => $on, 'created_by' => $actor->id,
        ]);
        $group->touch();

        $names = self::names($group, [$from, $to]);
        foreach (array_unique([$from, $to]) as $userId) {
            $recipient = $userId === $actor->id ? null : $group->members->firstWhere('user_id', $userId)?->user;
            if (! $recipient) continue;
            $payer = $userId === $from ? 'you' : $names[$from];
            $payee = $userId === $to ? 'you' : $names[$to];
            $what  = self::firstName($actor) . " recorded that {$payer} paid {$payee} " . DisplayCurrency::for($recipient)->format($amount);
            self::tell($recipient, $group->label(), "{$what} in {$group->name}.", $group,
                new GroupActivity("{$group->label()} — {$what}", "{$what} in {$group->name}.", ['On ' . Carbon::parse($on)->format('j M Y')], $group->id));
        }

        return $settlement;
    }

    /** One of the group's recorded payments; a 404 when it belongs to another group. */
    public static function findSettlement(Group $group, int|string $id): GroupSettlement
    {
        $settlement = ctype_digit((string) $id) ? GroupSettlement::where('group_id', $group->id)->find((int) $id) : null;
        if (! $settlement) abort(404, 'Not found.');

        return $settlement;
    }

    /** Undoes a recorded payment (any member may): the balances go back to what they were without it. */
    public static function deleteSettlement(Group $group, GroupSettlement $settlement): void
    {
        $settlement->delete();
        $group->touch();
    }

    /*
     * ── People ──────────────────────────────────────────────────────────
     */

    /** A name to show: the account's name, else the start of its email. */
    public static function name(?User $user): string
    {
        if (! $user) return self::FORMER_MEMBER;
        $name = trim((string) $user->name);

        return $name !== '' ? $name : (Str::before((string) $user->email, '@') ?: 'Member');
    }

    public static function firstName(User $user): string
    {
        return strtok(self::name($user), ' ') ?: self::name($user);
    }

    /**
     * Names for user ids in a group's ledger: members from the loaded
     * relation, anyone who left from the users table, "Former member" for
     * a deleted account.
     *
     * @param  int[]  $ids
     * @return array<int, string>
     */
    public static function names(Group $group, array $ids): array
    {
        $names = [];
        foreach ($group->members as $member) $names[$member->user_id] = self::name($member->user);

        $missing = array_values(array_diff(array_unique($ids), array_keys($names)));
        if ($missing) {
            $users = User::whereIn('id', $missing)->get()->keyBy('id');
            foreach ($missing as $id) $names[$id] = self::name($users[$id] ?? null);
        }

        return $names;
    }

    /**
     * Everyone with a balance to show: the members in the order they joined,
     * then anyone who left (or deleted their account) still owing or owed.
     *
     * @param  array<int, int>  $balances
     * @return list<array{user_id: int, user: ?User, name: string, balance: int, member: bool}>
     */
    public static function people(Group $group, array $balances): array
    {
        $rows = [];
        foreach ($group->members as $member) {
            $rows[] = ['user_id' => $member->user_id, 'user' => $member->user, 'name' => self::name($member->user),
                'balance' => $balances[$member->user_id] ?? 0, 'member' => true];
        }
        $former = array_keys(array_filter($balances, fn (int $b, int $id) => $b !== 0 && ! $group->hasMember($id), ARRAY_FILTER_USE_BOTH));
        if ($former) {
            $users = User::whereIn('id', $former)->get()->keyBy('id');
            foreach ($former as $id) {
                $rows[] = ['user_id' => $id, 'user' => $users[$id] ?? null, 'name' => self::name($users[$id] ?? null),
                    'balance' => $balances[$id], 'member' => false];
            }
        }

        return $rows;
    }

    /** @return list<array{from: int, to: int, amount: int, from_name: string, to_name: string}> */
    public static function suggestions(Group $group, array $balances): array
    {
        $payments = GroupLedger::simplify($balances);
        $names = self::names($group, array_merge(array_column($payments, 'from'), array_column($payments, 'to')));

        return array_map(fn (array $p) => $p + ['from_name' => $names[$p['from']], 'to_name' => $names[$p['to']]], $payments);
    }

    /*
     * ── API shapes ──────────────────────────────────────────────────────
     */

    /** @param array<int, int> $balances the group's, from GroupLedger */
    public static function groupShape(Group $group, User $viewer, array $balances): array
    {
        return [
            'id'               => $group->id,
            'name'             => $group->name,
            'emoji'            => $group->emoji ?: Group::DEFAULT_EMOJI,
            'created_by'       => $group->created_by,
            'member_count'     => $group->members->count(),
            'members'          => $group->members->map(fn (GroupMember $m) => [
                'user_id'       => $m->user_id,
                'name'          => self::name($m->user),
                'email'         => $m->user?->email,
                'avatar_url'    => $m->user ? Accounts::avatarUrl($m->user) : null,
                'balance_minor' => $balances[$m->user_id] ?? 0,
            ])->values()->all(),
            'my_balance_minor' => $balances[$viewer->id] ?? 0,
            'created_at'       => $group->created_at?->toIso8601String(),
        ];
    }

    public static function detailShape(Group $group, User $viewer): array
    {
        $balances    = GroupLedger::balances($group->id);
        $settlements = $group->settlements()->orderByDesc('settled_on')->orderByDesc('id')->limit(self::RECENT_SETTLEMENTS)->get();
        $names       = self::names($group, $settlements->pluck('from_user_id')->merge($settlements->pluck('to_user_id'))->all());

        return self::groupShape($group, $viewer, $balances) + [
            'suggested_settlements' => array_map(fn (array $p) => [
                'from_user_id' => $p['from'],
                'from_name'    => $p['from_name'],
                'to_user_id'   => $p['to'],
                'to_name'      => $p['to_name'],
                'amount_minor' => $p['amount'],
            ], self::suggestions($group, $balances)),
            'recent_settlements' => $settlements->map(fn (GroupSettlement $s) => [
                'id'           => $s->id,
                'from_user_id' => $s->from_user_id,
                'from_name'    => $names[$s->from_user_id],
                'to_user_id'   => $s->to_user_id,
                'to_name'      => $names[$s->to_user_id],
                'amount_minor' => $s->amount_minor,
                'on'           => $s->settled_on->toDateString(),
            ])->values()->all(),
        ];
    }

    /** @param array<int, string> $names from names() */
    public static function expenseShape(GroupExpense $expense, User $viewer, array $names): array
    {
        return [
            'id'             => $expense->id,
            'description'    => $expense->description,
            'amount_minor'   => $expense->amount_minor,
            'paid_by'        => $expense->paid_by,
            'paid_by_name'   => $names[$expense->paid_by] ?? self::FORMER_MEMBER,
            'spent_on'       => $expense->spent_on->toDateString(),
            'split'          => $expense->split,
            'shares'         => $expense->shares->sortBy('user_id')->map(fn ($s) => [
                'user_id'      => $s->user_id,
                'name'         => $names[$s->user_id] ?? self::FORMER_MEMBER,
                'amount_minor' => $s->amount_minor,
            ])->values()->all(),
            'my_share_minor' => $expense->shareOf($viewer->id),
            'created_by'     => $expense->created_by,
            'created_at'     => $expense->created_at?->toIso8601String(),
        ];
    }

    /** Names for every person on these expenses. */
    public static function expenseNames(Group $group, iterable $expenses): array
    {
        $ids = [];
        foreach ($expenses as $expense) {
            $ids[] = $expense->paid_by;
            foreach ($expense->shares as $share) $ids[] = $share->user_id;
        }

        return self::names($group, $ids);
    }

    /*
     * ── Telling people ──────────────────────────────────────────────────
     */

    /** "Rishi added 'Dinner' ₹1,200.00 in Goa trip — your share ₹400.00", to everyone on it but $actor. */
    private static function pushExpense(Group $group, User $actor, GroupExpense $expense): void
    {
        $involved = array_unique(array_merge([$expense->paid_by], $expense->shares->pluck('user_id')->all()));
        foreach ($group->members as $member) {
            if ($member->user_id === $actor->id || ! in_array($member->user_id, $involved, true) || ! $member->user) continue;

            $money = DisplayCurrency::for($member->user);
            $share = $expense->shareOf($member->user_id);
            $parts = array_filter([
                $expense->paid_by === $member->user_id ? 'paid by you' : null,
                $share > 0 ? 'your share ' . $money->format($share) : null,
            ]);
            $what = self::firstName($actor) . " added '{$expense->description}' " . $money->format($expense->amount_minor);
            self::tell($member->user, $group->label(), "{$what} in {$group->name} — " . implode(', ', $parts), $group,
                new GroupActivity("{$group->label()} — {$what}", "{$what} in {$group->name}.", array_values(array_map('ucfirst', $parts)), $group->id));
        }
    }

    /**
     * Tells $user about something in the group: a push to their phones, or —
     * with no phone of theirs signed in — $email instead (if given, and they
     * keep emails on). Never both. Either goes out from the queue, so a slow
     * Firebase or mail server never delays a save, and a failure never breaks one.
     */
    private static function tell(User $user, string $title, string $body, Group $group, ?GroupActivity $email = null): void
    {
        if (DeviceToken::where('user_id', $user->id)->exists()) {
            try {
                SendPush::dispatch($user->id, $title, $body, ['type' => 'group', 'group_id' => (string) $group->id]);
            } catch (Throwable $e) {
                report($e);
            }
        } elseif ($email) {
            self::mail($user, $email);
        }
    }

    /** A queued email, for an account with an address and emails kept on. */
    private static function mail(User $user, Notification $notification): void
    {
        if (! $user->email || $user->email_notifications === false) return;
        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function emoji(?string $emoji): string
    {
        $emoji = trim((string) $emoji);

        return $emoji !== '' ? $emoji : Group::DEFAULT_EMOJI;
    }
}
