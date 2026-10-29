<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The website now shows the app's expenses and incomes. Copies what was
 * recorded on the older website pages into the app's tables so it shows up
 * too. The originals stay where they are.
 *
 * Safe to run again (a fresh install, or after a rollback): an old entry
 * whose copy is already there — same user, amount, day and note — is
 * skipped, so nothing is doubled. Old entries that are exact twins of each
 * other are each copied once. Everything happens in one transaction.
 */
return new class extends Migration
{
    private const SOURCES = [
        'salary'         => 'Salary',
        'lic_commission' => 'LIC Commission',
        'business'       => 'Business',
        'received_from'  => 'Received from a person',
    ];

    /** The app's limits (categories.name, app_incomes.source, the notes). */
    private const NAME_MAX = 40;
    private const NOTE_MAX = 120;

    /** @var array<string, int> signature => copies already in the app's tables not yet matched to an old entry */
    private array $unmatched = [];

    public function up(): void
    {
        $this->unmatched = [];

        DB::transaction(function () {
            $now = now();

            foreach (DB::table('expenses')->orderBy('id')->get() as $old) {
                $row = [
                    'user_id'      => (int) $old->user_id,
                    'amount_minor' => (int) round($old->amount * 100),
                    'note'         => $this->note($old->description),
                    'spent_at'     => substr((string) $old->date, 0, 10),
                ];
                if ($this->alreadyCopied('app_expenses', 'spent_at', $row)) continue;

                $name = DB::table('expense_categories')->where('id', $old->expense_category_id)->value('name');
                DB::table('app_expenses')->insert($row + [
                    'category_id'    => $this->categoryFor($row['user_id'], (string) $name),
                    'payment_method' => $this->paymentMethod($old->payment_mode),
                    'created_at'     => $old->created_at ?? $now,
                    'updated_at'     => $now,
                ]);
            }

            foreach (DB::table('incomes')->orderBy('id')->get() as $old) {
                $row = [
                    'user_id'      => (int) $old->user_id,
                    'amount_minor' => (int) round($old->amount * 100),
                    'note'         => $this->note($old->note ?: $old->description),
                    'received_at'  => substr((string) $old->date, 0, 10),
                ];
                if ($this->alreadyCopied('app_incomes', 'received_at', $row)) continue;

                $source = trim((string) (self::SOURCES[$old->type] ?? $old->category_name));
                DB::table('app_incomes')->insert($row + [
                    'source'         => mb_substr($source !== '' ? $source : 'Other', 0, self::NAME_MAX),
                    'payment_method' => $this->paymentMethod($old->payment_mode),
                    'created_at'     => $old->created_at ?? $now,
                    'updated_at'     => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // The copies are ordinary entries now and may have been edited since.
    }

    /**
     * Whether this old entry's copy is already in $table. Each matching row
     * there accounts for one old entry, so n identical old entries with m
     * copies already made get n − m more — none on a second run.
     *
     * @param array{user_id: int, amount_minor: int, note: ?string} $row
     */
    private function alreadyCopied(string $table, string $dateColumn, array $row): bool
    {
        $key = implode("\x1F", [$table, $row['user_id'], $row['amount_minor'], $row[$dateColumn], $row['note'] ?? "\0"]);

        if (! array_key_exists($key, $this->unmatched)) {
            $this->unmatched[$key] = DB::table($table)
                ->where('user_id', $row['user_id'])
                ->where('amount_minor', $row['amount_minor'])
                ->whereDate($dateColumn, $row[$dateColumn])
                ->when($row['note'] === null, fn ($q) => $q->whereNull('note'), fn ($q) => $q->where('note', $row['note']))
                ->count();
        }
        if ($this->unmatched[$key] === 0) return false;

        $this->unmatched[$key]--;

        return true;
    }

    /**
     * The user's app category of that name, made if they have none. Names
     * are cut to the app's 40 characters first, so two long names that start
     * the same share one category instead of breaking the unique name.
     */
    private function categoryFor(int $userId, string $name): int
    {
        $name = mb_substr(trim($name), 0, self::NAME_MAX);
        $name = trim($name) !== '' ? trim($name) : 'Other';

        $existing = DB::table('categories')->where('user_id', $userId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');
        if ($existing) return (int) $existing;

        // Ignored when the database already counts the name as taken (MySQL
        // compares names without case or accents); that category is then used.
        DB::table('categories')->insertOrIgnore([
            'user_id'    => $userId,
            'name'       => $name,
            'icon'       => '🧾',
            'color'      => '#94A3B8',
            'sort_order' => (DB::table('categories')->where('user_id', $userId)->max('sort_order') ?? -1) + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('categories')->where('user_id', $userId)->where('name', $name)->value('id');
    }

    /** "Credit Card" → credit_card; anything unrecognised is "other". */
    private function paymentMethod(?string $mode): ?string
    {
        if ($mode === null || trim($mode) === '') return null;
        $key = str_replace([' ', '-'], '_', strtolower(trim($mode)));

        return in_array($key, ['cash', 'upi', 'credit_card', 'debit_card', 'bank_transfer'], true) ? $key : 'other';
    }

    private function note(?string $text): ?string
    {
        $text = trim((string) $text);
        return $text === '' ? null : mb_substr($text, 0, self::NOTE_MAX);
    }
};
