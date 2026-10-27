<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Models\UserCurrency;

class DeleteAccountTest extends ApiTestCase
{
    private function fillAccount(User $user): void
    {
        $transport = $this->category($user)->id;
        $this->postJson('/api/v1/expenses', ['amount_minor' => 100, 'category_id' => $transport, 'spent_at' => '2026-09-01'])->assertCreated();
        $this->postJson('/api/v1/incomes', ['amount_minor' => 100, 'source' => 'Salary', 'received_at' => '2026-09-01'])->assertCreated();
        $this->postJson('/api/v1/budgets', ['month' => '2026-09', 'category_id' => $transport, 'amount_minor' => 100])->assertCreated();
        $this->putJson('/api/v1/me/currencies/THB', ['rate' => 2.31, 'label' => 'Thai Baht', 'symbol' => '฿'])->assertOk();
    }

    public function test_a_user_with_data_is_deleted_completely(): void
    {
        $user = $this->signIn(['password' => 'secret123']);
        $this->fillAccount($user);
        $keep = $this->makeUser(['email' => 'other@example.com']);

        $this->deleteJson('/api/v1/me', ['password' => 'secret123'])->assertNoContent();

        $this->assertNull(User::find($user->id));
        foreach ([AppExpense::class, AppIncome::class, Budget::class, UserCurrency::class, Category::class] as $model) {
            $this->assertSame(0, $model::where('user_id', $user->id)->count(), $model);
        }
        $this->assertSame(0, $user->tokens()->count());
        // Nobody else's data goes with it.
        $this->assertSame(8, Category::where('user_id', $keep->id)->count());
    }

    public function test_the_password_must_match(): void
    {
        $user = $this->signIn(['password' => 'secret123']);

        $this->deleteJson('/api/v1/me', [])->assertUnprocessable();
        $this->deleteJson('/api/v1/me', ['password' => 'wrong-one1'])->assertUnprocessable();
        $this->assertNotNull(User::find($user->id));
    }

    public function test_an_account_without_a_password_needs_none(): void
    {
        $user = $this->signIn(['password' => null, 'google_id' => 'google-123']);
        $this->fillAccount($user);

        $this->deleteJson('/api/v1/me')->assertNoContent();
        $this->assertNull(User::find($user->id));
    }
}
