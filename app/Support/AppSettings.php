<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Small switches kept in the `app_settings` table (one JSON value per key),
 * cached until the next set(). Admin › App control writes them; what they
 * mean lives in App\Support\AppControl.
 */
class AppSettings
{
    private const CACHE_KEY = 'app_settings.all';

    /** Every stored value, decoded. Empty before the table is migrated. */
    public static function all(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('app_settings')
                ->pluck('value', 'key')
                ->map(fn ($value) => $value === null ? null : json_decode($value, true))
                ->all());
        } catch (QueryException) {
            // No table yet (a deploy before `migrate`): every page must still work.
            return [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /** Stores a value; null removes the key, so get() falls back to its default again. */
    public static function set(string $key, mixed $value): void
    {
        if ($value === null) {
            DB::table('app_settings')->where('key', $key)->delete();
        } else {
            $row = ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_at' => now()];
            if (DB::table('app_settings')->where('key', $key)->exists()) {
                DB::table('app_settings')->where('key', $key)->update($row);
            } else {
                DB::table('app_settings')->insert($row + ['key' => $key, 'created_at' => now()]);
            }
        }

        self::forgetCache();
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
