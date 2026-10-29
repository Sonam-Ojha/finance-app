<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SignsSocialTokens;

/** Google / Apple sign-in meeting an account that already uses the same email (App\Support\SocialSignIn). */
class SocialSignInClaimTest extends ApiTestCase
{
    use SignsSocialTokens;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config([
            'services.google.client_ids'    => ['android-client.apps.googleusercontent.com'],
            'services.google.web_client_id' => 'web-client.apps.googleusercontent.com',
            'services.apple.client_ids'     => ['com.example.expenses'],
        ]);
        $this->fakeSocialKeys();
    }

    /** Someone registered rishi@gmail.com with a password, never proved it, and is signed in on a phone. */
    private function squatter(array $attributes = []): array
    {
        $user = $this->makeUser($attributes + ['email' => 'rishi@gmail.com', 'password' => 'squatter123', 'email_verified_at' => null]);
        $user->forceFill(['pin' => Hash::make('1234')])->save();
        $token = $user->createToken('api');
        DeviceToken::create(['user_id' => $user->id, 'access_token_id' => $token->accessToken->id, 'token' => 'squatter-phone', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'older-phone', 'platform' => 'android']);

        return [$user, $token->plainTextToken];
    }

    public function test_a_verified_account_with_the_same_email_is_linked_and_keeps_its_password(): void
    {
        $existing = $this->makeUser(['email' => 'rishi@gmail.com', 'password' => 'secret123']);
        $this->assertNotNull($existing->email_verified_at);
        $phone = $existing->createToken('api')->plainTextToken;

        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken()])
            ->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.user.id', $existing->id)
            ->assertJsonPath('data.user.has_password', true);

        $this->assertSame('google-111', $existing->fresh()->google_id);
        // Nothing of the owner's is touched.
        $this->app['auth']->forgetGuards();
        $this->withToken($phone)->getJson('/api/v1/me')->assertOk();
        $this->assertTrue(Hash::check('secret123', $existing->fresh()->password));
    }

    public function test_google_claims_an_account_whose_email_was_never_proved(): void
    {
        [$squatter, $squatterToken] = $this->squatter(['apple_id' => 'someone-elses-apple']);

        $response = $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken()])
            ->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.user.id', $squatter->id)
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.user.has_password', false)
            ->assertJsonPath('data.user.has_pin', false);

        $claimed = $squatter->fresh();
        $this->assertSame('google-111', $claimed->google_id);
        $this->assertNull($claimed->apple_id);
        $this->assertNotNull($claimed->email_verified_at);
        $this->assertSame(1, User::count());

        // The squatter's sign-in, phones and password are gone; the new owner's token works.
        $this->assertSame(0, DeviceToken::count());
        $this->assertSame(1, $claimed->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($squatterToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $squatter->id);
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->postJson('/api/v1/login', ['email' => 'rishi@gmail.com', 'password' => 'squatter123'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'This account signs in with Google or Apple.');
    }

    public function test_apple_claims_an_unproved_account_the_same_way(): void
    {
        [$squatter] = $this->squatter();

        $this->postJson('/api/v1/auth/apple', ['identity_token' => $this->appleIdToken(), 'name' => 'Rishi'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $squatter->id)
            ->assertJsonPath('data.user.has_password', false);

        $this->assertSame('apple-222', $squatter->fresh()->apple_id);
        $this->assertNull($squatter->fresh()->password);
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_a_blocked_account_is_neither_signed_in_nor_linked(): void
    {
        $blocked = $this->makeUser(['email' => 'rishi@gmail.com', 'email_verified_at' => null]);
        $blocked->forceFill(['blocked_at' => now()])->save();

        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken()])
            ->assertForbidden()
            ->assertExactJson(['message' => 'This account is blocked. Contact support.', 'code' => 'account_blocked']);
        $this->assertNull($blocked->fresh()->google_id);
        $this->assertNull($blocked->fresh()->email_verified_at);

        // Already linked: refused by its Google id too.
        $blocked->forceFill(['google_id' => 'google-111'])->save();
        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken(['email' => 'other@gmail.com'])])
            ->assertForbidden()->assertJsonPath('code', 'account_blocked');
        $this->assertSame(0, $blocked->tokens()->count());
    }

    public function test_a_new_google_account_must_add_its_birthday_in_the_app(): void
    {
        $token = $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken()])
            ->assertCreated()
            ->assertJsonPath('data.user.birthday', null)
            ->assertJsonPath('data.user.birthday_required', true)
            ->json('data.token');

        // Not blocked meanwhile: the app gates on the flag itself.
        $this->withToken($token)->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('data.user.birthday_required', true);

        $this->withToken($token)->patchJson('/api/v1/me/profile', ['birthday' => '1994-02-03'])
            ->assertOk()
            ->assertJsonPath('data.birthday', '1994-02-03')
            ->assertJsonPath('data.birthday_required', false);
        $this->assertFalse(User::firstOrFail()->birthday_required);

        // An account that signs in with Google again is not asked again.
        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken()])->assertOk()->assertJsonPath('data.user.birthday_required', false);
    }

    public function test_the_website_client_id_is_accepted_by_the_api_too(): void
    {
        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken(['aud' => 'web-client.apps.googleusercontent.com'])])->assertCreated();
        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken(['aud' => 'android-client.apps.googleusercontent.com'])])->assertOk();
        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken(['aud' => 'someone-else'])])->assertUnauthorized();
    }
}
