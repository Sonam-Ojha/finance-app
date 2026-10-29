<?php

namespace Tests\Feature\Tracker;

use App\Models\AppExpense;
use App\Models\SavingsGoal;
use App\Support\Goals;
use App\Support\Receipts;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ReceiptsTest;

/** The website's Export menu, receipt photos on the expense form, and the Goals pages. */
class ExportsReceiptsAndGoalsTest extends TrackerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Thursday 29 October 2026, 3 p.m. in India.
        $this->travelTo(Carbon::parse('2026-10-29 15:00', 'Asia/Kolkata'));
        Storage::fake(Receipts::DISK);
    }

    private function photo(): string
    {
        return 'data:image/png;base64,' . ReceiptsTest::PNG;
    }

    // ── Export ──

    public function test_the_transactions_page_offers_the_exports(): void
    {
        $this->get('/transactions?month=2026-10&type=expense')->assertOk()
            ->assertSee('Export')
            ->assertSee('CSV (current filters)')
            ->assertSee('Monthly report PDF')
            ->assertSee(route('transactions.export', ['month' => '2026-10', 'type' => 'expense']))
            ->assertSee(route('reports.pdf'));
    }

    public function test_the_csv_follows_the_lists_filters(): void
    {
        $this->expense(45000, 'Transport', '2026-10-28', ['note' => 'Auto']);
        $this->expense(9900, 'Food & Drink', '2026-09-30', ['note' => 'Last month']);
        $this->income(5000000, 'Salary', '2026-10-01');
        $other = $this->makeUser(['email' => 'other@example.com']);
        AppExpense::create(['user_id' => $other->id, 'amount_minor' => 777700, 'category_id' => $this->category('Transport', $other)->id, 'spent_at' => '2026-10-10', 'note' => 'Not yours']);

        $response = $this->get('/transactions/export.csv?month=2026-10')->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="transactions-2026-10-01-to-2026-10-31.csv"', $response->headers->get('Content-Disposition'));
        $body = $response->getContent();
        $this->assertStringContainsString("Date,Type,\"Category or Source\",Note,\"Payment method\",\"Amount (INR)\"", $body);
        $this->assertStringContainsString('2026-10-28,Expense,Transport,Auto,,450.00', $body);
        $this->assertStringContainsString('2026-10-01,Income,Salary,,,50000.00', $body);
        $this->assertStringNotContainsString('Last month', $body);
        $this->assertStringNotContainsString('Not yours', $body);

        $expensesOnly = $this->get('/transactions/export.csv?month=2026-10&type=expense')->getContent();
        $this->assertStringContainsString('Auto', $expensesOnly);
        $this->assertStringNotContainsString('Salary', $expensesOnly);

        $range = $this->get('/transactions/export.csv?from=2026-09-01&to=2026-10-31&search=last')->getContent();
        $this->assertStringContainsString('Last month', $range);
        $this->assertStringNotContainsString('Auto', $range);
    }

    public function test_the_monthly_report_downloads_as_a_pdf(): void
    {
        $this->expense(45000, 'Transport', '2026-10-28');

        $response = $this->get('/reports/monthly.pdf?month=2026-10')->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="expense-report-2026-10.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        // A future or broken month is this month's report.
        $this->get('/reports/monthly.pdf?month=2030-01')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="expense-report-2026-10.pdf"');
    }

    // ── Receipts ──

    public function test_a_receipt_is_added_with_a_new_expense_and_shown_to_its_owner_only(): void
    {
        $this->post('/expenses', [
            'amount' => '450', 'category_id' => $this->category()->id, 'spent_at' => '2026-10-29', 'receipt' => $this->photo(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $expense = AppExpense::latest('id')->first();
        $this->assertTrue($expense->has_receipt);
        Storage::disk(Receipts::DISK)->assertExists($expense->receipt_path);

        $photo = $this->get("/expenses/{$expense->id}/receipt")->assertOk();
        $this->assertSame('image/png', $photo->headers->get('Content-Type'));
        $this->assertSame(base64_decode(ReceiptsTest::PNG), $photo->streamedContent());

        // The list marks it, and the form shows it.
        $this->get('/transactions')->assertOk()->assertSee('🧾 Receipt');
        $this->get('/dashboard')->assertOk()->assertSee('🧾 Receipt');
        $this->get("/expenses/{$expense->id}/edit")->assertOk()->assertSee(str_replace('/', '\/', Receipts::url($expense)), false)->assertSee('Remove');

        $other = $this->makeUser(['email' => 'other@example.com']);
        $this->actingAs($other)->get("/expenses/{$expense->id}/receipt")->assertNotFound();
    }

    public function test_a_receipt_is_replaced_and_removed_from_the_edit_form(): void
    {
        $expense = $this->expense(45000);
        $form = ['amount' => '450.00', 'category_id' => $this->category()->id, 'spent_at' => $expense->spent_at->toDateString()];

        $this->put("/expenses/{$expense->id}", $form + ['receipt' => $this->photo()])->assertSessionHasNoErrors();
        $first = $expense->fresh()->receipt_path;
        $this->put("/expenses/{$expense->id}", $form + ['receipt' => $this->photo()])->assertSessionHasNoErrors();
        $second = $expense->fresh()->receipt_path;
        Storage::disk(Receipts::DISK)->assertMissing($first);
        Storage::disk(Receipts::DISK)->assertExists($second);

        // Saving without touching it keeps it.
        $this->put("/expenses/{$expense->id}", $form)->assertSessionHasNoErrors();
        $this->assertSame($second, $expense->fresh()->receipt_path);

        $this->put("/expenses/{$expense->id}", $form + ['remove_receipt' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($expense->fresh()->receipt_path);
        Storage::disk(Receipts::DISK)->assertMissing($second);
    }

    public function test_a_bad_receipt_saves_nothing(): void
    {
        $this->from('/expenses/create')->post('/expenses', [
            'amount' => '450', 'category_id' => $this->category()->id, 'spent_at' => '2026-10-29',
            'receipt' => 'data:image/png;base64,' . base64_encode('not a picture'),
        ])->assertRedirect('/expenses/create')
            ->assertSessionHasErrors(['receipt' => 'Could not use that receipt. Pick a JPEG, PNG or WebP image under 5 MB.']);

        $this->assertSame(0, AppExpense::where('user_id', $this->user->id)->count());
        // The photo is never kept in the session.
        $this->assertArrayNotHasKey('receipt', session()->getOldInput());
        $this->assertSame('450', session()->getOldInput('amount'));
    }

    public function test_deleting_an_expense_deletes_its_receipt(): void
    {
        $expense = $this->expense(45000);
        Receipts::store($expense, $this->photo());
        $path = $expense->receipt_path;

        $this->delete("/expenses/{$expense->id}")->assertRedirect();

        Storage::disk(Receipts::DISK)->assertMissing($path);
    }

    // ── Goals ──

    private function makeGoal(array $attributes = []): SavingsGoal
    {
        return Goals::create($this->user, $attributes + [
            'name' => 'Goa trip', 'emoji' => '🏖️', 'color' => '#199CDA', 'target_minor' => 2000000, 'deadline' => '2027-03-31',
        ]);
    }

    public function test_the_goals_pages_open_and_goals_sit_in_the_menu(): void
    {
        $this->get('/goals')->assertOk()->assertSee('No goals yet');
        $goal = $this->makeGoal();
        Goals::contribute($goal, 1200000, 'Bonus', '2026-10-20');

        $this->get('/goals')->assertOk()
            ->assertSee('Goa trip')
            ->assertSee('₹12,000.00</span> of ₹20,000.00 · 60%', false)
            // ₹8,000 left over five months.
            ->assertSee('₹1,600.00 per month')
            ->assertSee('to reach it by 31 Mar 2027')
            ->assertSee('Add money')
            ->assertSee('Withdraw');
        $this->get("/goals/{$goal->id}")->assertOk()->assertSee('History')->assertSee('Bonus')->assertSee('+₹12,000.00');
        $this->get('/goals/create')->assertOk()->assertSee('New goal');
        $this->get("/goals/{$goal->id}/edit")->assertOk()->assertSee('Edit goal')->assertSee('20000.00');

        $this->get('/dashboard')->assertSee(route('goals.index'), false)->assertSee('Goals');
    }

    public function test_a_goal_is_created_and_edited_from_the_form(): void
    {
        $this->post('/goals', ['name' => 'New laptop', 'emoji' => '💻', 'color' => '#283897', 'target' => '80,000', 'deadline' => '2027-06-30'])
            ->assertRedirect('/goals')->assertSessionHas('success', 'Goal “New laptop” created');

        $goal = SavingsGoal::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame(8000000, $goal->target_minor);
        $this->assertSame('2027-06-30', $goal->deadline->toDateString());

        $this->put("/goals/{$goal->id}", ['name' => 'Laptop', 'emoji' => '💻', 'color' => '#283897', 'target' => '80000.00', 'deadline' => ''])
            ->assertRedirect('/goals');
        $goal->refresh();
        $this->assertSame('Laptop', $goal->name);
        $this->assertSame(8000000, $goal->target_minor);
        $this->assertNull($goal->deadline);

        $this->from('/goals/create')->post('/goals', ['name' => '', 'emoji' => '💻', 'color' => '#283897', 'target' => '0', 'deadline' => '2026-01-01'])
            ->assertRedirect('/goals/create')
            ->assertSessionHasErrors([
                'name' => 'Give the goal a name.',
                'target' => 'Enter a target greater than zero.',
                'deadline' => 'Pick a deadline that is today or later.',
            ]);
    }

    public function test_money_is_added_and_withdrawn_from_a_card(): void
    {
        $goal = $this->makeGoal();

        $this->from('/goals')->post("/goals/{$goal->id}/contributions", ['goal_id' => $goal->id, 'direction' => 'add', 'amount' => '5,000', 'note' => 'Salary day'])
            ->assertRedirect('/goals')->assertSessionHas('success', '₹5,000.00 added to Goa trip');
        $this->assertSame(500000, $goal->fresh()->saved_minor);

        $this->from('/goals')->post("/goals/{$goal->id}/contributions", ['goal_id' => $goal->id, 'direction' => 'withdraw', 'amount' => '6000'])
            ->assertRedirect('/goals')
            ->assertSessionHasErrors(['amount' => 'You can withdraw at most ₹5,000.00 — that is all this goal holds.']);
        $this->assertSame(500000, $goal->fresh()->saved_minor);

        $this->from('/goals')->post("/goals/{$goal->id}/contributions", ['goal_id' => $goal->id, 'direction' => 'withdraw', 'amount' => '1000'])
            ->assertSessionHas('success', '₹1,000.00 withdrawn from Goa trip');
        $this->assertSame(400000, $goal->fresh()->saved_minor);

        $this->from('/goals')->post("/goals/{$goal->id}/contributions", ['goal_id' => $goal->id, 'direction' => 'add', 'amount' => 'abc'])
            ->assertSessionHasErrors(['amount' => 'Enter an amount greater than zero.']);
    }

    public function test_reaching_the_target_is_celebrated(): void
    {
        $goal = $this->makeGoal(['target_minor' => 100000]);

        $this->from('/goals')->post("/goals/{$goal->id}/contributions", ['direction' => 'add', 'amount' => '1000'])
            ->assertSessionHas('success', '🎉 You reached your goal “Goa trip”!');

        $this->assertNotNull($goal->fresh()->reached_at);
        $this->get('/goals')->assertSee('🎉 Reached');
    }

    public function test_a_goal_is_archived_brought_back_and_deleted(): void
    {
        $goal = $this->makeGoal();
        Goals::contribute($goal, 1000, null, '2026-10-29');

        $this->patch("/goals/{$goal->id}/archive")->assertRedirect('/goals')->assertSessionHas('success', 'Goal “Goa trip” archived');
        $this->assertTrue($goal->fresh()->isArchived());
        $this->get('/goals')->assertSee('Archived (1)')->assertSee('No active goals');

        $this->patch("/goals/{$goal->id}/archive")->assertSessionHas('success', 'Goal “Goa trip” is active again');
        $this->assertFalse($goal->fresh()->isArchived());

        $this->delete("/goals/{$goal->id}")->assertRedirect('/goals');
        $this->assertNull(SavingsGoal::find($goal->id));
        $this->assertDatabaseMissing('goal_contributions', ['goal_id' => $goal->id]);
    }

    public function test_someone_elses_goal_is_not_found(): void
    {
        $other = $this->makeUser(['email' => 'other@example.com']);
        $theirs = Goals::create($other, ['name' => 'Theirs', 'emoji' => '🎯', 'color' => '#DA2020', 'target_minor' => 100]);

        $this->get("/goals/{$theirs->id}")->assertNotFound();
        $this->get("/goals/{$theirs->id}/edit")->assertNotFound();
        $this->put("/goals/{$theirs->id}", ['name' => 'Mine', 'emoji' => '🎯', 'color' => '#DA2020', 'target' => '1'])->assertNotFound();
        $this->patch("/goals/{$theirs->id}/archive")->assertNotFound();
        $this->post("/goals/{$theirs->id}/contributions", ['direction' => 'add', 'amount' => '1'])->assertNotFound();
        $this->delete("/goals/{$theirs->id}")->assertNotFound();
        $this->get('/goals')->assertDontSee('Theirs');

        $this->assertSame('Theirs', $theirs->fresh()->name);
        $this->assertSame(0, $theirs->fresh()->saved_minor);
    }
}
