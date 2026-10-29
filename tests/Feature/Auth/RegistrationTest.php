<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'birthday' => '1995-04-12',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertSame('1995-04-12', User::where('email', 'test@example.com')->first()->birthday->toDateString());
        $this->assertFalse(User::where('email', 'test@example.com')->first()->birthday_required);
    }

    public function test_a_date_of_birth_is_required_and_must_be_real(): void
    {
        $this->withoutVite();
        $this->get('/register')->assertOk()->assertSee('name="birthday"', false);

        $base = ['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password'];
        $refused = [
            'missing'         => [null, 'Enter your date of birth.'],
            'not a date'      => ['12-04-1995', 'Choose a real date of birth.'],
            'in the future'   => [now()->addMonth()->toDateString(), 'Choose a real date of birth.'],
            'under a year'    => [now()->subMonths(6)->toDateString(), 'Choose a real date of birth.'],
            'over 120'        => [now()->subYears(130)->toDateString(), 'Choose a real date of birth.'],
        ];
        foreach ($refused as $why => [$birthday, $message]) {
            $this->post('/register', $base + array_filter(['birthday' => $birthday]))
                ->assertSessionHasErrors(['birthday' => $message]);
            $this->assertGuest();
        }
        $this->assertSame(0, User::count());
    }
}
