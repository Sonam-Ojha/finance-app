<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Budget;
use App\Models\User;
use App\Support\MonthReport;
use App\Support\TransactionCsv;
use Carbon\Carbon;

/** GET /api/v1/export/transactions.csv and /api/v1/export/report.pdf */
class ExportsTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Thursday 29 October 2026, 3 p.m. in India.
        $this->travelTo(Carbon::parse('2026-10-29 15:00', 'Asia/Kolkata'));
        $this->user = $this->signIn(['name' => 'Asha Rao']);

        $this->spend(45000, 'Transport', '2026-10-28', ['note' => 'Auto to office', 'payment_method' => 'upi']);
        $this->spend(120000, 'Food & Drink', '2026-10-05', ['note' => '=SUM(A1:A9)', 'payment_method' => 'credit_card']);
        $this->spend(9900, 'Food & Drink', '2026-09-30');
        AppIncome::create(['user_id' => $this->user->id, 'amount_minor' => 5000000, 'source' => 'Salary', 'received_at' => '2026-10-01', 'payment_method' => 'bank_transfer']);

        // Someone else's entries never show up.
        $other = $this->makeUser(['email' => 'other@example.com']);
        AppExpense::create(['user_id' => $other->id, 'amount_minor' => 777700, 'category_id' => $this->category($other)->id, 'spent_at' => '2026-10-10', 'note' => 'Not yours']);
        AppIncome::create(['user_id' => $other->id, 'amount_minor' => 888800, 'source' => 'Bonus', 'received_at' => '2026-10-10']);
    }

    private function spend(int $amount, string $category, string $day, array $extra = []): AppExpense
    {
        return AppExpense::create($extra + [
            'user_id' => $this->user->id, 'amount_minor' => $amount,
            'category_id' => $this->category($this->user, $category)->id, 'spent_at' => $day,
        ]);
    }

    /** The CSV's lines as arrays, header first, without the byte-order mark. */
    private function csv(string $query = ''): array
    {
        $response = $this->get('/api/v1/export/transactions.csv' . ($query ? "?{$query}" : ''))->assertOk();
        $body = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);

        return array_map('str_getcsv', explode("\n", trim(substr($body, 3))));
    }

    public function test_the_csv_holds_this_months_entries_by_default(): void
    {
        $response = $this->get('/api/v1/export/transactions.csv')->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="transactions-2026-10-01-to-2026-10-31.csv"', $response->headers->get('Content-Disposition'));

        $this->assertSame([
            ['Date', 'Type', 'Category or Source', 'Note', 'Payment method', 'Amount (INR)'],
            ['2026-10-28', 'Expense', 'Transport', 'Auto to office', 'UPI', '450.00'],
            ['2026-10-05', 'Expense', 'Food & Drink', "'=SUM(A1:A9)", 'Credit card', '1200.00'],
            ['2026-10-01', 'Income', 'Salary', '', 'Bank transfer', '50000.00'],
        ], $this->csv());
    }

    public function test_a_date_range_picks_the_rows_and_open_ends_are_allowed(): void
    {
        $rows = $this->csv('from=2026-09-01&to=2026-10-04');
        $this->assertSame([['2026-10-01', 'Income'], ['2026-09-30', 'Expense']], array_map(fn ($r) => [$r[0], $r[1]], array_slice($rows, 1)));

        // From a day on, to today; up to a day, from the start.
        $this->assertCount(3, $this->csv('from=2026-10-02')); // header + 2
        $this->assertCount(2, $this->csv('to=2026-09-30'));   // header + 1
        $this->get('/api/v1/export/transactions.csv?to=2026-09-30')
            ->assertHeader('Content-Disposition', 'attachment; filename="transactions-start-to-2026-09-30.csv"');
    }

    public function test_only_the_users_own_entries_are_exported(): void
    {
        $body = $this->get('/api/v1/export/transactions.csv?from=2026-01-01&to=2026-12-31')->assertOk()->getContent();

        $this->assertStringNotContainsString('Not yours', $body);
        $this->assertStringNotContainsString('7777.00', $body);
        $this->assertStringNotContainsString('Bonus', $body);
    }

    public function test_bad_dates_are_refused_with_a_sentence(): void
    {
        $this->getJson('/api/v1/export/transactions.csv?from=2026-10-10&to=2026-10-01')
            ->assertStatus(422)->assertJsonPath('errors.to.0', 'The end date cannot be before the start date.');
        $this->getJson('/api/v1/export/transactions.csv?from=10-10-2026')
            ->assertStatus(422)->assertJsonPath('errors.from.0', 'Pick a start date like 2026-10-01.');
    }

    public function test_the_monthly_report_is_a_pdf(): void
    {
        $response = $this->get('/api/v1/export/report.pdf?month=2026-10')->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="expense-report-2026-10.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        // Without a month: this one, in India time.
        $this->get('/api/v1/export/report.pdf')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="expense-report-2026-10.pdf"');

        // An empty month still makes a report.
        $this->get('/api/v1/export/report.pdf?month=2025-01')->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->getJson('/api/v1/export/report.pdf?month=2026-13')
            ->assertStatus(422)->assertJsonPath('errors.month.0', 'Pick a month like 2026-10.');
    }

    public function test_the_report_adds_up_the_month(): void
    {
        $food = $this->category($this->user, 'Food & Drink');
        Budget::create(['user_id' => $this->user->id, 'category_id' => $food->id, 'year' => 2026, 'month' => 10, 'amount_minor' => 100000]);
        $this->category($this->user, 'Transport')->update(['budget_minor' => 50000]); // every month
        Budget::create(['user_id' => $this->user->id, 'category_id' => null, 'year' => 2026, 'month' => 10, 'amount_minor' => 10000000]);

        $data = MonthReport::data($this->user->fresh(), '2026-10');

        $this->assertSame('October 2026', $data['monthLabel']);
        $this->assertSame('₹50,000.00', $data['received']);
        $this->assertSame('₹1,650.00', $data['spent']);
        $this->assertSame('₹48,350.00', $data['saved']);
        $this->assertFalse($data['overspent']);

        $this->assertSame(['Food & Drink', 'Transport'], $data['breakdown']->pluck('name')->all());
        $this->assertSame([72.7, 27.3], $data['breakdown']->pluck('share')->all());

        $budgets = collect($data['budgets'])->keyBy('name');
        $this->assertSame('ok', $budgets['Whole month']['status']);
        $this->assertSame('over', $budgets['Food & Drink']['status']);
        $this->assertSame('₹200.00 over', $budgets['Food & Drink']['remainder']);
        $this->assertSame('near', $budgets['Transport']['status']);

        // Every entry of the month, oldest first; nobody else's.
        $this->assertSame(['+₹50,000.00', '−₹1,200.00', '−₹450.00'], $data['entries']->pluck('amount')->all());
    }

    public function test_exports_need_a_signed_in_user(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('Accept', 'application/json');

        $this->get('/api/v1/export/transactions.csv')->assertUnauthorized();
        $this->get('/api/v1/export/report.pdf')->assertUnauthorized();
    }

    public function test_the_csv_escapes_formulas_and_quotes(): void
    {
        $csv = TransactionCsv::build(collect([[
            'type' => 'expense', 'date' => '2026-10-01', 'category_name' => 'Food, "fancy"', 'source' => null,
            'note' => '@cmd', 'payment_method' => null, 'amount_minor' => 5,
        ]]));

        $this->assertStringContainsString('2026-10-01,Expense,"Food, ""fancy""",\'@cmd,,0.05', $csv);
    }
}
