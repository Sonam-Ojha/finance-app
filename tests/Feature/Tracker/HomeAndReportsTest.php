<?php

namespace Tests\Feature\Tracker;

use Laravel\Sanctum\Sanctum;

class HomeAndReportsTest extends TrackerTestCase
{
    public function test_what_the_app_records_shows_on_the_website(): void
    {
        // Added through the app's API…
        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/expenses', [
            'amount_minor' => 123400, 'category_id' => $this->category('Groceries')->id,
            'note' => 'Weekly veggies', 'payment_method' => 'upi', 'spent_at' => now()->toDateString(),
        ])->assertCreated();
        $this->postJson('/api/v1/incomes', [
            'amount_minor' => 5000000, 'source' => 'Salary', 'received_at' => now()->toDateString(),
        ])->assertCreated();

        // …and seen on the website, same account.
        $this->actingAs($this->user, 'web');
        $this->get('/dashboard')->assertOk()
            ->assertSee('Asha 👋')
            ->assertSee('₹48,766.00')   // balance: 50,000 − 1,234
            ->assertSee('₹50,000.00')
            ->assertSee('₹1,234.00')
            ->assertSee('Weekly veggies')
            ->assertSee('Groceries');

        $this->get('/transactions')->assertOk()->assertSee('Weekly veggies')->assertSee('UPI')->assertSee('Salary');
    }

    public function test_home_asks_for_income_until_there_is_some(): void
    {
        $this->expense(10000);
        $this->get('/dashboard')->assertSee('Add your income to see what is left');

        $this->income(10000);
        $this->get('/dashboard')->assertDontSee('Add your income to see what is left');
    }

    public function test_transactions_filter_and_sort(): void
    {
        $month = now()->format('Y-m');
        $this->expense(45000, 'Transport', null, ['note' => 'Auto to office', 'payment_method' => 'upi']);
        $this->expense(120000, 'Food & Drink', null, ['note' => 'Team dinner', 'payment_method' => 'credit_card']);
        $this->income(500000, 'Refund', null, ['note' => 'Office auto']);

        $this->get('/transactions?search=auto')->assertSee('Auto to office')->assertSee('Office auto')->assertDontSee('Team dinner');
        $this->get('/transactions?type=income')->assertSee('Office auto')->assertDontSee('Auto to office');
        $this->get('/transactions?category=' . $this->category('Food & Drink')->id)->assertSee('Team dinner')->assertDontSee('Auto to office')->assertDontSee('Office auto');
        $this->get('/transactions?payment=upi')->assertSee('Auto to office')->assertDontSee('Team dinner');
        $this->get('/transactions?min=1000')->assertSee('Team dinner')->assertSee('Office auto')->assertDontSee('Auto to office');
        $this->get('/transactions?sort=highest')->assertSeeInOrder(['Highest amount', 'Office auto', 'Team dinner', 'Auto to office']);

        $this->get('/transactions?min=abc')->assertSee('Amounts need a number greater than zero, or an empty field.');
        $this->get('/transactions?min=500&max=100')->assertSee('The minimum is higher than the maximum.');
        $this->get('/transactions?search=nothing-like-this')->assertSee('No matches');
        $this->get('/transactions?month=2020-01')->assertSee('Nothing recorded')->assertSee('January 2020');
        $this->get("/transactions?month={$month}")->assertSee('All Transactions');
    }

    public function test_notifications_show_messages_and_work_out_alerts(): void
    {
        $this->get('/notifications')->assertSee('No income recorded yet')->assertSee('No budgets set');

        $this->income(100000);
        $transport = $this->category('Transport');
        $transport->update(['budget_minor' => 50000]);
        $food = $this->category('Food & Drink');
        $food->update(['budget_minor' => 100000]);
        $this->expense(60000, 'Transport');
        $this->expense(85000, 'Food & Drink');

        $this->get('/notifications')
            ->assertSee('Transport is over budget')
            ->assertSee('₹100.00 past its ₹500.00 limit this month.')
            ->assertSee('Food &amp; Drink is nearly spent', false)
            ->assertSee('85% of its ₹1,000.00 budget is gone.')
            ->assertDontSee('No income recorded yet')
            ->assertDontSee('No budgets set');

        // The bell's red dot counts only budgets already passed.
        $this->get('/dashboard')->assertSee('Notifications, 1 budget alert');

        \App\Models\AdminNotification::create(['title' => 'New: budgets', 'body' => 'Set one today', 'audience' => 'all', 'status' => 'sent']);
        $this->get('/notifications')->assertSeeInOrder(['Messages', 'New: budgets', 'Set one today', 'Alerts']);
    }

    public function test_analytics_lists_budgets_and_history(): void
    {
        $this->category('Transport')->update(['budget_minor' => 50000]);
        $this->expense(25000, 'Transport');

        $this->get('/analytics')->assertOk()
            ->assertSee('Monthly Budgets')
            ->assertSee('₹250.00 of ₹500.00')
            ->assertSee('₹250.00 left')
            ->assertSee('Today');
    }
}
