<?php

namespace Tests\Feature\Api\V1;

class PaymentMethodTest extends ApiTestCase
{
    private function expenseBody(int $categoryId, array $extra = []): array
    {
        return array_merge(['amount_minor' => 45000, 'category_id' => $categoryId, 'note' => 'Auto', 'spent_at' => '2026-10-20'], $extra);
    }

    public function test_an_expense_keeps_its_payment_method(): void
    {
        $user = $this->signIn();
        $categoryId = $this->category($user)->id;

        $id = $this->postJson('/api/v1/expenses', $this->expenseBody($categoryId, ['payment_method' => 'upi']))
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'upi')
            ->json('data.id');

        $this->getJson("/api/v1/expenses/{$id}")->assertJsonPath('data.payment_method', 'upi');
        $this->getJson('/api/v1/expenses?month=2026-10')->assertJsonPath('data.0.payment_method', 'upi');
        $this->getJson('/api/v1/dashboard?month=2026-10')->assertJsonPath('data.expenses.0.payment_method', 'upi');
    }

    public function test_an_unknown_payment_method_is_rejected(): void
    {
        $user = $this->signIn();

        $this->postJson('/api/v1/expenses', $this->expenseBody($this->category($user)->id, ['payment_method' => 'cheque']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.payment_method.0', 'Pick a payment method from the list.');
    }

    public function test_an_edit_without_the_key_keeps_it_and_null_clears_it(): void
    {
        $user = $this->signIn();
        $categoryId = $this->category($user)->id;
        $id = $this->postJson('/api/v1/expenses', $this->expenseBody($categoryId, ['payment_method' => 'cash']))->json('data.id');

        // An older app version: no payment_method in the body at all.
        $this->putJson("/api/v1/expenses/{$id}", $this->expenseBody($categoryId, ['amount_minor' => 50000]))
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.amount_minor', 50000);

        $this->putJson("/api/v1/expenses/{$id}", $this->expenseBody($categoryId, ['payment_method' => null]))
            ->assertOk()
            ->assertJsonPath('data.payment_method', null);
    }

    public function test_incomes_carry_a_payment_method_too(): void
    {
        $this->signIn();
        $body = ['amount_minor' => 5000000, 'source' => 'Salary', 'received_at' => '2026-10-01'];

        $id = $this->postJson('/api/v1/incomes', $body + ['payment_method' => 'bank_transfer'])
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'bank_transfer')
            ->json('data.id');

        $this->putJson("/api/v1/incomes/{$id}", $body)->assertJsonPath('data.payment_method', 'bank_transfer');
        $this->putJson("/api/v1/incomes/{$id}", $body + ['payment_method' => 'other'])->assertJsonPath('data.payment_method', 'other');
        $this->postJson('/api/v1/incomes', $body + ['payment_method' => 'gold'])->assertUnprocessable();
    }
}
