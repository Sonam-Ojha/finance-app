<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeviceToken;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;

/**
 * How long the app's sign-ins last, what ends them, and the phones (push
 * tokens) tied to them.
 */
class SessionLifetimeTest extends ApiTestCase
{
    /** A real token (not Sanctum::actingAs), with a phone registered under it. */
    private function phoneSignIn(User $user, string $fcm = 'phone-1'): array
    {
        $token = $user->createToken('api');
        DeviceToken::create(['user_id' => $user->id, 'access_token_id' => $token->accessToken->id, 'token' => $fcm, 'platform' => 'android']);

        return [$token->plainTextToken, $token->accessToken];
    }

    private function asPhone(string $token, string $method, string $uri, array $data = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->json($method, $uri, $data);
    }

    public function test_the_app_uses_the_custom_token_model(): void
    {
        $this->assertSame(PersonalAccessToken::class, Sanctum::personalAccessTokenModel());
        $this->assertInstanceOf(PersonalAccessToken::class, $this->makeUser()->createToken('api')->accessToken);
    }

    public function test_a_token_unused_for_90_days_stops_working(): void
    {
        $user = $this->makeUser();
        [$plain, $token] = $this->phoneSignIn($user);

        $token->forceFill(['created_at' => now()->subDays(200), 'last_used_at' => now()->subDays(89)])->save();
        $this->asPhone($plain, 'GET', '/api/v1/me')->assertOk();
        // That use counted: it is fresh again.
        $this->assertTrue($token->fresh()->last_used_at->isToday());

        $token->forceFill(['last_used_at' => now()->subDays(91)])->save();
        $this->asPhone($plain, 'GET', '/api/v1/me')->assertUnauthorized();

        // Never used: counted from when it was made.
        [$never, $neverToken] = $this->phoneSignIn($user, 'phone-2');
        $neverToken->forceFill(['created_at' => now()->subDays(91), 'last_used_at' => null])->save();
        $this->asPhone($never, 'GET', '/api/v1/me')->assertUnauthorized();
    }

    public function test_a_token_older_than_a_year_stops_working_however_much_it_is_used(): void
    {
        $user = $this->makeUser();
        [$plain, $token] = $this->phoneSignIn($user);
        $this->assertSame(60 * 24 * 365, config('sanctum.expiration'));

        $token->forceFill(['created_at' => now()->subDays(364), 'last_used_at' => now()->subDay()])->save();
        $this->asPhone($plain, 'GET', '/api/v1/me')->assertOk();

        $token->forceFill(['created_at' => now()->subDays(366), 'last_used_at' => now()->subDay()])->save();
        $this->asPhone($plain, 'GET', '/api/v1/me')->assertUnauthorized();
    }

    public function test_the_daily_prune_removes_dead_tokens_and_their_phones(): void
    {
        $user = $this->makeUser();
        [, $idle] = $this->phoneSignIn($user, 'idle-phone');
        [, $old] = $this->phoneSignIn($user, 'old-phone');
        [, $fresh] = $this->phoneSignIn($user, 'fresh-phone');
        DeviceToken::create(['user_id' => $user->id, 'token' => 'from-before-tokens-were-recorded', 'platform' => 'android']);

        $idle->forceFill(['created_at' => now()->subDays(120), 'last_used_at' => now()->subDays(95)])->save();
        $old->forceFill(['created_at' => now()->subDays(370), 'last_used_at' => now()->subDay()])->save();

        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();
        $this->artisan('tokens:prune-idle', ['--hours' => 24])->assertSuccessful();

        $this->assertSame([$fresh->id], PersonalAccessToken::pluck('id')->all());
        $this->assertEqualsCanonicalizing(['fresh-phone', 'from-before-tokens-were-recorded'], DeviceToken::pluck('token')->all());
    }

