<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Support\DefaultCategories;
use App\Support\Totp;
use App\Support\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SignsSocialTokens;
use Tests\TestCase;

/** Admins' two-factor sign-in: setting it up in Admin › Settings, and the code step when signing in. */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase, SignsSocialTokens;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // A fixed moment, so the codes below are the ones the server expects.
        $this->travelTo(Carbon::createFromTimestamp(1_800_000_000));
    }

    private function admin(bool $withTwoFactor = false, string $email = 'boss@example.com'): User
    {
        $admin = User::factory()->create(['email' => $email, 'password' => 'secret123', 'onboarded' => true]);
        DefaultCategories::seed($admin);
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        if ($withTwoFactor) {
            $admin->forceFill([
                'two_factor_secret'         => self::SECRET,
                'two_factor_confirmed_at'   => now(),
                'two_factor_recovery_codes' => [hash('sha256', 'abcde23456'), hash('sha256', 'fghjk78923')],
            ])->save();
        }

        return $admin->fresh();
    }

    private function code(int $stepsFromNow = 0, string $secret = self::SECRET): string
    {
        return Totp::code($secret, Totp::step() + $stepsFromNow);
    }

    public function test_codes_match_the_rfc_6238_test_vector(): void
    {
        // RFC 6238 appendix B, SHA-1, secret "12345678901234567890", last 6 of the 8 digits.
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);
        $this->assertSame('287082', Totp::code($secret, Totp::step(59)));
        $this->assertSame('081804', Totp::code($secret, Totp::step(1111111109)));
        $this->assertSame('050471', Totp::code($secret, Totp::step(1111111111)));
        $this->assertSame('005924', Totp::code($secret, Totp::step(1234567890)));
        $this->assertSame('12345678901234567890', Totp::base32Decode($secret));
    }

    public function test_one_step_of_clock_drift_is_allowed_and_no_more(): void
    {
        foreach ([-1, 0, 1] as $drift) {
            $this->assertNotNull(Totp::match(self::SECRET, $this->code($drift)), "drift {$drift}");
        }
        $this->assertNull(Totp::match(self::SECRET, $this->code(2)));
        $this->assertNull(Totp::match(self::SECRET, $this->code(-2)));
        $this->assertNull(Totp::match(self::SECRET, 'abcdef'));
    }

    public function test_an_admin_turns_it_on_with_a_qr_code_and_a_confirming_code(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/settings')->assertOk()
            ->assertSee('Two-factor authentication')->assertSee('Turn on');

        $this->post('/admin/two-factor')->assertRedirect();
        $admin->refresh();
        $secret = $admin->two_factor_secret;
        $this->assertNotNull($secret);
        $this->assertNull($admin->two_factor_confirmed_at);
        // Stored encrypted.
        $this->assertNotSame($secret, DB::table('users')->where('id', $admin->id)->value('two_factor_secret'));

        $page = $this->get('/admin/settings')->assertOk()->assertSee('Setting up')->assertSee('window.qrSvg', false);
        $page->assertSee(trim(chunk_split($secret, 4, ' ')));
        $this->assertStringContainsString('otpauth:\/\/totp\/', $page->getContent());

        // A wrong code does not turn it on.
        $this->post('/admin/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrorsIn('twoFactor', 'code');
        $this->assertFalse($admin->fresh()->hasTwoFactor());

        $response = $this->post('/admin/two-factor/confirm', ['code' => $this->code(0, $secret)])
            ->assertSessionHas('success', 'Two-factor authentication is on.');
        $codes = $response->getSession()->get('two_factor_recovery_codes');
        $this->assertCount(8, $codes);
        $this->assertMatchesRegularExpression('/^[a-z2-9]{5}-[a-z2-9]{5}$/', $codes[0]);
        $this->assertTrue($admin->fresh()->hasTwoFactor());
        // Only hashes are kept.
        $stored = DB::table('users')->where('id', $admin->id)->value('two_factor_recovery_codes');
        $this->assertStringNotContainsString($codes[0], $stored);
        $this->assertSame('two_factor.enabled', AdminActivityLog::latest('id')->firstOrFail()->action);

        // Shown once, on the page right after.
        $this->withSession(['two_factor_recovery_codes' => $codes])->get('/admin/settings')->assertSee($codes[0]);
        $this->get('/admin/settings')->assertOk()->assertDontSee($codes[0])->assertSee('8 of 8 recovery codes left');
    }

    public function test_a_password_sign_in_waits_for_the_code(): void
    {
        $admin = $this->admin(withTwoFactor: true);

        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'secret123'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get('/two-factor-challenge')->assertOk()->assertSee('Two-step check');

        // Not in until the code: the admin pages still send to sign-in.
        $this->get('/admin')->assertRedirect(route('login'));

        $this->post('/two-factor-challenge', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        // On to the page that sent them to sign in.
        $this->post('/two-factor-challenge', ['code' => $this->code()])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_code_from_a_moment_ago_works_but_not_twice(): void
    {
        $this->admin(withTwoFactor: true);
        $code = $this->code(-1);

        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'secret123']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $this->post('/logout');
        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'secret123']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $admin = $this->admin(withTwoFactor: true);

        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'secret123']);
        $this->post('/two-factor-challenge', ['code' => 'ABCDE-23456'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertSame(1, TwoFactor::recoveryCodesLeft($admin->fresh()));

        $this->post('/logout');
        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'secret123']);
        $this->post('/two-factor-challenge', ['code' => 'abcde-23456'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_too_many_wrong_codes_start_the_sign_in_over(): void
    {
        $this->admin(withTwoFactor: true);
        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'secret123']);

        for ($i = 1; $i < TwoFactor::MAX_TRIES; $i++) {
            $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post('/two-factor-challenge', ['code' => '000000'])->assertRedirect(route('login'));

        // The right code no longer helps without the password first.
        $this->post('/two-factor-challenge', ['code' => $this->code()])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
    }

    public function test_the_code_page_is_throttled(): void
    {
        $this->admin(withTwoFactor: true);
        $statuses = [];
        for ($i = 0; $i < 7; $i++) {
            $this->post('/login', ['email' => 'boss@example.com', 'password' => 'secret123']);
            $statuses[] = $this->post('/two-factor-challenge', ['code' => '000000'])->status();
        }
        $this->assertContains(429, $statuses);
    }

    public function test_a_held_sign_in_expires(): void
    {
        $this->admin(withTwoFactor: true);
        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'secret123']);

        $this->travel(11)->minutes();
        $this->post('/two-factor-challenge', ['code' => $this->code()])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_google_sign_in_on_the_website_also_asks_for_the_code(): void
    {
        config(['services.google.web_client_id' => 'web-client']);
        $this->fakeSocialKeys();
        $admin = $this->admin(withTwoFactor: true);
        $admin->forceFill(['google_id' => 'google-111'])->save();

        $this->post('/auth/google', ['credential' => $this->googleIdToken(['email' => 'boss@example.com'])])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $this->code()])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_turning_it_off_takes_the_password_and_a_code(): void
    {
        $admin = $this->admin(withTwoFactor: true);
        $this->actingAs($admin);

        $this->delete('/admin/two-factor', ['password' => 'wrong', 'disable_code' => $this->code()])
            ->assertSessionHasErrorsIn('twoFactor', 'password');
        $this->delete('/admin/two-factor', ['password' => 'secret123', 'disable_code' => '000000'])
            ->assertSessionHasErrorsIn('twoFactor', 'disable_code');
        $this->delete('/admin/two-factor', ['disable_code' => $this->code()])
            ->assertSessionHasErrorsIn('twoFactor', 'password');
        $this->assertTrue($admin->fresh()->hasTwoFactor());

        $this->delete('/admin/two-factor', ['password' => 'secret123', 'disable_code' => $this->code()])
            ->assertSessionHas('success', 'Two-factor authentication is off.');
        $fresh = $admin->fresh();
        $this->assertFalse($fresh->hasTwoFactor());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertSame('two_factor.disabled', AdminActivityLog::latest('id')->firstOrFail()->action);
    }

    public function test_new_recovery_codes_replace_the_old_ones(): void
    {
        $admin = $this->admin(withTwoFactor: true);

        $codes = $this->actingAs($admin)->post('/admin/two-factor/recovery-codes', ['recovery_code_check' => $this->code()])
            ->assertSessionHas('success')
            ->getSession()->get('two_factor_recovery_codes');
        $this->assertCount(8, $codes);
        $this->assertSame(8, TwoFactor::recoveryCodesLeft($admin->fresh()));
        $this->assertNull(TwoFactor::check($admin->fresh(), 'abcde-23456'));
        $this->assertSame('recovery', TwoFactor::check($admin->fresh(), $codes[3]));
    }

    public function test_the_dashboard_recommends_it_until_it_is_on(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertOk()->assertSee('Protect your admin account.');
        $this->actingAs($this->admin(withTwoFactor: true, email: 'second@example.com'))->get('/admin')->assertOk()->assertDontSee('Protect your admin account.');
    }
}
