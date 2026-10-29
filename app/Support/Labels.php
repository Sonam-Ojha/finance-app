<?php

namespace App\Support;

use Carbon\Carbon;

/** Dates, months and income sources worded the way the app words them. */
class Labels
{
    /** The app's preset sources, with the icon each shows. */
    public const INCOME_SOURCES = [
        'Salary'    => '💼',
        'Freelance' => '💻',
        'Business'  => '🏢',
        'Interest'  => '🏦',
        'Refund'    => '↩️',
        'Gift'      => '🎁',
        'Other'     => '💰',
    ];

    /**
     * "Now" as the user's calendar sees it. The server keeps UTC, which is
     * a different day for India until 5:30 in the morning; today, this
     * month and the greeting go by the display timezone instead.
     */
    public static function now(): Carbon
    {
        return Carbon::now(config('app.display_timezone'));
    }

    /** Any typed source shows 💰; presets match case-insensitively. */
    public static function sourceIcon(?string $source): string
    {
        foreach (self::INCOME_SOURCES as $name => $icon) {
            if (strcasecmp($name, trim((string) $source)) === 0) return $icon;
        }
        return '💰';
    }

    /** "Today", "Yesterday", "Mon, 12 Aug" — with the year when it is not this one. */
    public static function dayHeading(Carbon|string $day): string
    {
        $day = self::calendarDay($day);
        $relative = self::relativeDay($day);
        if ($relative) return $relative;

        return $day->format('D, j M') . ($day->year !== self::now()->year ? ' ' . $day->year : '');
    }

    /** Transactions' day headers: "Today, 24 Oct 2026", "Thu, 22 Oct 2026". */
    public static function dayGroupLabel(Carbon|string $day): string
    {
        $day = self::calendarDay($day);

        return (self::relativeDay($day) ?? $day->format('D')) . ', ' . $day->format('j M Y');
    }

    /** A moment ("Today, 7:40 PM", "Mon, 12 Aug, 9:05 AM"), in the display timezone. */
    public static function moment(Carbon $at): string
    {
        $local = $at->copy()->timezone(config('app.display_timezone'));

        return self::dayHeading($local->toDateString()) . ', ' . $local->format('g:i A');
    }

    /** A date as a plain calendar day; a timestamp is read in the display timezone first. */
    private static function calendarDay(Carbon|string $day): Carbon
    {
        if ($day instanceof Carbon) $day = $day->copy()->timezone(config('app.display_timezone'))->toDateString();

        return Carbon::parse(substr((string) $day, 0, 10));
    }

    private static function relativeDay(Carbon $day): ?string
    {
        $today = self::now()->toDateString();
        if ($day->toDateString() === $today) return 'Today';
        if ($day->toDateString() === Carbon::parse($today)->subDay()->toDateString()) return 'Yesterday';

        return null;
    }

    /** "October 2026" */
    public static function month(string $month): string
    {
        return Carbon::parse($month . '-01')->format('F Y');
    }

    /** A ?month= value, kept to a real month no later than this one. */
    public static function clampMonth(?string $month): string
    {
        $current = self::now()->format('Y-m');
        if (! $month || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month > $current) return $current;

        return $month;
    }
}
