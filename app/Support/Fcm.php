<?php

namespace App\Support;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Push notifications through Firebase Cloud Messaging (HTTP v1 API).
 *
 * Authenticates with the Firebase service-account key — the JSON file from
 * Firebase console › Project settings › Service accounts — at the path
 * `services.firebase.credentials`. Without that file `configured()` is false
 * and nothing is sent.
 */
class Fcm
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    /** Sent at once; FCM v1 takes one token per request. */
    private const BATCH = 100;
    /** Must match the channel id the app creates (src/lib/push.ts). */
    public const ANDROID_CHANNEL = 'default';

    public static function configured(): bool
    {
        return self::credentials() !== null;
    }

    /**
     * @param  string[]  $tokens
     * @param  array<string, string>  $data  extra key/values for the app
     * @return array{delivered: int, failed: int, invalid: string[]}  invalid:
     *         tokens FCM says are dead (the app was removed) — delete them
     */
    public static function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $result = ['delivered' => 0, 'failed' => 0, 'invalid' => []];
        $credentials = self::credentials();
        if ($credentials === null || $tokens === []) return $result;

        try {
            $accessToken = self::accessToken($credentials);
        } catch (Throwable $e) {
            report($e);
            $result['failed'] = count($tokens);
            return $result;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send";
        // FCM wants every data value as a string.
        $data = array_map('strval', $data);

        foreach (array_chunk(array_values($tokens), self::BATCH) as $batch) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $token) => $pool->as($token)
                    ->withToken($accessToken)
                    ->timeout(10)
                    ->post($url, ['message' => array_filter([
                        'token'        => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data'         => $data ?: null,
                        // The app creates this channel (high importance, so the
                        // message pops up); without it Android files the push
                        // under "Miscellaneous", silently.
                        'android'      => ['priority' => 'high', 'notification' => ['channel_id' => self::ANDROID_CHANNEL]],
                    ])]),
                $batch,
            ));

            foreach ($responses as $token => $response) {
                if ($response instanceof Response && $response->successful()) {
                    $result['delivered']++;
                } else {
                    $result['failed']++;
                    if ($response instanceof Response && self::isDeadToken($response)) {
                        $result['invalid'][] = $token;
                    }
                }
            }
        }

        return $result;
    }

    /** The token no longer reaches an app: UNREGISTERED, or not a token at all. */
    private static function isDeadToken(Response $response): bool
    {
        $code = $response->json('error.details.0.errorCode') ?? $response->json('error.status');
        return in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)
            || $response->status() === 404;
    }

    /** An OAuth access token for FCM, from a JWT signed with the service account; reused for 50 minutes. */
    private static function accessToken(array $credentials): string
    {
        return Cache::remember('fcm_access_token:' . md5($credentials['client_email']), now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $assertion = JWT::encode([
                'iss'   => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud'   => $credentials['token_uri'],
                'iat'   => $now,
                'exp'   => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            return Http::asForm()->timeout(10)->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ])->throw()->json('access_token');
        });
    }

    /** The service-account key, or null when the file is missing or not one. */
    private static function credentials(): ?array
    {
        $path = config('services.firebase.credentials');
        if (! $path || ! is_file($path)) return null;

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || empty($json['project_id']) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        return $json + ['token_uri' => 'https://oauth2.googleapis.com/token'];
    }
}
