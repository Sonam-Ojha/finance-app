<?php

namespace Tests\Feature;

use App\Models\EmailCode;
use App\Models\User;
use App\Notifications\EmailCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    /** The code in the last email this user got. */
    private function codeFor(User $user): string
    {
        $code = null;
        Notification::assertSentTo($user, EmailCodeNotification::class, function (EmailCodeNotification $mail) use (&$code) {
            $code = $mail->code;
            return true;
        });

        return $code;
    }

    private function wrong(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function test_signing_up_in_the_app_sends_a_code_and_the_code_verifies(): void
    {
        $response = $this->postJson('/api/v1/register', ['email' => 'asha@gmail.com', 'password' => 'secret123'])
            ->assertCreated()
            ->assertJsonPath('data.user.email_verified', false);
        $user = User::where('email', 'asha@gmail.com')->firstOrFail();
        $code = $this->codeFor($user);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, EmailCode::firstOrFail()->code_hash); // only a hash is kept

        Sanctum::actingAs($user);
        // Opening the screen keeps the code already in the inbox.
        $this->postJson('/api/v1/me/email/code')->assertOk()->assertJsonPath('data.sent', false);
        Notification::assertSentToTimes($user, EmailCodeNotification::class, 1);

        $this->postJson('/api/v1/me/email/verify', ['code' => $this->wrong($code)])
            ->assertStatus(422)->assertJsonPath('errors.code.0', 'That code is not right. 4 tries left.');
        $this->postJson('/api/v1/me/email/verify', ['code' => $code])
            ->assertOk()->assertJsonPath('data.email_verified', true);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame(0, EmailCode::count());
        $this->getJson('/api/v1/me')->assertJsonPath('data.email_verified', true);
    }

    public function test_send_again_waits_a_minute_and_replaces_the_code(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/email/code')->assertJsonPath('data.sent', true)->assertJsonPath('data.retry_after', 60);
        $first = $this->codeFor($user);
        $this->postJson('/api/v1/me/email/code', ['again' => true])->assertJsonPath('data.sent', false);

        $this->travel(61)->seconds();
        $this->postJson('/api/v1/me/email/code', ['again' => true])->assertJsonPath('data.sent', true);
        Notification::assertSentToTimes($user, EmailCodeNotification::class, 2);
        $second = $this->codeFor($user);

        if ($first !== $second) {
            $this->postJson('/api/v1/me/email/verify', ['code' => $first])->assertStatus(422);
        }
        $this->postJson('/api/v1/me/email/verify', ['code' => $second])->assertOk();
    }

    public function test_five_wrong_tries_or_ten_minutes_end_a_code(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/me/email/code');
        $code = $this->codeFor($user);

        foreach (range(1, 5) as $_) $this->postJson('/api/v1/me/email/verify', ['code' => $this->wrong($code)]);
        $this->postJson('/api/v1/me/email/verify', ['code' => $code])
            ->assertStatus(422)->assertJsonPath('errors.code.0', 'Too many wrong tries. Send a new code.');

        // A spent code is replaced on the next visit to the screen, and that one expires in ten minutes.
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/me/email/code')->assertJsonPath('data.sent', true);
        $this->travel(11)->minutes();
        $this->postJson('/api/v1/me/email/verify', ['code' => $this->codeFor($user)])
            ->assertStatus(422)->assertJsonPath('errors.code.0', 'That code has expired. Send a new one.');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_throwaway_inboxes_cannot_sign_up(): void
    {
        $this->postJson('/api/v1/register', ['email' => 'someone@mailinator.com', 'password' => 'secret123'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Use your own email address. Temporary inboxes cannot be used.');
        $this->post('/register', ['name' => 'X', 'email' => 'x@yopmail.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertSame(0, User::count());
        Notification::assertNothingSent();
    }

    public function test_a_new_email_has_to_be_verified_again(): void
    {
        $user = User::factory()->create(['email' => 'old@gmail.com']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me/profile', ['name' => 'Asha', 'email' => 'new@gmail.com'])
            ->assertOk()->assertJsonPath('data.email_verified', false);
        $this->assertSame('new@gmail.com', $user->fresh()->email);
        $this->postJson('/api/v1/me/email/verify', ['code' => $this->codeFor($user)])->assertOk();

        // Saving the same address again changes nothing.
        $this->patchJson('/api/v1/me/profile', ['name' => 'Asha R', 'email' => 'new@gmail.com'])->assertJsonPath('data.email_verified', true);
    }

    public function test_the_app_api_blocks_unverified_accounts_only_once_switched_on(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        // Off: the Play Store build that cannot ask for a code keeps working.
        $this->getJson('/api/v1/categories')->assertOk();

        config(['services.email_check.required_for_api' => true]);
        $this->getJson('/api/v1/categories')->assertForbidden()->assertJsonPath('code', 'email_unverified');
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email_verified', false);
        $this->postJson('/api/v1/me/email/code')->assertOk();
        $this->postJson('/api/v1/me/email/verify', ['code' => $this->codeFor($user)])->assertOk();
        $this->getJson('/api/v1/categories')->assertOk();
    }

    public function test_the_website_asks_for_the_code_before_anything_else(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'asha@gmail.com']);
        $user->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/verify-email');
        $this->actingAs($user)->get('/admin')->assertRedirect('/verify-email');
        $this->actingAs($user)->get('/verify-email')->assertOk()->assertSee('asha@gmail.com')->assertSee('6-digit code');
        $code = $this->codeFor($user);
        // Reloading the page keeps the same code.
        $this->actingAs($user)->get('/verify-email');
        Notification::assertSentToTimes($user, EmailCodeNotification::class, 1);

        $this->actingAs($user)->post('/verify-email', ['code' => $this->wrong($code)])->assertSessionHasErrors('code');
        $this->actingAs($user)->post('/verify-email', ['code' => $code])->assertRedirect('/dashboard');
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/verify-email')->assertRedirect('/dashboard');
    }

    public function test_a_typo_in_the_email_is_fixed_on_the_code_page(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'asha@gmial.com']);

        $this->actingAs($user)->patch('/verify-email/email', ['email' => 'Asha@Gmail.com'])
            ->assertRedirect('/verify-email')->assertSessionHas('status', 'Code sent to asha@gmail.com.');
        $this->assertSame('asha@gmail.com', $user->fresh()->email);

        $this->actingAs($user)->post('/verify-email', ['code' => $this->codeFor($user)])->assertRedirect('/dashboard');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_google_accounts_are_already_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $user->forceFill(['email_verified_at' => now(), 'google_id' => 'g-1'])->save();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')->assertJsonPath('data.email_verified', true);
        $this->postJson('/api/v1/me/email/code')->assertJsonPath('data.sent', false);
        Notification::assertNothingSent();
    }
}
