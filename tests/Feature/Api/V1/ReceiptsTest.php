<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppExpense;
use App\Models\User;
use App\Support\Receipts;
use Illuminate\Support\Facades\Storage;

/** Receipt photos on expenses: POST/GET/DELETE /api/v1/expenses/{id}/receipt and has_receipt. */
class ReceiptsTest extends ApiTestCase
{
    /** A 1×1 PNG. */
    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** A 1×1 GIF: a real image, but not a type receipts take. */
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Files land on a throwaway disk, never in storage/app/receipts.
        Storage::fake(Receipts::DISK);
        $this->user = $this->signIn();
    }

    private function expense(?User $owner = null): AppExpense
    {
        $owner ??= $this->user;

        return AppExpense::create([
            'user_id' => $owner->id, 'amount_minor' => 45000,
            'category_id' => $this->category($owner)->id, 'spent_at' => now()->toDateString(),
        ]);
    }

    private function upload(AppExpense $expense, string $base64 = self::PNG, string $type = 'image/png')
    {
        return $this->postJson("/api/v1/expenses/{$expense->id}/receipt", ['receipt' => "data:{$type};base64,{$base64}"]);
    }

    public function test_a_receipt_is_stored_privately_and_served_to_its_owner(): void
    {
        $expense = $this->expense();
        $this->getJson("/api/v1/expenses/{$expense->id}")->assertJsonPath('data.has_receipt', false);

        $this->upload($expense)->assertOk()->assertExactJson(['data' => ['has_receipt' => true]]);

        $path = $expense->fresh()->receipt_path;
        $this->assertMatchesRegularExpression("#^{$this->user->id}/{$expense->id}-[a-z0-9]{10}\\.png$#", $path);
        Storage::disk(Receipts::DISK)->assertExists($path);
        $this->assertFileDoesNotExist(public_path($path));

        $photo = $this->get("/api/v1/expenses/{$expense->id}/receipt")->assertOk();
        $this->assertSame('image/png', $photo->headers->get('Content-Type'));
        $this->assertSame(base64_decode(self::PNG), $photo->streamedContent());

        // Every expense object says so.
        $this->getJson("/api/v1/expenses/{$expense->id}")->assertJsonPath('data.has_receipt', true);
        $this->getJson('/api/v1/expenses?month=' . now()->format('Y-m'))->assertJsonPath('data.0.has_receipt', true);
        $this->getJson('/api/v1/transactions')->assertJsonPath('data.0.has_receipt', true);
    }

    public function test_has_receipt_is_on_every_expense_object(): void
    {
        $transport = $this->category($this->user)->id;
        $created = $this->postJson('/api/v1/expenses', ['amount_minor' => 100, 'category_id' => $transport, 'spent_at' => now()->toDateString()])
            ->assertCreated()->assertJsonPath('data.has_receipt', false)->json('data.id');
        $this->upload(AppExpense::find($created))->assertOk();

        // An edit keeps the photo, and says so.
        $this->putJson("/api/v1/expenses/{$created}", ['amount_minor' => 200, 'category_id' => $transport, 'spent_at' => now()->toDateString()])
            ->assertOk()->assertJsonPath('data.has_receipt', true);

        // Incomes in the transactions list carry it too, always false.
        $this->postJson('/api/v1/incomes', ['amount_minor' => 100, 'source' => 'Salary', 'received_at' => now()->toDateString()])->assertCreated();
        $rows = collect($this->getJson('/api/v1/transactions')->json('data'))->keyBy('type');
        $this->assertTrue($rows['expense']['has_receipt']);
        $this->assertFalse($rows['income']['has_receipt']);
    }

    public function test_a_new_receipt_replaces_the_old_file(): void
    {
        $expense = $this->expense();
        $this->upload($expense)->assertOk();
        $first = $expense->fresh()->receipt_path;

        $this->upload($expense)->assertOk();
        $second = $expense->fresh()->receipt_path;

        $this->assertNotSame($first, $second);
        Storage::disk(Receipts::DISK)->assertMissing($first);
        Storage::disk(Receipts::DISK)->assertExists($second);
        $this->assertCount(1, Storage::disk(Receipts::DISK)->allFiles());
    }

    public function test_a_receipt_can_be_removed(): void
    {
        $expense = $this->expense();
        $this->upload($expense)->assertOk();
        $path = $expense->fresh()->receipt_path;

        $this->deleteJson("/api/v1/expenses/{$expense->id}/receipt")->assertNoContent();

        $this->assertNull($expense->fresh()->receipt_path);
        Storage::disk(Receipts::DISK)->assertMissing($path);
        $this->getJson("/api/v1/expenses/{$expense->id}")->assertJsonPath('data.has_receipt', false);
        $this->get("/api/v1/expenses/{$expense->id}/receipt")->assertNotFound();
        // Removing one that is not there is fine.
        $this->deleteJson("/api/v1/expenses/{$expense->id}/receipt")->assertNoContent();
    }

    public function test_an_expense_without_a_receipt_has_none_to_read(): void
    {
        $this->getJson('/api/v1/expenses/' . $this->expense()->id . '/receipt')->assertNotFound();
    }

    public function test_someone_elses_expense_is_not_found(): void
    {
        $other = $this->makeUser(['email' => 'other@example.com']);
        $theirs = $this->expense($other);
        $path = "{$other->id}/{$theirs->id}-abcdefghij.png";
        Storage::disk(Receipts::DISK)->put($path, base64_decode(self::PNG));
        $theirs->receipt_path = $path;
        $theirs->save();

        $this->get("/api/v1/expenses/{$theirs->id}/receipt")->assertNotFound();
        $this->upload($theirs)->assertNotFound();
        $this->deleteJson("/api/v1/expenses/{$theirs->id}/receipt")->assertNotFound();

        $this->assertSame($path, $theirs->fresh()->receipt_path);
        Storage::disk(Receipts::DISK)->assertExists($path);
    }

    public function test_anything_but_a_jpeg_png_or_webp_under_5_mb_is_refused(): void
    {
        $expense = $this->expense();
        $this->upload($expense)->assertOk();
        $kept = $expense->fresh()->receipt_path;

        $tooBig = base64_encode(base64_decode(self::PNG) . str_repeat("\0", 5 * 1024 * 1024));
        foreach ([
            [base64_encode('not a picture'), 'image/jpeg'],
            [self::GIF, 'image/gif'],
            ['%%%not-base64%%%', 'image/png'],
            [$tooBig, 'image/png'],
        ] as [$data, $type]) {
            $this->upload($expense, $data, $type)
                ->assertStatus(422)
                ->assertJsonPath('errors.receipt.0', 'Could not use that receipt. Pick a JPEG, PNG or WebP image under 5 MB.');
        }

        $this->postJson("/api/v1/expenses/{$expense->id}/receipt", [])
            ->assertStatus(422)
            ->assertJsonPath('errors.receipt.0', 'Could not use that receipt. Pick a JPEG, PNG or WebP image under 5 MB.');

        // A refused photo never replaces a good one.
        $this->assertSame($kept, $expense->fresh()->receipt_path);
        Storage::disk(Receipts::DISK)->assertExists($kept);
    }

    public function test_deleting_the_expense_deletes_its_receipt(): void
    {
        $expense = $this->expense();
        $this->upload($expense)->assertOk();
        $path = $expense->fresh()->receipt_path;

        $this->deleteJson("/api/v1/expenses/{$expense->id}")->assertNoContent();

        Storage::disk(Receipts::DISK)->assertMissing($path);
    }

    public function test_deleting_the_account_deletes_every_receipt(): void
    {
        $user = $this->signIn(['email' => 'leaving@example.com', 'password' => 'secret123']);
        $this->user = $user;
        $this->upload($this->expense())->assertOk();
        $this->upload($this->expense())->assertOk();
        $this->assertCount(2, Storage::disk(Receipts::DISK)->allFiles((string) $user->id));

        $this->deleteJson('/api/v1/me', ['password' => 'secret123'])->assertNoContent();

        $this->assertSame([], Storage::disk(Receipts::DISK)->allFiles((string) $user->id));
    }
}
