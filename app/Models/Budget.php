<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A budget for one month: the whole month's spending when category_id is
 * NULL, otherwise one category's — overriding its standing budget_minor for
 * that month only.
 */
class Budget extends Model
{
    protected $fillable = ['user_id', 'category_id', 'year', 'month', 'amount_minor'];

    protected $casts = [
        'category_id'  => 'integer',
        'year'         => 'integer',
        'month'        => 'integer',
        'amount_minor' => 'integer',
    ];

    protected static function booted(): void
    {
        // The unique index reads this, not category_id — see the migration.
        static::saving(fn (Budget $budget) => $budget->category_key = $budget->category_id ?? 0);
    }

    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(Category::class); }

    /** "2026-09" */
    public function monthKey(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    /**
     * Calculated on every request, never stored, so adding, editing or
     * deleting an expense shows up in the very next read.
     */
    public function spentMinor(): int
    {
        $start = Carbon::create($this->year, $this->month, 1);

        return (int) AppExpense::where('user_id', $this->user_id)
            ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
            ->where('spent_at', '>=', $start->toDateString())
            ->where('spent_at', '<', $start->copy()->addMonth()->toDateString())
            ->sum('amount_minor');
    }

    public function toApiShape(): array
    {
        $spent = $this->spentMinor();

        return [
            'id'              => $this->id,
            'category_id'     => $this->category_id,
            'month'           => $this->monthKey(),
            'amount_minor'    => $this->amount_minor,
            'spent_minor'     => $spent,
            'remaining_minor' => $this->amount_minor - $spent,
            'percent_used'    => round($spent / $this->amount_minor * 100, 1),
            'is_over'         => $spent > $this->amount_minor,
        ];
    }
}
