<?php

namespace App\Support;

/** How an expense was paid or an income received. NULL means "not recorded". */
class PaymentMethods
{
    public const LABELS = [
        'cash'          => 'Cash',
        'upi'           => 'UPI',
        'credit_card'   => 'Credit card',
        'debit_card'    => 'Debit card',
        'bank_transfer' => 'Bank transfer',
        'other'         => 'Other',
    ];

    public const MESSAGE = 'Pick a payment method from the list.';

    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(?string $method): ?string
    {
        return $method === null ? null : (self::LABELS[$method] ?? null);
    }
}
