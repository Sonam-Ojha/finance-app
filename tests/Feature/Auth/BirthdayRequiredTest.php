<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** A date of birth for new accounts; existing ones are never forced to add one. */
class BirthdayRequiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['onboarded' => true]);
        DefaultCategories::seed($user);

        return $user;
    }

    public function test_the_api_takes_an_optional_birthday_when_registering(): void
    {
        $this->postJson('/api/v1/register', ['email' => 'new@example.com', 'password' => 'secret123', 'birthday' => '1992-08-30'])
            ->assertCreated()
            ->assertJsonPath('data.user.birthday', '1992-08-30')
            ->assertJsonPath('data.user.birthday_required', false);

        // The 1.0 app sends none, and is not asked for one.
        $this->postJson('/api/v1/register', ['email' => 'old-app@example.com', 'password' => 'secret123'])
            ->assertCreated()
            ->assertJsonPath('data.user.birthday', null)
            ->assertJsonPath('data.user.birthday_required', false);

        foreach (['30-08-1992', now()->addWeek()->toDateString(), now()->subMonths(2)->toDateString(), now()->subYears(125)->toDateString()] as $bad) {
            $this->postJson('/api/v1/register', ['email' => 'bad@example.com', 'password' => 'secret123', 'birthday' => $bad])
                ->assertUnprocessable()
                ->assertJsonPath('errors.birthday.0', 'Choose a real date of birth.');
        }
        $this->assertNull(User::where('email', 'bad@example.com')->first());
    }

    public function test_existing_accounts_without_a_birthday_are_left_alone(): void
    {
        $user = $this->user(['birthday' => null]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.birthday_required', false);
    }

    public function test_the_website_asks_first_but_lets_the_user_sign_out_or_verify(): void
    {
        $user = $this->user(['birthday' => null, 'birthday_required' => true]);
        $this->actingAs($user);

        foreach (['/dashboard', '/transactions', '/profile', '/settings'] as $page) {
            $this->get($page)->assertRedirect(route('profile.complete'));
        }
        $this->post('/expenses', [])->assertRedirect(route('profile.complete'));
        $this->get('/complete-profile')->assertOk()->assertSee('Date of birth');

        // The email-code page stays open (an unverified account is sent there first).
        $user->forceFill(['email_verified_at' => null])->save();
        $this->get('/verify-email')->assertOk();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->put('/complete-profile', [])->assertSessionHasErrors(['birthday' => 'Enter your date of birth.']);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_a_birthday_saved_on_the_profile_page_or_in_the_app_answers_it(): void
    {
        $web = $this->user(['email' => 'web@example.com', 'birthday' => null, 'birthday_required' => true]);
        $web->update(['birthday' => '1988-03-04']);
        $this->assertFalse($web->fresh()->birthday_required);

        $app = $this->user(['email' => 'app@example.com', 'birthday' => null, 'birthday_required' => true]);
        Sanctum::actingAs($app);
        // Saving no birthday does not answer it.
        $this->patchJson('/api/v1/me/profile', ['name' => 'Asha'])->assertOk()->assertJsonPath('data.birthday_required', true);
        $this->patchJson('/api/v1/me/profile', ['birthday' => '1991-11-12'])->assertOk()->assertJsonPath('data.birthday_required', false);
    }
}
