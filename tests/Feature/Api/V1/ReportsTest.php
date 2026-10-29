<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\User;

/** Pins the numbers the app's Home, Analytics and Notifications screens show. */
class ReportsTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->signIn();

        $transport = $this->category($this->user, 'Transport');
        $food      = $this->category($this->user, 'Food & Drink');
        $transport->update(['budget_minor' => 50000]);
        $food->update(['budget_minor' => 100000]);

        $this->expense(45000, $transport->id, '2026-09-27');
        $this->expense(120000, $food->id, '2026-09-28');
        $this->expense(30000, $transport->id, '2026-10-02');
        $this->expense(10000, $food->id, '2026-08-15');
        $this->income(5000000, '2026-09-01');
        $this->income(45000, '2026-10-02');

        $other = $this->makeUser(['email' => 'other@example.com']);
        AppExpense::create(['user_id' => $other->id, 'amount_minor' => 999, 'category_id' => $this->category($other)->id, 'spent_at' => '2026-09-29']);
    }

    private function expense(int $amount, int $categoryId, string $day): void
    {
        AppExpense::create(['user_id' => $this->user->id, 'amount_minor' => $amount, 'category_id' => $categoryId, 'spent_at' => $day]);
    }

    private function income(int $amount, string $day): void
    {
        AppIncome::create(['user_id' => $this->user->id, 'amount_minor' => $amount, 'source' => 'Salary', 'received_at' => $day]);
    }

    public function test_month_summary_compares_with_the_month_before(): void
    {
        $this->getJson('/api/v1/reports/month-summary?month=2026-09')->assertOk()->assertExactJson(['data' => [
            'month' => '2026-09', 'total_minor' => 165000, 'expense_count' => 2,
            'previous_month' => '2026-08', 'previous_total_minor' => 10000,
        ]]);
    }

    public function test_category_totals_list_every_category_biggest_first(): void
    {
        $rows = $this->getJson('/api/v1/reports/category-totals?month=2026-09')->assertOk()->json('data');

        $this->assertCount(8, $rows);
        $this->assertSame(['Food & Drink', 120000, 1, 100000], [$rows[0]['category_name'], $rows[0]['total_minor'], $rows[0]['count'], $rows[0]['budget_minor']]);
        $this->assertSame(['Transport', 45000, 1], [$rows[1]['category_name'], $rows[1]['total_minor'], $rows[1]['count']]);
        $this->assertSame(0, $rows[2]['total_minor']);
        $this->assertSame(
            ['category_id', 'category_name', 'category_icon', 'category_color', 'budget_minor', 'total_minor', 'count'],
            array_keys($rows[0]),
        );
    }

    public function test_months_are_zero_filled(): void
    {
        $this->getJson('/api/v1/reports/months?from=2026-07&to=2026-10')->assertOk()->assertExactJson(['data' => [
            ['month' => '2026-07', 'total_minor' => 0],
            ['month' => '2026-08', 'total_minor' => 10000],
            ['month' => '2026-09', 'total_minor' => 165000],
            ['month' => '2026-10', 'total_minor' => 30000],
        ]]);
    }

    public function test_flow_buckets_income_and_spending(): void
    {
        $monthly = $this->getJson('/api/v1/reports/flow?mode=monthly&count=3&today=2026-10-05')->assertOk()->json('data');
        $this->assertSame([
            ['key' => '2026-08', 'start' => '2026-08-01', 'end' => '2026-09-01', 'income_minor' => 0, 'expense_minor' => 10000],
            ['key' => '2026-09', 'start' => '2026-09-01', 'end' => '2026-10-01', 'income_minor' => 5000000, 'expense_minor' => 165000],
            ['key' => '2026-10', 'start' => '2026-10-01', 'end' => '2026-11-01', 'income_minor' => 45000, 'expense_minor' => 30000],
        ], $monthly);

        // Weeks start on Sunday.
        $weekly = $this->getJson('/api/v1/reports/flow?mode=weekly&count=2&today=2026-10-02')->assertOk()->json('data');
        $this->assertSame(['2026-09-20', '2026-09-27'], array_column($weekly, 'key'));
        $this->assertSame([0, 195000], array_column($weekly, 'expense_minor'));

        $yearly = $this->getJson('/api/v1/reports/flow?mode=yearly&count=1&today=2026-10-02')->assertOk()->json('data');
        $this->assertSame([['key' => '2026', 'start' => '2026-01-01', 'end' => '2027-01-01', 'income_minor' => 5045000, 'expense_minor' => 205000]], $yearly);
    }

    public function test_day_flow_lists_the_latest_active_days(): void
    {
        $rows = $this->getJson('/api/v1/reports/day-flow?limit=2')->assertOk()->json('data');

        // MySQL hands back a DATE; SQLite, which the tests run on, adds a time.
        $this->assertSame(['2026-10-02', '2026-09-28'], array_map(fn ($r) => substr($r['day'], 0, 10), $rows));
        $this->assertSame([[45000, 30000], [0, 120000]], array_map(fn ($r) => [$r['income_minor'], $r['expense_minor']], $rows));
    }

    public function test_lifetime_and_dashboard(): void
    {
        $this->getJson('/api/v1/reports/lifetime')->assertOk()->assertExactJson(['data' => ['income_minor' => 5045000, 'expense_minor' => 205000]]);

        $data = $this->getJson('/api/v1/dashboard?month=2026-09')->assertOk()->json('data');
        $this->assertSame('2026-09', $data['month']);
        $this->assertSame([120000, 45000], array_column($data['expenses'], 'amount_minor'));
        $this->assertSame(['total_minor' => 165000, 'expense_count' => 2, 'previous_total_minor' => 10000], $data['month_summary']);
        $this->assertSame(120000, $data['category_totals'][0]['total_minor']);
        $this->assertSame(['income_minor' => 5045000, 'expense_minor' => 205000], $data['lifetime']);
    }

    public function test_alerts_flag_budgets_over_and_near(): void
    {
        $this->expense(36000, $this->category($this->user, 'Transport')->id, '2026-10-03'); // 66000 of 50000

        $alerts = $this->getJson('/api/v1/alerts?month=2026-09')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([
            ['kind' => 'over', 'category_id' => $this->category($this->user, 'Food & Drink')->id, 'total_minor' => 120000, 'budget_minor' => 100000],
            ['kind' => 'near', 'category_id' => $this->category($this->user, 'Transport')->id, 'total_minor' => 45000, 'budget_minor' => 50000],
        ], $alerts);

        $this->assertSame([['kind' => 'over', 'category_id' => $this->category($this->user, 'Transport')->id, 'total_minor' => 66000, 'budget_minor' => 50000]],
            $this->getJson('/api/v1/alerts?month=2026-10')->json('data'));

        AppIncome::where('user_id', $this->user->id)->delete();
        $this->user->categories()->update(['budget_minor' => null]);
        $this->assertSame([['kind' => 'no-income'], ['kind' => 'no-budgets']], $this->getJson('/api/v1/alerts?month=2026-09')->json('data'));
    }
}
