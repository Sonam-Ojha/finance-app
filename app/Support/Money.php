<?php

namespace App\Support;

class Money
{
    /**
     * Stored hundredths of a rupee as "₹1,23,456.78" — Indian grouping, by
     * hand, so it does not depend on the intl extension being installed.
     */
    public static function inr(int $minor): string
    {
        $sign   = $minor < 0 ? '-' : '';
        $minor  = abs($minor);
        $rupees = (string) intdiv($minor, 100);
        $paise  = str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);

        if (strlen($rupees) > 3) {
            $rest   = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($rupees, 0, -3));
            $rupees = $rest . ',' . substr($rupees, -3);
        }

        return "{$sign}₹{$rupees}.{$paise}";
    }
}
