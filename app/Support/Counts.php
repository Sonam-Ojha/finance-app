<?php

namespace App\Support;

/** Counts of things (never money) written the Indian way, for the admin pages. */
class Counts
{
    /** 1,23,456 — or "3.2 lakh" / "1.4 crore" once it is that big. */
    public static function indian(int $count): string
    {
        $trim = fn (float $v) => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
        if ($count >= 10000000) return $trim($count / 10000000) . ' crore';
        if ($count >= 100000) return $trim($count / 100000) . ' lakh';

        $digits = (string) $count;
        if (strlen($digits) <= 3) return $digits;

        return preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($digits, 0, -3)) . ',' . substr($digits, -3);
    }

    /** "4.2" — a share or change in percent, one decimal. */
    public static function percent(float $value): string
    {
        return number_format(abs($value), 1);
    }
}
