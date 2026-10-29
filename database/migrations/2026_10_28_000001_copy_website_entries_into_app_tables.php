<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The website now shows the app's expenses and incomes. Copies what was
 * recorded on the older website pages into the app's tables so it shows up
 * too. The originals stay where they are.
 */
return new class extends Migration
{
    private const SOURCES = [
        'salary'         => 'Salary',
        'lic_commission' => 'LIC Commission',
        'business'       => 'Business',
        'received_from'  => 'Received from a person',
    ];

    public function up(): void
    {
        $now = now();

        foreach (DB::table('expenses')->orderBy('id')->get() as $old) {
            $name = DB::table('expense_categories')->where('id', $old->expense_category_id)->value('name') ?: 'Other';

            DB::table('app_expenses')->insert([
                'user_id'        => $old->user_id,
                'amount_minor'   => (int) round($old->amount * 100),
                'category_id'    => $this->categoryFor($old->user_id, $name),
                'note'           => $this->note($old->description),
                'payment_method' => $this->paymentMethod($old->payment_mode),
                'spent_at'       => substr((string) $old->date, 0, 10),
                'created_at'     => $old->created_at ?? $now,
                'updated_at'     => $now,
            ]);
        }

        foreach (DB::table('incomes')->orderBy('id')->get() as $old) {
            DB::table('app_incomes')->insert([
                'user_id'        => $old->user_id,
                'amount_minor'   => (int) round($old->amount * 100),
                'source'         => mb_substr(self::SOURCES[$old->type] ?? ($old->category_name ?: 'Other'), 0, 40),
                'note'           => $this->note($old->note ?: $old->description),
                'payment_method' => $this->paymentMethod($old->payment_mode),
                'received_at'    => substr((string) $old->date, 0, 10),
                'created_at'     => $old->created_at ?? $now,
                'updated_at'     => $now,
            ]);
        }
    }

    public function down(): void
    {
        // The copies are ordinary entries now and may have been edited since.
    }

    /** The user's app category of that name, made if they have none. */
    private function categoryFor(int $userId, string $name): int
    {
        $existing = DB::table('categories')->where('user_id', $userId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');
        if ($existing) return $existing;

        return DB::table('categories')->insertGetId([
            'user_id'    => $userId,
            'name'       => mb_substr($name, 0, 40),
            'icon'       => '🧾',
            'color'      => '#94A3B8',
            'sort_order' => (DB::table('categories')->where('user_id', $userId)->max('sort_order') ?? -1) + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
        return $text === '' ? null : mb_substr($text, 0, 120);
    }
};
