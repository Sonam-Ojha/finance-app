<?php

namespace Tests\Feature\Tracker;

use App\Models\User;

class PagesTest extends TrackerTestCase
{
    public function test_every_page_opens(): void
    {
        $expense = $this->expense(45000, 'Transport', null, ['note' => 'Auto', 'payment_method' => 'upi']);
        $income  = $this->income(5000000);

        foreach ([
            '/dashboard', '/transactions', '/transactions?range=weekly&sort=highest&type=expense', '/analytics?range=yearly&trend=weekly',
            '/expenses/create', "/expenses/{$expense->id}/edit", '/incomes/create', "/incomes/{$income->id}/edit",
            '/categories', '/categories/create', '/categories/' . $this->category()->id . '/edit',
            '/budgets', '/budgets?month=2026-01', '/budgets/every-month', '/notifications',
            '/settings', '/settings/appearance', '/about', '/settings/currency', '/settings/currency/new', '/settings/currency/USD',
            '/profile', '/settings/password',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        auth()->logout();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/transactions')->assertRedirect('/login');
    }

    public function test_the_menu_is_the_apps_and_the_old_pages_are_gone_from_it(): void
    {
        $page = $this->get('/dashboard')->assertOk();

        foreach (['Transactions', 'Analytics', 'Budgets', 'Categories', 'Notifications', 'Settings'] as $item) {
            $page->assertSee($item);
        }
        foreach (['Bank Accounts', 'Credit Cards', 'Loans / EMI', 'Investments', 'Commission', 'Cashback', 'Contact Ledger', 'Reminders', 'Pending Money'] as $old) {
            $page->assertDontSee($old);
        }
        $page->assertDontSee('Send notification');

        $admin = $this->makeUser(['email' => 'admin@example.com']);
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $this->actingAs($admin)->get('/dashboard')->assertSee('Send notification')->assertSee('Users');
    }

    public function test_dark_mode_follows_the_appearance_setting(): void
    {
        $this->get('/dashboard')->assertDontSee('class="dark"', false);

        $this->put('/settings/appearance', ['theme_mode' => 'dark'])->assertRedirect('/settings/appearance');
        $this->assertSame('dark', $this->user->fresh()->theme_mode);
        $this->get('/dashboard')->assertSee('class="dark"', false);
    }

    public function test_someone_elses_entries_are_not_found(): void
    {
        $other = $this->makeUser(['email' => 'other@example.com']);
        $theirs = $this->category('Food & Drink', $other);
        $expense = \App\Models\AppExpense::create(['user_id' => $other->id, 'amount_minor' => 100, 'category_id' => $theirs->id, 'spent_at' => now()->toDateString()]);

        $this->get("/expenses/{$expense->id}/edit")->assertNotFound();
        $this->put("/expenses/{$expense->id}", ['amount' => '5'])->assertNotFound();
        $this->delete("/expenses/{$expense->id}")->assertNotFound();
        $this->get("/categories/{$theirs->id}/edit")->assertNotFound();
        $this->assertDatabaseHas('app_expenses', ['id' => $expense->id]);
    }
}
