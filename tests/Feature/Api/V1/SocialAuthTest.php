<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\User;
use App\Support\SocialTokens;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

class SocialAuthTest extends ApiTestCase
{
    private const GOOGLE_CLIENT = 'android-client.apps.googleusercontent.com';
    private const APPLE_CLIENT  = 'com.example.expenses';

    private string $privateKey;
    private string $strangerKey;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config([
            'services.google.client_ids' => [self::GOOGLE_CLIENT],
            'services.apple.client_ids'  => [self::APPLE_CLIENT],
        ]);

        [$this->privateKey, $jwk] = $this->rsaKey('test-key');
        [$this->strangerKey] = $this->rsaKey('test-key');
        Http::fake([
            SocialTokens::GOOGLE_KEYS => Http::response(['keys' => [$jwk]]),
            SocialTokens::APPLE_KEYS  => Http::response(['keys' => [$jwk]]),
        ]);
    }

    /** A fresh RSA key pair: the private key as PEM, the public one as a JWK. */
    private function rsaKey(string $kid): array
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

    private function googleToken(array $claims = [], ?string $key = null): string
    {
        return JWT::encode($claims + [
            'iss'            => 'https://accounts.google.com',
            'aud'            => self::GOOGLE_CLIENT,
            'sub'            => 'google-111',
            'email'          => 'Rishi@Gmail.com',
            'email_verified' => true,
            'name'           => 'Rishi Dubey',
            'iat'            => time(),
            'exp'            => time() + 3600,
        ], $key ?? $this->privateKey, 'RS256', 'test-key');
    }

    public function test_a_new_google_user_gets_an_account(): void
    {
        $response = $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleToken()])
            ->assertCreated()
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.user.email', 'rishi@gmail.com')
            ->assertJsonPath('data.user.name', 'Rishi Dubey')
            ->assertJsonPath('data.user.has_password', false)
            ->assertJsonPath('data.user.onboarded', true);

        $user = User::where('email', 'rishi@gmail.com')->firstOrFail();
        $this->assertSame('google-111', $user->google_id);
        $this->assertSame(8, Category::where('user_id', $user->id)->count());

        // The token it returned works.
        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_signing_in_again_finds_the_same_account(): void
    {
        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleToken()])->assertCreated();
        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleToken(['email' => 'changed@gmail.com'])])
            ->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.user.email', 'rishi@gmail.com');

        $this->assertSame(1, User::count());
    }

    public function test_an_existing_password_account_with_the_same_verified_email_is_linked(): void
    {
        $existing = $this->makeUser(['email' => 'rishi@gmail.com', 'password' => 'secret123']);

        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleToken()])
            ->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.user.id', $existing->id)
            ->assertJsonPath('data.user.has_password', true);

        $this->assertSame('google-111', $existing->fresh()->google_id);
        $this->assertSame(1, User::count());
    }

    public function test_tokens_that_do_not_check_out_are_refused(): void
    {
        $refused = [
            'wrong app'      => $this->googleToken(['aud' => 'someone-elses-app']),
            'wrong issuer'   => $this->googleToken(['iss' => 'https://evil.example.com']),
            'expired'        => $this->googleToken(['exp' => time() - 3600, 'iat' => time() - 7200]),
            'wrong key'      => $this->googleToken([], $this->strangerKey),
            'not even a JWT' => 'hello',
        ];

        foreach ($refused as $why => $token) {
            $this->postJson('/api/v1/auth/google', ['id_token' => $token])->assertUnauthorized();
        }
        $this->assertSame(0, User::count());
    }

    public function test_an_unverified_email_cannot_make_an_account(): void
    {
        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleToken(['email_verified' => false])])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Your Google account did not share a verified email address.');
    }

    public function test_sign_in_is_off_until_client_ids_are_set(): void
    {
        config(['services.google.client_ids' => []]);
        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleToken()])->assertStatus(503);
        $this->postJson('/api/v1/auth/google', [])->assertStatus(503);
    }

    public function test_apple_takes_the_name_from_the_app(): void
    {
        $token = JWT::encode([
            'iss' => 'https://appleid.apple.com', 'aud' => self::APPLE_CLIENT, 'sub' => 'apple-222',
            'email' => 'abc123@privaterelay.appleid.com', 'email_verified' => 'true',
            'iat' => time(), 'exp' => time() + 3600,
        ], $this->privateKey, 'RS256', 'test-key');

        $this->postJson('/api/v1/auth/apple', ['identity_token' => $token, 'name' => 'Rishi'])
            ->assertCreated()
            ->assertJsonPath('data.user.name', 'Rishi')
            ->assertJsonPath('data.user.email', 'abc123@privaterelay.appleid.com');
        $this->assertSame('apple-222', User::first()->apple_id);
    }

    public function test_a_google_only_account_cannot_use_a_password_until_it_sets_one(): void
    {
        $token = $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleToken()])->json('data.token');

        $this->postJson('/api/v1/login', ['email' => 'rishi@gmail.com', 'password' => 'anything1'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'This account signs in with Google or Apple.');

        // No current password to give the first time.
        $this->withToken($token)->putJson('/api/v1/me/password', ['new_password' => 'newpass123'])->assertNoContent();
        $this->postJson('/api/v1/login', ['email' => 'rishi@gmail.com', 'password' => 'newpass123'])->assertOk();
    }
}
