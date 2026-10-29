<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SignsSocialTokens;
use Tests\TestCase;

/** Admin › Users › Block: what a blocked account can no longer do. */
class BlockUsersTest extends TestCase
{
    use RefreshDatabase, SignsSocialTokens;

    private const MESSAGE = 'This account is blocked. Contact support.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['email' => 'boss@example.com']);
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        return $admin;
    }

    private function member(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['email' => 'member@example.com', 'password' => 'secret123', 'onboarded' => true]);
        DefaultCategories::seed($user);

        return $user;
    }

    private function blocked(): User
    {
        $user = $this->member();
        $user->forceFill(['blocked_at' => now()])->save();

        return $user;
    }

    public function test_blocking_ends_every_session_and_is_logged(): void
    {
        $admin = $this->admin();
        $user = $this->member();
        $phone = $user->createToken('api');
        DeviceToken::create(['user_id' => $user->id, 'access_token_id' => $phone->accessToken->id, 'token' => 'phone-1', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'phone-2', 'platform' => 'ios']);

        // A website session of theirs, in the database session store.
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'their-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 'someone-elses', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($admin)->post("/admin/users/{$user->id}/block")
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNotNull($user->fresh()->blocked_at);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DeviceToken::count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertTrue(DB::table('sessions')->where('id', 'someone-elses')->exists());
        $this->assertNotSame($user->remember_token, $user->fresh()->remember_token);

        $log = AdminActivityLog::latest('id')->firstOrFail();
        $this->assertSame('user.blocked', $log->action);
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame("blocked user #{$user->id}", $log->description);

        $this->app['auth']->forgetGuards();
        $this->withToken($phone->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_admins_cannot_block_themselves_or_other_admins(): void
    {
        $admin = $this->admin();
        $other = $this->member();
        $other->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($admin)->post("/admin/users/{$admin->id}/block")->assertSessionHas('error', 'You cannot block yourself.');
        $this->actingAs($admin)->post("/admin/users/{$other->id}/block")->assertSessionHas('error');
        $this->assertNull($admin->fresh()->blocked_at);
        $this->assertNull($other->fresh()->blocked_at);

        // Nor can a blocked user be made an admin.
        $blocked = User::factory()->create(['blocked_at' => now()]);
        $this->actingAs($admin)->patch("/admin/users/{$blocked->id}/role", ['role' => 'admin'])->assertSessionHas('error');
        $this->assertFalse($blocked->fresh()->isAdmin());

        // Ordinary users cannot block anyone.
        $this->actingAs($this->member(['email' => 'x@example.com']))->post("/admin/users/{$blocked->id}/block")->assertForbidden();
    }

    public function test_unblocking_lets_them_back_in_and_is_logged(): void
    {
        $admin = $this->admin();
        $user = $this->blocked();

        $this->actingAs($admin)->delete("/admin/users/{$user->id}/block")->assertSessionHas('success');
        $this->assertNull($user->fresh()->blocked_at);
        $this->assertSame('user.unblocked', AdminActivityLog::latest('id')->firstOrFail()->action);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/login', ['email' => 'member@example.com', 'password' => 'secret123'])->assertOk();
    }

    public function test_the_users_page_shows_and_filters_blocked_accounts(): void
    {
        $admin = $this->admin();
        $this->blocked();
        User::factory()->create(['email' => 'fine@example.com']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()->assertSee('Blocked')->assertSee('Unblock')->assertSee('fine@example.com');

        $this->actingAs($admin)->get('/admin/users?status=blocked')
            ->assertOk()->assertSee('member@example.com')->assertDontSee('fine@example.com');
    }

    public function test_a_blocked_user_is_signed_out_of_the_website_on_their_next_request(): void
    {
        $user = $this->member();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['blocked_at' => now()])->save();

        $this->get('/dashboard')->assertRedirect(route('login'))->assertSessionHasErrors(['email' => self::MESSAGE]);
        $this->assertGuest();
    }

    public function test_a_blocked_user_cannot_sign_in_on_the_website(): void
    {
        $this->blocked();

        $this->post('/login', ['email' => 'member@example.com', 'password' => 'secret123'])
            ->assertSessionHasErrors(['email' => self::MESSAGE]);
        $this->assertGuest();

        // A wrong password says only that.
        $this->post('/login', ['email' => 'member@example.com', 'password' => 'wrong-one'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    public function test_the_api_answers_a_blocked_account_with_403_account_blocked(): void
    {
        $user = $this->blocked();
        $blockedAnswer = ['message' => self::MESSAGE, 'code' => 'account_blocked'];

        $this->postJson('/api/v1/login', ['email' => 'member@example.com', 'password' => 'secret123'])
            ->assertForbidden()->assertExactJson($blockedAnswer);
        $this->postJson('/api/v1/register', ['email' => 'member@example.com', 'password' => 'another123'])
            ->assertForbidden()->assertExactJson($blockedAnswer);
        $this->assertSame(0, $user->tokens()->count());

        // A token from before the block (blocking deletes them; this one slipped through).
        $token = $user->createToken('api')->plainTextToken;
        foreach (['/api/v1/me', '/api/v1/bootstrap', '/api/v1/transactions'] as $uri) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson($uri)->assertForbidden()->assertExactJson($blockedAnswer);
        }
    }

    public function test_google_sign_in_refuses_a_blocked_account_on_the_api_and_the_website(): void
    {
        config(['services.google.client_ids' => ['android-client'], 'services.google.web_client_id' => 'web-client']);
        $this->fakeSocialKeys();
        $this->blocked();

        $this->postJson('/api/v1/auth/google', ['id_token' => $this->googleIdToken(['email' => 'member@example.com'])])
            ->assertForbidden()->assertJsonPath('code', 'account_blocked');

        $this->post('/auth/google', ['credential' => $this->googleIdToken(['email' => 'member@example.com'])])
            ->assertRedirect(route('login'))->assertSessionHasErrors(['google' => self::MESSAGE]);
        $this->assertGuest();
    }
}
