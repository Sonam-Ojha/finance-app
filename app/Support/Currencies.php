<?php

namespace App\Support;

/** The currencies the app ships with. Every amount is stored in INR. */
class Currencies
{
    public const BASE = 'INR';

    public const BUILT_IN = [
        'INR' => ['symbol' => '₹',   'decimals' => 2, 'label' => 'Indian Rupee'],
        'USD' => ['symbol' => '$',   'decimals' => 2, 'label' => 'US Dollar'],
        'EUR' => ['symbol' => '€',   'decimals' => 2, 'label' => 'Euro'],
        'GBP' => ['symbol' => '£',   'decimals' => 2, 'label' => 'British Pound'],
        'JPY' => ['symbol' => '¥',   'decimals' => 0, 'label' => 'Japanese Yen'],
        'AUD' => ['symbol' => 'A$',  'decimals' => 2, 'label' => 'Australian Dollar'],
        'CAD' => ['symbol' => 'C$',  'decimals' => 2, 'label' => 'Canadian Dollar'],
        'AED' => ['symbol' => 'AED', 'decimals' => 2, 'label' => 'UAE Dirham'],
        'SGD' => ['symbol' => 'S$',  'decimals' => 2, 'label' => 'Singapore Dollar'],
    ];

    public static function isBuiltIn(string $code): bool
    {
        return array_key_exists($code, self::BUILT_IN);
    }

    /** The shape of `GET /currencies`. */
    public static function list(): array
    {
        return collect(self::BUILT_IN)
            ->map(fn ($meta, $code) => ['code' => $code] + $meta)
            ->values()
            ->all();
    }
}
