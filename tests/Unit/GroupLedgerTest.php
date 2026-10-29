<?php

namespace Tests\Unit;

use App\Support\GroupLedger;
use PHPUnit\Framework\TestCase;

/** Groups' money maths: equal splits to the paisa and the fewest settle-up payments. */
class GroupLedgerTest extends TestCase
{
    public function test_an_equal_split_gives_leftover_paise_to_the_lowest_ids_first(): void
    {
        $this->assertSame([3 => 334, 7 => 333, 9 => 333], GroupLedger::equalShares(1000, [9, 3, 7]));
        $this->assertSame([1 => 34, 2 => 34, 3 => 33, 4 => 33], GroupLedger::equalShares(134, [4, 3, 2, 1]));
        $this->assertSame([5 => 1200], GroupLedger::equalShares(1200, [5, 5]));
        $this->assertSame([1 => 1, 2 => 0, 3 => 0], GroupLedger::equalShares(1, [1, 2, 3]));
        $this->assertSame([], GroupLedger::equalShares(1000, []));

        foreach ([[1, [1, 2, 3]], [99999, [4, 8, 15, 16, 23, 42]], [100, [1, 2, 3, 4, 5, 6, 7]]] as [$amount, $ids]) {
            $this->assertSame($amount, array_sum(GroupLedger::equalShares($amount, $ids)));
        }
    }

    public function test_the_one_who_owes_most_pays_the_one_owed_most(): void
    {
        $this->assertSame([
            ['from' => 3, 'to' => 1, 'amount' => 534],
            ['from' => 2, 'to' => 1, 'amount' => 32],
        ], GroupLedger::simplify([1 => 566, 2 => -32, 3 => -534]));

        // Four people, two payments rather than three.
        $this->assertSame([
            ['from' => 2, 'to' => 1, 'amount' => 5000],
            ['from' => 4, 'to' => 3, 'amount' => 2000],
        ], GroupLedger::simplify([1 => 5000, 2 => -5000, 3 => 2000, 4 => -2000]));

        // A debtor split across two creditors.
        $this->assertSame([
            ['from' => 3, 'to' => 1, 'amount' => 700],
            ['from' => 3, 'to' => 2, 'amount' => 300],
        ], GroupLedger::simplify([1 => 700, 2 => 300, 3 => -1000]));

        $this->assertSame([], GroupLedger::simplify([1 => 0, 2 => 0]));
        $this->assertSame([], GroupLedger::simplify([]));
    }

    public function test_ties_go_to_the_lower_id(): void
    {
        $this->assertSame([
            ['from' => 3, 'to' => 1, 'amount' => 100],
            ['from' => 4, 'to' => 2, 'amount' => 100],
        ], GroupLedger::simplify([4 => -100, 2 => 100, 3 => -100, 1 => 100]));
    }
}
