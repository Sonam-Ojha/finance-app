<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** A form left open past the session: back to a fresh page with a reason, not a bare 419 (bootstrap/app.php). */
class ExpiredPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // CSRF checks are off in tests, so stand in for an expired token.
        Route::middleware('web')->post('/_expired', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
    }

    public function test_an_expired_form_goes_back_with_a_message(): void
    {
        $this->from('/login')->post('/_expired', ['email' => 'asha@example.com', 'password' => 'secret123'])
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'That page was open for a long time and expired. Please try again.')
            ->assertSessionHasInput('email', 'asha@example.com')
            ->assertSessionMissing('_old_input.password');

        $this->get('/login')->assertSee('That page was open for a long time and expired.');
    }

    public function test_the_app_and_scripts_still_get_419(): void
    {
        $this->postJson('/_expired')->assertStatus(419);
    }
}
