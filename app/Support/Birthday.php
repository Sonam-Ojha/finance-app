<?php

namespace App\Support;

use Carbon\Carbon;
use Closure;

/**
 * The date-of-birth rule the app and the profile pages use: at least 1 and
 * at most 120 years old, with a day of slack either way (the app judges by
 * the user's own date, which can be a day off the server's).
 */
class Birthday
{
    public const MESSAGE = 'Choose a real date of birth.';

    /** Validation rules for a "YYYY-MM-DD" date of birth; prepend required/nullable. */
    public static function rules(): array
    {
        return ['date_format:Y-m-d', self::ageRule()];
    }

    public static function ageRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $born = Carbon::createFromFormat('!Y-m-d', (string) $value);
            if (! $born || $born->gt(now()->addDay()->subYear()) || $born->lte(now()->subDay()->subYears(121))) {
                $fail(self::MESSAGE);
            }
        };
    }

    /** The oldest and youngest dates a date picker should offer. */
    public static function pickerRange(): array
    {
        return [
            'oldest'   => now()->subYears(120)->startOfYear()->toDateString(),
            'youngest' => now()->subYear()->toDateString(),
        ];
    }
}
