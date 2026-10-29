<?php

namespace App\Console\Commands;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Support\BudgetAlertPush;
use App\Support\Labels;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Adds each recurring expense and income on its day (India time). Days the
 * server missed are caught up — a rent that came due while it was down
 * still happened — up to a year's worth per rule.
 */
class PostRecurringEntries extends Command
{
    protected $signature = 'recurring:post';

    protected $description = 'Add the recurring expenses and incomes that are due';

    public const MAX_CATCH_UP = 12;

    public function handle(): int
    {
        // Compared as dates: a stored day and India's "today" are in different time zones.
        $today = Labels::now();
        $todayDate = $today->toDateString();
        $posted = 0;

        RecurringEntry::with('category')
            ->where('active', true)
            ->whereDate('next_on', '<=', $todayDate)
            ->each(function (RecurringEntry $rule) use ($today, $todayDate, &$posted) {
                $months = [];
                for ($i = 0; $i < self::MAX_CATCH_UP && $rule->next_on->toDateString() <= $todayDate; $i++) {
                    $on = $rule->next_on->copy();
                    DB::transaction(function () use ($rule, $on) {
                        $this->post($rule, $on);
                        $rule->update(['last_on' => $on, 'next_on' => $rule->after($on)]);
                    });
                    $months[$on->format('Y-m')] = $on;
                    $posted++;
                }
                // A budget alert for this month, if a recurring expense just crossed a line.
                $thisMonth = $today->format('Y-m');
                if ($rule->kind === 'expense' && isset($months[$thisMonth]) && $rule->user && $rule->user->budget_alert_push !== false) {
                    BudgetAlertPush::check($rule->user, $thisMonth);
                }
            });

        $this->info("Posted {$posted} recurring " . ($posted === 1 ? 'entry' : 'entries') . '.');

        return self::SUCCESS;
    }

    private function post(RecurringEntry $rule, $on): void
    {
        $shared = [
            'user_id'            => $rule->user_id,
            'amount_minor'       => $rule->amount_minor,
            'note'               => $rule->note,
            'payment_method'     => $rule->payment_method,
            'recurring_entry_id' => $rule->id,
        ];

        if ($rule->kind === 'income') {
            AppIncome::create($shared + ['source' => $rule->source ?: 'Other', 'received_at' => $on->toDateString()]);

            return;
        }

        // Its category was deleted: the user's first one keeps the money counted.
        $categoryId = $rule->category_id
            ?? Category::where('user_id', $rule->user_id)->orderBy('sort_order')->orderBy('id')->value('id');
        AppExpense::create($shared + ['category_id' => $categoryId, 'spent_at' => $on->toDateString()]);
    }
}
