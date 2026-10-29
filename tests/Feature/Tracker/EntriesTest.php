<?php

namespace Tests\Feature\Tracker;

use App\Models\AppExpense;
use App\Models\AppIncome;
use Laravel\Sanctum\Sanctum;

class EntriesTest extends TrackerTestCase
{
    public function test_an_expense_added_on_the_website_reaches_the_app(): void
    {
        $this->post('/expenses', [
            'amount' => '1,234.50', 'category_id' => $this->category('Groceries')->id,
            'spent_at' => now()->toDateString(), 'payment_method' => 'cash', 'note' => '  Milk  ',
            'back' => url('/dashboard'),
        ])->assertRedirect('/dashboard')->assertSessionHas('success', 'Expense of ₹1,234.50 added · Groceries');

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/expenses?month=' . now()->format('Y-m'))->assertOk()
            ->assertJsonPath('data.0.amount_minor', 123450)
            ->assertJsonPath('data.0.note', 'Milk')
            ->assertJsonPath('data.0.payment_method', 'cash')
            ->assertJsonPath('data.0.category_name', 'Groceries');
    }

    public function test_expense_rules_match_the_app(): void
    {
        $this->post('/expenses', ['amount' => '0', 'spent_at' => now()->addDays(3)->toDateString()])
            ->assertSessionHasErrors([
                'amount' => 'Enter an amount greater than zero.',
                'category_id' => 'Pick a category for this expense.',
                'spent_at' => 'Pick a day that is not in the future.',
            ]);
        $this->post('/expenses', ['amount' => '1,5', 'category_id' => $this->category()->id, 'spent_at' => now()->toDateString()])
            ->assertSessionHasErrors(['amount' => 'Enter an amount greater than zero.']);
        $this->assertSame(0, AppExpense::count());
    }

    public function test_editing_keeps_an_untouched_amount_exactly(): void
    {
        // $1 = ₹83.33: 1,000 paise shows as $0.12, which would store back as 1,000 — but 1,001 would not survive a round trip.
        $this->user->update(['currency' => 'USD']);
        $this->user->userCurrencies()->create(['code' => 'USD', 'rate' => 83.33]);
        $expense = $this->expense(1001);

        $this->put("/expenses/{$expense->id}", [
            'amount' => '0.12', 'category_id' => $expense->category_id, 'spent_at' => $expense->spent_at->toDateString(), 'note' => 'Edited',
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Expense updated');

        $this->assertSame(1001, $expense->fresh()->amount_minor);
        $this->assertSame('Edited', $expense->fresh()->note);

        $this->delete("/expenses/{$expense->id}")->assertSessionHas('success', 'Expense deleted');
        $this->assertModelMissing($expense);
    }

    public function test_income_on_the_website(): void
    {
        $this->post('/incomes', ['amount' => '50000', 'source' => '  Freelance ', 'received_at' => now()->toDateString(), 'payment_method' => 'bank_transfer'])
            ->assertSessionHas('success', 'Income of ₹50,000.00 added · Freelance');
        $income = AppIncome::firstOrFail();
        $this->assertSame([5000000, 'Freelance', 'bank_transfer'], [$income->amount_minor, $income->source, $income->payment_method]);

        $this->post('/incomes', ['amount' => '5', 'source' => ' ', 'received_at' => now()->toDateString()])
            ->assertSessionHasErrors(['source' => 'Say where this income came from.']);

        $this->put("/incomes/{$income->id}", ['amount' => '60000', 'source' => 'Salary', 'received_at' => now()->toDateString(), 'payment_method' => ''])
            ->assertSessionHas('success', 'Income updated');
        $this->assertSame([6000000, 'Salary', null], [$income->fresh()->amount_minor, $income->fresh()->source, $income->fresh()->payment_method]);

        $this->delete("/incomes/{$income->id}")->assertSessionHas('success', 'Income deleted');
        $this->assertSame(0, AppIncome::count());
    }
}
