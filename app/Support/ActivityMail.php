<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ActivityNotification;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Emails the user about something they just added. Sent after the response
 * has gone out, so a slow or failing mail server never delays or breaks the
 * save itself — and it needs no queue worker running on the server.
 *
 * Called from the controllers rather than from model events on purpose: the
 * one-time import creates hundreds of rows through the same models.
 */
class ActivityMail
{
    /*
     * The emails themselves, worded once for the API and the website alike.
     */

    public static function expenseAdded(User $user, \App\Models\AppExpense $expense, \App\Models\Category $category): void
    {
        self::send($user, 'Expense added: ' . Money::inr($expense->amount_minor), 'You added an expense.', [
            'Amount'    => Money::inr($expense->amount_minor),
            'Category'  => trim("{$category->icon} {$category->name}"),
            'Date'      => $expense->spent_at->format('j M Y'),
            'Paid with' => PaymentMethods::label($expense->payment_method),
            'Note'      => $expense->note,
        ]);
    }

    public static function incomeAdded(User $user, \App\Models\AppIncome $income): void
    {
        self::send($user, 'Income added: ' . Money::inr($income->amount_minor), 'You added an income.', [
            'Amount'        => Money::inr($income->amount_minor),
            'Source'        => $income->source,
            'Date'          => $income->received_at->format('j M Y'),
            'Received into' => PaymentMethods::label($income->payment_method),
            'Note'          => $income->note,
        ]);
    }

    public static function categoryAdded(User $user, \App\Models\Category $category): void
    {
        self::send($user, 'New category: ' . $category->name, 'You added a category.', [
            'Category'       => trim("{$category->icon} {$category->name}"),
            'Monthly budget' => $category->budget_minor ? Money::inr($category->budget_minor) : null,
        ]);
    }

    public static function budgetSet(User $user, \App\Models\Budget $budget): void
    {
        $category  = $budget->category_id ? \App\Models\Category::find($budget->category_id) : null;
        $monthName = \Carbon\Carbon::create($budget->year, $budget->month, 1)->format('F Y');
        self::send($user, "Budget set for {$monthName}", 'You set a budget.', [
            'Month'  => $monthName,
            'For'    => $category ? trim("{$category->icon} {$category->name}") : 'All spending',
            'Amount' => Money::inr($budget->amount_minor),
        ]);
    }

    public static function currencyAdded(User $user, \App\Models\UserCurrency $currency): void
    {
        self::send($user, "Currency added: {$currency->code}", 'You added a currency.', [
            'Currency' => "{$currency->label} ({$currency->code})",
            'Symbol'   => $currency->symbol,
            'Rate'     => "1 {$currency->code} = ₹" . rtrim(rtrim(number_format((float) $currency->rate, 6, '.', ''), '0'), '.'),
        ]);
    }

    public static function currencyChanged(User $user, ?string $from): void
    {
        self::send($user, "Currency changed to {$user->currency}", 'You changed the currency the app shows amounts in.', [
            'From' => $from,
            'To'   => $user->currency,
        ]);
    }

    /** @param array<string, string|null> $details label => value; nulls are left out */
    public static function send(User $user, string $subject, string $headline, array $details): void
    {
        // Off only when the user turned it off: a model that has not been
        // read back from the database has no value yet, and the default is on.
        if (! config('mail.activity_notifications') || $user->email_notifications === false || ! $user->email) {
            return;
        }

        $notification = new ActivityNotification(
            $subject,
            $headline,
            array_filter($details, fn ($value) => $value !== null && $value !== ''),
        );

        defer(function () use ($user, $notification) {
            try {
                $user->notify($notification);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
