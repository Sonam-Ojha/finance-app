<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The currencies the app and the website offer: the `currencies` table,
 * edited in Admin › Currencies (cached until it changes). Every amount is
 * stored in INR, which is always on offer.
 */
class Currencies
{
    public const BASE = 'INR';

    /** Used only while the table is empty (or not migrated yet); it was seeded from this list. */
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

    private const CACHE_KEY = 'currencies.catalog';

    /**
     * Every currency in the table, switched off ones too, in the admin's
     * order: code => {symbol, decimals, label, rate, enabled}.
     */
    public static function catalog(): array
    {
        try {
            $rows = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('currencies')
                ->orderBy('sort_order')->orderBy('id')->get()
                ->mapWithKeys(fn ($row) => [$row->code => [
                    'symbol'   => $row->symbol,
                    'decimals' => (int) $row->decimals,
                    'label'    => $row->name,
                    'rate'     => $row->default_rate === null ? null : (float) $row->default_rate,
                    'enabled'  => (bool) $row->enabled,
                ]])->all());
        } catch (QueryException) {
            $rows = [];
        }

        if (! $rows) {
            $rows = array_map(fn ($meta) => $meta + ['rate' => null, 'enabled' => true], self::BUILT_IN);
            $rows[self::BASE]['rate'] = 1.0;
        }

        return $rows;
    }

    /** The ones on offer — code => {symbol, decimals, label} — rupees always first. */
    public static function all(): array
    {
        $catalog = self::catalog();
        $base = $catalog[self::BASE] ?? self::BUILT_IN[self::BASE] + ['rate' => 1.0, 'enabled' => true];
        $on = array_filter($catalog, fn ($meta, $code) => $meta['enabled'] && $code !== self::BASE, ARRAY_FILTER_USE_BOTH);

        return array_map(
            fn ($meta) => ['symbol' => $meta['symbol'], 'decimals' => $meta['decimals'], 'label' => $meta['label']],
            [self::BASE => $base] + $on,
        );
    }

    /** On offer to everyone (as opposed to one a user added for themselves). */
    public static function isBuiltIn(string $code): bool
    {
        return array_key_exists($code, self::all());
    }

    /** Name, symbol and decimals of any currency in the table, switched off or not. */
    public static function meta(string $code): ?array
    {
        return self::catalog()[$code] ?? null;
    }

    /** The admin's suggested rate (rupees for one unit) for a currency on offer, or null. */
    public static function defaultRate(string $code): ?float
    {
        if ($code === self::BASE) return 1.0;
        if (! self::isBuiltIn($code)) return null;

        return self::catalog()[$code]['rate'] ?? null;
    }

    /** The shape of `GET /currencies`. */
    public static function list(): array
    {
        return collect(self::all())
            ->map(fn ($meta, $code) => ['code' => $code] + $meta)
            ->values()
            ->all();
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
