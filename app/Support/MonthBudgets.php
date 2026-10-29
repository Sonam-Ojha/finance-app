<?php

namespace App\Support;

use App\Models\Budget;
use Illuminate\Support\Collection;

/**
 * The budgets that apply to one month, as the app reads them: a category's
 * budget for that month replaces its standing every-month budget_minor, and
 * the row with no category is the whole-month budget.
 */
class MonthBudgets
{
    /** NEARLY_SPENT in the app: at this share of a budget it starts warning. */
    public const NEAR_LIMIT = 0.8;

    private function __construct(
        public readonly ?Budget $whole,
        /** @var Collection<int, Budget> keyed by category_id */
        public readonly Collection $byCategory,
    ) {}

    public static function load(int $uid, string $month): self
    {
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $rows = Budget::where('user_id', $uid)->where('year', $year)->where('month', $monthNumber)->get();

        return new self(
            $rows->first(fn (Budget $b) => $b->category_id === null),
            $rows->filter(fn (Budget $b) => $b->category_id !== null)->keyBy('category_id'),
        );
    }

    /** The month's own budget for the category, else its standing one. */
    public function inForce(int $categoryId, ?int $standingMinor): ?int
    {
        return $this->byCategory->get($categoryId)?->amount_minor ?? $standingMinor;
    }

    public function wholeMinor(): ?int
    {
        return $this->whole?->amount_minor;
    }
}
