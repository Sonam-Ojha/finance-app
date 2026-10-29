<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The money maths of groups, in integer paise only.
 *
 * A person's balance in a group is what they paid, minus what they owe
 * (their shares), plus settlements they paid, minus settlements paid to
 * them. Positive: the others owe them. The balances of a group always add
 * up to zero.
 */
class GroupLedger
{
    /**
     * An equal split of $amount between $userIds. Leftover paise go one each
     * to the lowest user ids first, so the same split always comes out the
     * same: ₹10.00 between three is 334, 333, 333.
     *
     * @param  int[]  $userIds
     * @return array<int, int> user id => share
     */
    public static function equalShares(int $amount, array $userIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        sort($ids);
        if ($ids === []) return [];

        $base     = intdiv($amount, count($ids));
        $leftover = $amount - $base * count($ids);
        $shares   = [];
        foreach ($ids as $i => $id) {
            $shares[$id] = $base + ($i < $leftover ? 1 : 0);
        }

        return $shares;
    }

    /**
     * Every group's balances, read in four queries however many groups.
     * Includes anyone in the ledger, member or not (someone who left, or
     * deleted their account); zero balances are kept.
     *
     * @param  int[]  $groupIds
     * @return array<int, array<int, int>> group id => (user id => balance)
     */
    public static function balancesFor(array $groupIds): array
    {
        $balances = array_fill_keys($groupIds, []);
        if ($groupIds === []) return $balances;

        $add = function (iterable $rows, int $sign) use (&$balances) {
            foreach ($rows as $row) {
                $g = (int) $row->group_id;
                $u = (int) $row->user_id;
                $balances[$g][$u] = ($balances[$g][$u] ?? 0) + $sign * (int) $row->total;
            }
        };

        $add(DB::table('group_expenses')->whereIn('group_id', $groupIds)
            ->groupBy('group_id', 'paid_by')
            ->selectRaw('group_id, paid_by as user_id, sum(amount_minor) as total')->get(), 1);

        $add(DB::table('group_expense_shares')
            ->join('group_expenses', 'group_expenses.id', '=', 'group_expense_shares.group_expense_id')
            ->whereIn('group_expenses.group_id', $groupIds)
            ->groupBy('group_expenses.group_id', 'group_expense_shares.user_id')
            ->selectRaw('group_expenses.group_id as group_id, group_expense_shares.user_id as user_id, sum(group_expense_shares.amount_minor) as total')->get(), -1);

        $add(DB::table('group_settlements')->whereIn('group_id', $groupIds)
            ->groupBy('group_id', 'from_user_id')
            ->selectRaw('group_id, from_user_id as user_id, sum(amount_minor) as total')->get(), 1);

        $add(DB::table('group_settlements')->whereIn('group_id', $groupIds)
            ->groupBy('group_id', 'to_user_id')
            ->selectRaw('group_id, to_user_id as user_id, sum(amount_minor) as total')->get(), -1);

        return $balances;
    }

    /** @return array<int, int> user id => balance */
    public static function balances(int $groupId): array
    {
        return self::balancesFor([$groupId])[$groupId];
    }

    /**
     * Who pays whom so everyone ends up square, in few payments: the one who
     * owes most pays the one owed most, as much as either allows, again and
     * again. Ties go to the lower user id, so the list is stable.
     *
     * @param  array<int, int>  $balances  user id => balance (summing to zero)
     * @return list<array{from: int, to: int, amount: int}>
     */
    public static function simplify(array $balances): array
    {
        $owe = $owed = [];
        foreach ($balances as $id => $amount) {
            if ($amount < 0) $owe[(int) $id] = -$amount;
            if ($amount > 0) $owed[(int) $id] = $amount;
        }

        $largest = function (array $amounts): int {
            $best = null;
            foreach ($amounts as $id => $amount) {
                if ($best === null || $amount > $amounts[$best] || ($amount === $amounts[$best] && $id < $best)) $best = $id;
            }
            return $best;
        };

        $payments = [];
        while ($owe && $owed) {
            $from   = $largest($owe);
            $to     = $largest($owed);
            $amount = min($owe[$from], $owed[$to]);
            $payments[] = ['from' => $from, 'to' => $to, 'amount' => $amount];

            $owe[$from] -= $amount;
            $owed[$to]  -= $amount;
            if ($owe[$from] === 0) unset($owe[$from]);
            if ($owed[$to] === 0) unset($owed[$to]);
        }

        return $payments;
    }
}