    public function test_the_prune_is_scheduled_daily(): void
    {
        // Loads routes/console.php, where the schedule is.
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $commands =collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode("\n");

        $this->assertStringContainsString('sanctum:prune-expired --hours=24', $commands);
        $this->assertStringContainsString('tokens:prune-idle --hours=24', $commands);
    }

    public function test_a_phone_is_tied_to_the_sign_in_that_registered_it(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('api');

        $this->asPhone($token->plainTextToken, 'POST', '/api/v1/me/devices', ['token' => 'phone-1'])->assertNoContent();
        $this->assertSame($token->accessToken->id, DeviceToken::firstOrFail()->access_token_id);

        // Signing out ends the pushes to that phone.
        $this->asPhone($token->plainTextToken, 'POST', '/api/v1/logout')->assertNoContent();
        $this->assertSame(0, DeviceToken::count());
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_phone_moves_to_whoever_registers_it_next(): void
    {
        $first = $this->makeUser(['email' => 'first@example.com']);
        $second = $this->makeUser(['email' => 'second@example.com']);
        $firstToken = $first->createToken('api');
        $secondToken = $second->createToken('api');

        $this->asPhone($firstToken->plainTextToken, 'POST', '/api/v1/me/devices', ['token' => 'shared-phone'])->assertNoContent();
        $this->asPhone($secondToken->plainTextToken, 'POST', '/api/v1/me/devices', ['token' => 'shared-phone'])->assertNoContent();

        $phone = DeviceToken::firstOrFail();
        $this->assertSame($second->id, $phone->user_id);
        $this->assertSame($secondToken->accessToken->id, $phone->access_token_id);

        // The first user's sign-in ending no longer touches it.
        $firstToken->accessToken->delete();
        $this->assertSame(1, DeviceToken::count());
    }

    public function test_changing_the_password_in_the_app_signs_out_every_other_phone(): void
    {
        $user = $this->makeUser(['password' => 'oldpass123']);
        [$here] = $this->phoneSignIn($user, 'this-phone');
        [$there] = $this->phoneSignIn($user, 'other-phone');

        $this->asPhone($here, 'PUT', '/api/v1/me/password', ['current_password' => 'oldpass123', 'new_password' => 'newpass123'])->assertNoContent();

        $this->asPhone($here, 'GET', '/api/v1/me')->assertOk();
        $this->asPhone($there, 'GET', '/api/v1/me')->assertUnauthorized();
        $this->assertSame(['this-phone'], DeviceToken::pluck('token')->all());
    }

    public function test_resetting_the_password_in_the_app_signs_out_everywhere(): void
    {
        Notification::fake();
        $user = $this->makeUser(['email' => 'me@example.com']);
        [$phone] = $this->phoneSignIn($user);
        $reset = Password::createToken($user);

        $this->withoutToken()->postJson('/api/v1/reset-password', ['email' => 'me@example.com', 'token' => $reset, 'password' => 'brandnew123'])
            ->assertNoContent();

        $this->asPhone($phone, 'GET', '/api/v1/me')->assertUnauthorized();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_resetting_the_password_on_the_website_signs_out_the_app(): void
    {
        $this->withoutVite();
        $user = $this->makeUser(['email' => 'me@example.com']);
        [$phone] = $this->phoneSignIn($user);

        $this->withoutToken()->post('/reset-password', [
            'token' => Password::createToken($user), 'email' => 'me@example.com',
            'password' => 'brandnew123', 'password_confirmation' => 'brandnew123',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DeviceToken::count());
        $this->asPhone($phone, 'GET', '/api/v1/me')->assertUnauthorized();
    }

    public function test_changing_the_password_on_the_website_signs_out_the_app(): void
    {
        $user = $this->makeUser(['password' => 'oldpass123']);
        $this->phoneSignIn($user);

        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'oldpass123', 'new_password' => 'newpass123', 'new_password_confirmation' => 'newpass123',
        ])->assertRedirect(route('settings.index'));

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DeviceToken::count());
        // The website session itself carries on.
        $this->assertAuthenticatedAs($user);
    }
}
