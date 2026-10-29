<?php

namespace App\Support;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks the ID token an app got from Google or Apple: its signature against
 * the provider's published keys, then who issued it, which app it was issued
 * to, and that it has not expired. Only then is the person it names trusted.
 */
class SocialTokens
{
    public const GOOGLE_KEYS = 'https://www.googleapis.com/oauth2/v3/certs';
    public const APPLE_KEYS  = 'https://appleid.apple.com/auth/keys';

    private const GOOGLE_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];
    private const APPLE_ISSUERS  = ['https://appleid.apple.com'];

    /**
     * The app's tokens (GOOGLE_CLIENT_IDS) and the website's "Continue with
     * Google" button's (services.google.web_client_id) are both accepted.
     *
     * @return array{sub: string, email: ?string, email_verified: bool, name: ?string}|null
     */
    public static function google(string $token): ?array
    {
        return self::verify($token, self::GOOGLE_KEYS, self::GOOGLE_ISSUERS, self::googleClientIds());
    }

    /** @return list<string> */
    public static function googleClientIds(): array
    {
        return array_values(array_unique(array_filter([
            ...(array) config('services.google.client_ids', []),
            config('services.google.web_client_id'),
        ])));
    }

    /** Apple never puts the name in the token; the app sends it, first sign-in only. */
    public static function apple(string $token): ?array
    {
        return self::verify($token, self::APPLE_KEYS, self::APPLE_ISSUERS, config('services.apple.client_ids'));
    }

    private static function verify(string $token, string $keysUrl, array $issuers, array $audiences): ?array
    {
        $claims = self::decode($token, $keysUrl);
        if ($claims === null) return null;

        if (! in_array($claims['iss'] ?? null, $issuers, true)) return null;
        if (! in_array($claims['aud'] ?? null, $audiences, true)) return null;
        if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '') return null;

        return [
            'sub'            => $claims['sub'],
            'email'          => isset($claims['email']) ? strtolower(trim($claims['email'])) : null,
            // Google sends a boolean, Apple the string "true".
            'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'name'           => isset($claims['name']) ? trim($claims['name']) : null,
        ];
    }

    /**
     * The keys are cached for an hour. When a token names a key the cache
     * does not have — the provider rotated them — they are fetched again once.
     */
    private static function decode(string $token, string $keysUrl): ?array
    {
        $cacheKey = 'social_jwks:' . md5($keysUrl);
        JWT::$leeway = 60;

        foreach ([false, true] as $refresh) {
            try {
                if ($refresh) Cache::forget($cacheKey);
                $keys = Cache::remember($cacheKey, now()->addHour(), fn () => Http::timeout(10)->get($keysUrl)->throw()->json());

                return (array) JWT::decode($token, JWK::parseKeySet($keys));
            } catch (Throwable) {
                // Bad signature, expired, unknown key, or the keys would not load.
            }
        }

        return null;
    }
}
