<?php

namespace Tests\Feature\Auth;

use App\Models\Category;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Concerns\SignsSocialTokens;
use Tests\TestCase;

/** "Continue with Google" on the website's sign-in and sign-up pages. */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase, SignsSocialTokens;

    private const WEB_CLIENT = '822356009808-test.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        config(['services.google.client_ids' => ['android-client'], 'services.google.web_client_id' => self::WEB_CLIENT]);
        $this->fakeSocialKeys();
    }

    public function test_the_sign_in_and_sign_up_pages_show_the_button(): void
    {
        foreach (['/login', '/register'] as $page) {
            $this->get($page)->assertOk()
                ->assertSee('id="google-button"', false)
                ->assertSee("text: 'continue_with'", false)
                ->assertSee(self::WEB_CLIENT)
                ->assertSee('https://accounts.google.com/gsi/client', false)
                ->assertSee(json_encode(route('auth.google')), false);
        }

        config(['services.google.web_client_id' => null]);
        $this->get('/login')->assertOk()->assertDontSee('id="google-button"', false);
    }

    public function test_a_new_google_user_gets_a_verified_account_and_gives_a_birthday_first(): void
    {
        $this->post('/auth/google', ['credential' => $this->googleIdToken()])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'rishi@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-111', $user->google_id);
        $this->assertSame('Rishi Dubey', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertTrue($user->birthday_required);
        $this->assertSame(8, Category::where('user_id', $user->id)->count());

        // The date of birth comes before anything else.
        $this->get('/dashboard')->assertRedirect(route('profile.complete'));
        $this->get('/complete-profile')->assertOk()->assertSee('Complete your profile');
        $this->put('/complete-profile', ['birthday' => now()->subMonths(3)->toDateString()])->assertSessionHasErrors(['birthday' => 'Choose a real date of birth.']);
        $this->put('/complete-profile', ['birthday' => '1990-06-15'])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertSame('1990-06-15', $user->birthday->toDateString());
        $this->assertFalse($user->birthday_required);
        $this->get('/dashboard')->assertOk();
        $this->get('/complete-profile')->assertRedirect(route('dashboard'));
    }

    public function test_an_existing_verified_account_is_linked_and_signed_in(): void
    {
        $user = User::factory()->create(['email' => 'rishi@gmail.com', 'password' => 'secret123', 'birthday' => '1990-01-01']);
        DefaultCategories::seed($user);

        $this->post('/auth/google', ['credential' => $this->googleIdToken()])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-111', $user->fresh()->google_id);
        $this->assertNotNull($user->fresh()->password);
        $this->assertSame(1, User::count());
        $this->get('/dashboard')->assertOk();
    }

    public function test_an_account_whose_email_was_never_proved_is_claimed(): void
    {
        $squatter = User::factory()->create(['email' => 'rishi@gmail.com', 'password' => 'squatter123', 'email_verified_at' => null]);
        $phone = $squatter->createToken('api');
        DeviceToken::create(['user_id' => $squatter->id, 'access_token_id' => $phone->accessToken->id, 'token' => 'squatter-phone', 'platform' => 'android']);

        $this->post('/auth/google', ['credential' => $this->googleIdToken()])->assertRedirect(route('dashboard'));

        $claimed = $squatter->fresh();
        $this->assertAuthenticatedAs($claimed);
        $this->assertNull($claimed->password);
        $this->assertNotNull($claimed->email_verified_at);
        $this->assertSame(0, $claimed->tokens()->count());
        $this->assertSame(0, DeviceToken::count());

        $this->post('/logout');
        $this->post('/login', ['email' => 'rishi@gmail.com', 'password' => 'squatter123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_tokens_that_do_not_check_out_are_refused_kindly(): void
    {
        $refused = [
            $this->googleIdToken(['aud' => 'someone-elses-app']),
            $this->googleIdToken(['exp' => time() - 3600, 'iat' => time() - 7200]),
            'not-a-token',
        ];
        foreach ($refused as $credential) {
            $this->post('/auth/google', ['credential' => $credential])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['google' => 'Could not sign in with Google. Please try again.']);
        }

        $this->post('/auth/google', [])->assertSessionHasErrors(['google' => 'Google did not send a sign-in token. Please try again.']);
        $this->post('/auth/google', ['credential' => $this->googleIdToken(['email_verified' => false])])
            ->assertSessionHasErrors(['google' => 'Your Google account did not share a verified email address.']);

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_errors_go_back_to_the_page_the_button_was_on(): void
    {
        $this->from('/register')->post('/auth/google', ['credential' => 'not-a-token'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['google' => 'Could not sign in with Google. Please try again.']);

        // And the page shows it.
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['google' => 'Could not sign in with Google. Please try again.']));
        $this->withSession(['errors' => $errors])->get('/register')->assertSee('Could not sign in with Google. Please try again.');
    }

    public function test_a_signed_in_user_cannot_post_a_google_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/auth/google', ['credential' => $this->googleIdToken()])->assertRedirect();
        $this->assertSame(1, User::count());
    }
}
