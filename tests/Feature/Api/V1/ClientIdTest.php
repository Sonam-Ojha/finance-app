<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppExpense;
use App\Models\AppIncome;

/** The app's offline queue resends an entry with the same client_id: saved once (App\Support\ClientIds). */
class ClientIdTest extends ApiTestCase
{
    public function test_a_resent_expense_is_saved_once(): void
    {
        $user = $this->signIn();
        $entry = ['amount_minor' => 25000, 'category_id' => $this->category($user)->id, 'spent_at' => now()->toDateString(), 'client_id' => 'c0ffee-1'];

        $first = $this->postJson('/api/v1/expenses', $entry)->assertCreated();
        $again = $this->postJson('/api/v1/expenses', $entry)->assertOk();

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame(1, AppExpense::where('user_id', $user->id)->count());
        $this->assertArrayNotHasKey('client_id', $first->json('data'));

        // The header works too, and a different id is a different entry.
        $this->postJson('/api/v1/expenses', array_diff_key($entry, ['client_id' => 1]), ['Idempotency-Key' => 'c0ffee-1'])->assertOk();
        $this->postJson('/api/v1/expenses', ['client_id' => 'c0ffee-2'] + $entry)->assertCreated();
        $this->assertSame(2, AppExpense::where('user_id', $user->id)->count());
    }

    public function test_a_resent_income_is_saved_once(): void
    {
        $user = $this->signIn();
        $entry = ['amount_minor' => 5000000, 'source' => 'Salary', 'received_at' => now()->toDateString(), 'client_id' => 'abc-9'];

        $id = $this->postJson('/api/v1/incomes', $entry)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/incomes', $entry)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame(1, AppIncome::where('user_id', $user->id)->count());
    }

    public function test_ids_belong_to_one_account_and_entries_without_one_are_unchanged(): void
    {
        $asha = $this->signIn();
        $entry = ['amount_minor' => 1000, 'source' => 'Gift', 'received_at' => now()->toDateString(), 'client_id' => 'same-id'];
        $this->postJson('/api/v1/incomes', $entry)->assertCreated();

        $ravi = $this->signIn(['email' => 'ravi@example.com']);
        $this->postJson('/api/v1/incomes', $entry)->assertCreated();

        // Without an id, two identical entries are two entries, as before.
        unset($entry['client_id']);
        $this->postJson('/api/v1/incomes', $entry)->assertCreated();
        $this->postJson('/api/v1/incomes', $entry)->assertCreated();

        $this->assertSame([1, 3], [AppIncome::where('user_id', $asha->id)->count(), AppIncome::where('user_id', $ravi->id)->count()]);
    }
}
