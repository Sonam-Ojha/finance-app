<?php

namespace App\Support;

/**
 * How this month compares with the last, as the app's home screen words it.
 * A percentage only means something against a positive base, and past
 * ±999% it says more about how small last month was than about this one.
 */
class MonthChange
{
    public const MAX_SHOWN_PERCENT = 999;

    /** @return array{kind: 'percent'|'new'|'none', value?: int} */
    public static function of(int $current, int $previous): array
    {
        if ($previous <= 0) {
            return ['kind' => $previous === 0 && $current > 0 ? 'new' : 'none'];
        }

        $value = (int) round(($current - $previous) / $previous * 100);
        if ($value < -self::MAX_SHOWN_PERCENT) return ['kind' => 'none'];
        if ($value > self::MAX_SHOWN_PERCENT) return ['kind' => 'new'];

        return ['kind' => 'percent', 'value' => $value];
    }
}
