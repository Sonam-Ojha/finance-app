<?php

namespace App\Http\Controllers\Tracker\Concerns;

use App\Support\DisplayCurrency;
use Closure;
use Illuminate\Http\Request;

/** Amount fields typed in the user's currency, read the way the app reads them. */
trait ReadsAmounts
{
    /**
     * A typed amount as stored hundredths of a rupee, or null when it is
     * not usable (or empty). An untouched field keeps the stored value as it
     * was, so display rounding never rewrites an amount.
     */
    protected function amountValue(?string $text, DisplayCurrency $money, ?int $original): ?int
    {
        $text = trim((string) $text);
        if ($original !== null && $text === $money->toInput($original)) return $original;

        return $text === '' ? null : $money->parse($text);
    }

    /** A validation rule failing with $message unless the field is a usable amount (or empty, when $optional). */
    protected function amountRule(DisplayCurrency $money, ?int $original, string $message, bool $optional = false): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($money, $original, $message, $optional) {
            if ($optional && trim((string) $value) === '') return;
            if ($this->amountValue($value, $money, $original) === null) $fail($message);
        };
    }

    /** Where to go after saving: the page the form was opened from, if it is one of ours and not the form itself. */
    protected function back(Request $request, string $fallback): string
    {
        $back = (string) $request->input('back');
        $ours = $back !== '' && str_starts_with($back, url('/') . '/');

        return $ours && $back !== $request->fullUrl() ? $back : $fallback;
    }
}
