<?php

namespace Tests\Feature\Tracker;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AccountAndCurrencyTest extends TrackerTestCase
{
    public function test_a_currency_needs_a_rate_before_it_can_be_picked(): void
    {
        $this->put('/settings/currency', ['currency' => 'USD'])->assertRedirect('/settings/currency/USD?select=1');
        $this->assertSame('INR', $this->user->fresh()->currency);

        $this->put('/settings/currency/USD', ['rate' => '80', 'select' => 1])->assertRedirect('/settings/currency');
        $this->assertSame('USD', $this->user->fresh()->currency);

        $this->expense(800000); // ₹8,000
        $this->get('/dashboard')->assertSee('$100.00');
    }

    public function test_custom_currencies_are_added_and_removed(): void
    {
        $this->put('/settings/currency/new', ['code' => 'thb', 'label' => 'Thai Baht', 'symbol' => '฿', 'rate' => '2.5'])
            ->assertSessionHas('success', 'THB added');
        $this->put('/settings/currency', ['currency' => 'THB']);
        $this->assertSame('THB', $this->user->fresh()->currency);

        $this->put('/settings/currency/new', ['code' => 'THB', 'label' => 'Again', 'symbol' => '฿', 'rate' => '2'])
            ->assertSessionHasErrors(['code' => 'THB is already in the list.']);
        $this->put('/settings/currency/new', ['code' => 'XY1', 'label' => 'Bad', 'symbol' => 'x', 'rate' => '0'])
            ->assertSessionHasErrors(['code', 'rate']);

        // Removing the picked one goes back to rupees.
        $this->delete('/settings/currency/THB')->assertSessionHas('success', 'Thai Baht removed');
        $this->assertSame('INR', $this->user->fresh()->currency);
        $this->assertSame(0, $this->user->userCurrencies()->count());
    }

    public function test_account_information(): void
    {
        $this->patch('/profile', ['name' => 'Asha R', 'email' => ' ASHA@Example.com ', 'birthday' => '1990-07-15'])
            ->assertSessionHas('success', 'Account details saved');
        $user = $this->user->fresh();
        $this->assertSame(['Asha R', 'asha@example.com', '1990-07-15'], [$user->name, $user->email, $user->birthday->toDateString()]);

        $this->makeUser(['email' => 'taken@example.com']);
        $this->patch('/profile', ['name' => str_repeat('a', 41), 'email' => 'taken@example.com', 'birthday' => now()->toDateString()])
            ->assertSessionHasErrors([
                'name' => 'Keep your name to 40 characters.',
                'email' => 'An account with that email already exists.',
                'birthday' => 'Choose a real date of birth.',
            ]);

        // A blank name is allowed.
        $this->patch('/profile', ['name' => '   ', 'email' => 'asha@example.com'])->assertSessionHasNoErrors();
        $this->assertSame('', $this->user->fresh()->name);
    }

    public function test_changing_and_setting_a_password(): void
    {
        $this->user->update(['password' => Hash::make('oldpass123')]);

        $this->put('/settings/password', ['current_password' => 'wrong', 'new_password' => 'newpass123', 'new_password_confirmation' => 'newpass123'])
            ->assertSessionHasErrors(['current_password' => 'Your current password is not right.']);
        $this->put('/settings/password', ['current_password' => 'oldpass123', 'new_password' => 'short', 'new_password_confirmation' => 'short'])
            ->assertSessionHasErrors(['new_password' => 'Use at least 8 characters.']);
        $this->put('/settings/password', ['current_password' => 'oldpass123', 'new_password' => 'newpass123', 'new_password_confirmation' => 'newpass124'])
            ->assertSessionHasErrors(['new_password' => 'The two new passwords do not match.']);

        $this->put('/settings/password', ['current_password' => 'oldpass123', 'new_password' => 'newpass123', 'new_password_confirmation' => 'newpass123'])
            ->assertSessionHas('success', 'Password changed');
        $this->assertTrue(Hash::check('newpass123', $this->user->fresh()->password));

        // A Google account sets its first one.
        $google = $this->makeUser(['email' => 'g@example.com']);
        $google->forceFill(['password' => null])->save();
        $this->actingAs($google)->get('/settings/password')->assertSee('Set a password')->assertSee('You sign in with Google.');
        $this->put('/settings/password', ['new_password' => 'firstpass1', 'new_password_confirmation' => 'firstpass1'])
            ->assertSessionHas('success', 'Password set');
    }

    public function test_deleting_the_account_takes_everything_with_it(): void
    {
        $this->user->update(['password' => Hash::make('secret123')]);
        $this->expense(1000);
        $this->income(1000);

        $this->delete('/profile', ['password' => 'nope'])->assertSessionHasErrorsIn('userDeletion', ['password' => 'Incorrect password.']);
        $this->assertModelExists($this->user);

        $this->delete('/profile', ['password' => 'secret123'])->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull(User::find($this->user->id));
        $this->assertDatabaseCount('app_expenses', 0);
        $this->assertDatabaseCount('app_incomes', 0);
    }

    public function test_email_notifications_switch(): void
    {
        $this->put('/settings/email-notifications', ['enabled' => 0])->assertSessionHas('success', 'Email notifications turned off');
        $this->assertFalse($this->user->fresh()->email_notifications);
        $this->put('/settings/email-notifications', ['enabled' => 1]);
        $this->assertTrue($this->user->fresh()->email_notifications);
    }

    public function test_signing_up_on_the_website_starts_with_the_apps_categories(): void
    {
        auth()->logout();
        $this->post('/register', ['name' => 'New Person', 'email' => 'new@example.com', 'password' => 'password123', 'password_confirmation' => 'password123']);
        $this->assertSame(8, User::where('email', 'new@example.com')->firstOrFail()->categories()->count());
    }
}
