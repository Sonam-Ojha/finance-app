<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\User;

class TransactionsTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->signIn();

        $transport = $this->category($this->user, 'Transport')->id;
        $food      = $this->category($this->user, 'Food & Drink')->id;

        $this->expense(45000, $transport, '2026-09-27', 'Auto to office', 'upi');
        $this->expense(120000, $food, '2026-09-28', 'Dinner 50% off', 'credit_card');
        $this->expense(45000, $food, '2026-09-30', null, null);
        $this->income(5000000, 'Salary', '2026-09-01', null, 'bank_transfer');
        $this->income(45000, 'Refund', '2026-09-30', 'Office auto', 'cash');

        // Someone else's rows never show up.
        $other = $this->makeUser(['email' => 'other@example.com']);
        AppExpense::create(['user_id' => $other->id, 'amount_minor' => 999, 'category_id' => $this->category($other)->id, 'spent_at' => '2026-09-29']);
    }

    private function expense(int $amount, int $categoryId, string $day, ?string $note, ?string $method): void
    {
        AppExpense::create(['user_id' => $this->user->id, 'amount_minor' => $amount, 'category_id' => $categoryId, 'note' => $note, 'payment_method' => $method, 'spent_at' => $day]);
    }

    private function income(int $amount, string $source, string $day, ?string $note, ?string $method): void
    {
        AppIncome::create(['user_id' => $this->user->id, 'amount_minor' => $amount, 'source' => $source, 'note' => $note, 'payment_method' => $method, 'received_at' => $day]);
    }

    /** "type:amount" per row, in order, for compact assertions. */
    private function rows(string $query = ''): array
    {
        return collect($this->getJson('/api/v1/transactions' . ($query ? "?{$query}" : ''))->assertOk()->json('data'))
            ->map(fn ($row) => "{$row['type']}:{$row['amount_minor']}")
            ->all();
    }

    public function test_it_lists_both_kinds_newest_first_with_the_documented_shape(): void
    {
        $response = $this->getJson('/api/v1/transactions')->assertOk();

        $response->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.last_page', 1);

        $this->assertSame(
            ['type', 'id', 'amount_minor', 'date', 'note', 'payment_method', 'category_id', 'category_name', 'category_icon', 'category_color', 'source', 'has_receipt'],
            array_keys($response->json('data.0')),
        );

        $first = $response->json('data.0');
        $this->assertSame('2026-09-30', $first['date']);
        $this->assertCount(5, $response->json('data'));
        $this->assertSame('income', $response->json('data.4.type'));
        $this->assertSame('Salary', $response->json('data.4.source'));
        $this->assertNull($response->json('data.4.category_id'));
    }

    public function test_filters_narrow_the_list(): void
    {
        $this->assertSame(['expense:45000', 'expense:120000', 'expense:45000'], $this->rows('type=expense'));
        $this->assertCount(2, $this->rows('type=income'));

        // A category means expenses only, even when the type says otherwise.
        $food = $this->category($this->user, 'Food & Drink')->id;
        $this->assertSame(['expense:45000', 'expense:120000'], $this->rows("category_id={$food}&type=income"));

        $this->assertSame(['income:5000000'], $this->rows('payment_method=bank_transfer'));

        // Both ends inclusive.
        $this->assertSame(['expense:120000', 'expense:45000'], $this->rows('date_from=2026-09-27&date_to=2026-09-28'));
        $this->assertCount(3, $this->rows('date_to=2026-09-28'));

        $this->assertSame(['expense:120000'], $this->rows('min_amount_minor=100000&max_amount_minor=200000'));
    }

    public function test_search_matches_category_source_note_and_method_without_case(): void
    {
        $this->assertSame(['expense:45000'], $this->rows('search=TRANSPORT'));
        $this->assertSame(['income:45000'], $this->rows('search=refund'));
        $this->assertSame(['income:45000', 'expense:45000'], $this->rows('search=auto'));
        $this->assertSame(['expense:120000'], $this->rows('search=credit'));
        // A % in the term is a character to find, not a wildcard: only the
        // one note that contains it matches.
        $this->assertSame(['expense:120000'], $this->rows('search=' . urlencode('50%')));
        $this->assertSame(['expense:120000'], $this->rows('search=' . urlencode('%')));
    }

    public function test_every_sort_with_its_tie_breaks(): void
    {
        // Same day: newest puts the higher id first, oldest the lower.
        $this->assertSame(
            ['expense:45000', 'income:45000', 'expense:120000', 'expense:45000', 'income:5000000'],
            $this->rows('sort=newest'),
        );
        $this->assertSame(
            ['income:5000000', 'expense:45000', 'expense:120000', 'income:45000', 'expense:45000'],
            $this->rows('sort=oldest'),
        );
        // Equal amounts fall back to the newer day, then the higher id.
        $this->assertSame(
            ['income:5000000', 'expense:120000', 'expense:45000', 'income:45000', 'expense:45000'],
            $this->rows('sort=highest'),
        );
        $this->assertSame(
            ['expense:45000', 'income:45000', 'expense:45000', 'expense:120000', 'income:5000000'],
            $this->rows('sort=lowest'),
        );
    }

    public function test_paging(): void
    {
        $this->getJson('/api/v1/transactions?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_bad_parameters_are_rejected(): void
    {
        $this->getJson('/api/v1/transactions?per_page=101')->assertUnprocessable();
        $this->getJson('/api/v1/transactions?sort=random')->assertUnprocessable();
        $this->getJson('/api/v1/transactions?type=transfer')->assertUnprocessable();
        $this->getJson('/api/v1/transactions?date_from=2026-09-30&date_to=2026-09-01')->assertUnprocessable();
        $this->getJson('/api/v1/transactions?payment_method=cheque')->assertUnprocessable();

        $foreign = $this->category(User::where('email', 'other@example.com')->first())->id;
        $this->getJson("/api/v1/transactions?category_id={$foreign}")->assertUnprocessable();
    }
}
