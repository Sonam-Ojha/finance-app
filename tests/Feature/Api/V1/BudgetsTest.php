<?php

namespace Tests\Feature\Api\V1;

use App\Models\Budget;
use Laravel\Sanctum\Sanctum;

class BudgetsTest extends ApiTestCase
{
    public function test_a_month_has_a_whole_budget_and_category_budgets_with_live_spending(): void
    {
        $user = $this->signIn();
        $transport = $this->category($user)->id;

        $whole = $this->postJson('/api/v1/budgets', ['month' => '2026-09', 'category_id' => null, 'amount_minor' => 1000000])
            ->assertCreated()
            ->assertJsonPath('data.month', '2026-09')
            ->assertJsonPath('data.category_id', null)
            ->assertJsonPath('data.spent_minor', 0)
            ->json('data.id');
        $this->postJson('/api/v1/budgets', ['month' => '2026-09', 'category_id' => $transport, 'amount_minor' => 500000])
            ->assertCreated();

        // Spending is read on every request: create, edit and delete all show.
        $expense = $this->postJson('/api/v1/expenses', ['amount_minor' => 312000, 'category_id' => $transport, 'spent_at' => '2026-09-15'])->json('data.id');
        $this->getJson("/api/v1/budgets/{$whole}")
            ->assertJsonPath('data.spent_minor', 312000)
            ->assertJsonPath('data.remaining_minor', 688000)
            ->assertJsonPath('data.percent_used', 31.2)
            ->assertJsonPath('data.is_over', false);

        // Moved into October: September no longer counts it.
        $this->putJson("/api/v1/expenses/{$expense}", ['amount_minor' => 312000, 'category_id' => $transport, 'spent_at' => '2026-10-02']);
        $this->getJson("/api/v1/budgets/{$whole}")->assertJsonPath('data.spent_minor', 0);

        $this->putJson("/api/v1/expenses/{$expense}", ['amount_minor' => 600000, 'category_id' => $transport, 'spent_at' => '2026-09-30']);
        $list = $this->getJson('/api/v1/budgets?month=2026-09')->assertOk()->assertJsonCount(2, 'data');
        $category = collect($list->json('data'))->firstWhere('category_id', $transport);
        $this->assertSame(600000, $category['spent_minor']);
        $this->assertSame(-100000, $category['remaining_minor']);
        $this->assertTrue($category['is_over']);

        $this->deleteJson("/api/v1/expenses/{$expense}");
        $this->getJson("/api/v1/budgets/{$whole}")->assertJsonPath('data.spent_minor', 0);

        $this->getJson('/api/v1/budgets?month=2026-10')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_one_budget_per_category_per_month(): void
    {
        $user = $this->signIn();
        $transport = $this->category($user)->id;
        $body = ['month' => '2026-09', 'category_id' => null, 'amount_minor' => 1000];

        $this->postJson('/api/v1/budgets', $body)->assertCreated();
        $this->postJson('/api/v1/budgets', $body)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This month already has that budget.');

        $this->postJson('/api/v1/budgets', ['category_id' => $transport] + $body)->assertCreated();
        $this->postJson('/api/v1/budgets', ['category_id' => $transport] + $body)->assertUnprocessable();
        // Another month is another budget.
        $this->postJson('/api/v1/budgets', ['month' => '2026-10'] + $body)->assertCreated();

        $this->assertSame(3, Budget::count());
    }

    public function test_bad_input_is_rejected(): void
    {
        $user = $this->signIn();
        $foreign = $this->category($this->makeUser(['email' => 'other@example.com']))->id;

        $this->postJson('/api/v1/budgets', ['month' => '2026-09', 'amount_minor' => 0])
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount_minor.0', 'Budget must be a number greater than zero.');
        $this->postJson('/api/v1/budgets', ['month' => '2026-13', 'amount_minor' => 100])->assertUnprocessable();
        $this->postJson('/api/v1/budgets', ['month' => '2026-09', 'category_id' => $foreign, 'amount_minor' => 100])->assertUnprocessable();
        $this->getJson('/api/v1/budgets')->assertUnprocessable();
    }

    public function test_update_delete_and_ownership(): void
    {
        $user = $this->signIn();
        $id = $this->postJson('/api/v1/budgets', ['month' => '2026-09', 'amount_minor' => 1000])->json('data.id');

        $this->putJson("/api/v1/budgets/{$id}", ['amount_minor' => 2500])->assertOk()->assertJsonPath('data.amount_minor', 2500);

        Sanctum::actingAs($this->makeUser(['email' => 'other@example.com']));
        $this->getJson("/api/v1/budgets/{$id}")->assertNotFound();
        $this->putJson("/api/v1/budgets/{$id}", ['amount_minor' => 1])->assertNotFound();
        $this->deleteJson("/api/v1/budgets/{$id}")->assertNotFound();

        Sanctum::actingAs($user);
        $this->deleteJson("/api/v1/budgets/{$id}")->assertNoContent();
        $this->assertSame(0, Budget::count());
    }

    public function test_deleting_a_category_removes_its_budgets(): void
    {
        $user = $this->signIn();
        $shopping = $this->category($user, 'Shopping')->id;
        $this->postJson('/api/v1/budgets', ['month' => '2026-09', 'category_id' => $shopping, 'amount_minor' => 1000])->assertCreated();

        $this->deleteJson("/api/v1/categories/{$shopping}")->assertSuccessful();

        $this->assertSame(0, Budget::count());
    }
}
