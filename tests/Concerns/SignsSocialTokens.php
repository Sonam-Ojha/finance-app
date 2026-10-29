<?php

namespace Tests\Concerns;

use App\Support\SocialTokens;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

/**
 * Google and Apple ID tokens signed with a throwaway key, and the providers'
 * key servers answered by Http::fake() with its public half — so
 * App\Support\SocialTokens checks them for real, without the network.
 */
trait SignsSocialTokens
{
    protected string $socialPrivateKey;

    protected function fakeSocialKeys(): void
    {
        [$this->socialPrivateKey, $jwk] = $this->makeRsaKey('test-key');
        Http::fake([
            SocialTokens::GOOGLE_KEYS => Http::response(['keys' => [$jwk]]),
            SocialTokens::APPLE_KEYS  => Http::response(['keys' => [$jwk]]),
        ]);
    }

    /** A fresh RSA key pair: the private key as PEM, the public one as a JWK. */
    protected function makeRsaKey(string $kid): array
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        // Windows builds of PHP ship their openssl.cnf beside the binary and do not find it on their own.
        $config = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_file($config)) $options['config'] = $config;

        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $b64 = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return [$pem, ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => $kid, 'n' => $b64($rsa['n']), 'e' => $b64($rsa['e'])]];
    }

    protected function googleIdToken(array $claims = []): string
    {
        return JWT::encode($claims + [
            'iss'            => 'https://accounts.google.com',
            'aud'            => config('services.google.web_client_id') ?: (config('services.google.client_ids')[0] ?? 'web-client'),
            'sub'            => 'google-111',
            'email'          => 'rishi@gmail.com',
            'email_verified' => true,
            'name'           => 'Rishi Dubey',
            'iat'            => time(),
            'exp'            => time() + 3600,
        ], $this->socialPrivateKey, 'RS256', 'test-key');
    }

    protected function appleIdToken(array $claims = []): string
    {
        return JWT::encode($claims + [
            'iss'            => 'https://appleid.apple.com',
            'aud'            => config('services.apple.client_ids')[0] ?? 'com.example.expenses',
            'sub'            => 'apple-222',
            'email'          => 'rishi@gmail.com',
            'email_verified' => 'true',
            'iat'            => time(),
            'exp'            => time() + 3600,
        ], $this->socialPrivateKey, 'RS256', 'test-key');
    }
}
