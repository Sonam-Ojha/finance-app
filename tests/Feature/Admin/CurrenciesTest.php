<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Currency;
use App\Models\User;
use App\Models\UserCurrency;
use App\Support\Currencies;
use App\Support\DisplayCurrency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Admin › Currencies, and the list the app and the website offer. */
class CurrenciesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = tap(User::factory()->create())->forceFill(['role' => User::ROLE_ADMIN]);
        $this->admin->save();
    }

    public function test_the_api_list_keeps_its_exact_shape_from_the_table(): void
    {
        $this->getJson('/api/v1/currencies')->assertOk()->assertExactJson(['data' => [
            ['code' => 'INR', 'symbol' => '₹',   'decimals' => 2, 'label' => 'Indian Rupee'],
            ['code' => 'USD', 'symbol' => '$',   'decimals' => 2, 'label' => 'US Dollar'],
            ['code' => 'EUR', 'symbol' => '€',   'decimals' => 2, 'label' => 'Euro'],
            ['code' => 'GBP', 'symbol' => '£',   'decimals' => 2, 'label' => 'British Pound'],
            ['code' => 'JPY', 'symbol' => '¥',   'decimals' => 0, 'label' => 'Japanese Yen'],
            ['code' => 'AUD', 'symbol' => 'A$',  'decimals' => 2, 'label' => 'Australian Dollar'],
            ['code' => 'CAD', 'symbol' => 'C$',  'decimals' => 2, 'label' => 'Canadian Dollar'],
            ['code' => 'AED', 'symbol' => 'AED', 'decimals' => 2, 'label' => 'UAE Dirham'],
            ['code' => 'SGD', 'symbol' => 'S$',  'decimals' => 2, 'label' => 'Singapore Dollar'],
        ]]);
    }

    public function test_only_admins_reach_the_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/currencies')->assertForbidden();
        $this->actingAs($user)->patch('/admin/currencies/USD/toggle')->assertForbidden();

        User::factory()->count(2)->create(['currency' => 'USD']);
        $this->actingAs($this->admin)->get('/admin/currencies')->assertOk()
            ->assertSee('Currencies')
            ->assertSee('US Dollar')
            ->assertSee('Payment methods')
            ->assertSee('Bank transfer')
            ->assertSee('Always');
    }

    public function test_adding_switching_off_and_deleting_change_what_is_offered(): void
    {
        $this->actingAs($this->admin)->post('/admin/currencies', [
            'code' => 'thb', 'name' => 'Thai Baht', 'symbol' => '฿', 'decimals' => 2, 'default_rate' => '2.45', 'enabled' => '1',
        ])->assertRedirect('/admin/currencies')->assertSessionHas('success');
        $this->assertContains(['code' => 'THB', 'symbol' => '฿', 'decimals' => 2, 'label' => 'Thai Baht'], $this->getJson('/api/v1/currencies')->json('data'));
        $this->assertSame(2.45, Currencies::defaultRate('THB'));

        $this->actingAs($this->admin)->patch('/admin/currencies/JPY/toggle')->assertRedirect();
        $codes = array_column($this->getJson('/api/v1/currencies')->json('data'), 'code');
        $this->assertNotContains('JPY', $codes);
        $this->assertSame('INR', $codes[0]);

        $this->actingAs($this->admin)->put('/admin/currencies/USD', ['name' => 'American Dollar', 'symbol' => 'US$', 'decimals' => 2, 'default_rate' => '83.25'])
            ->assertRedirect('/admin/currencies');
        $this->assertContains(['code' => 'USD', 'symbol' => 'US$', 'decimals' => 2, 'label' => 'American Dollar'], $this->getJson('/api/v1/currencies')->json('data'));

        $this->actingAs($this->admin)->delete('/admin/currencies/SGD')->assertRedirect('/admin/currencies');
        $this->assertNotContains('SGD', array_column($this->getJson('/api/v1/currencies')->json('data'), 'code'));

        $this->assertSame(
            ['currency.added', 'currency.disabled', 'currency.updated', 'currency.deleted'],
            AdminActivityLog::orderBy('id')->pluck('action')->all(),
        );

        // Suggested rates: only the ones on offer that have one.
        $this->getJson('/api/v1/currencies/rates')->assertOk()->assertExactJson(['data' => [
            ['code' => 'USD', 'rate' => 83.25],
            ['code' => 'THB', 'rate' => 2.45],
        ]]);
    }

    public function test_rupees_cannot_be_switched_off_edited_or_deleted(): void
    {
        $this->actingAs($this->admin)->patch('/admin/currencies/INR/toggle')->assertSessionHas('error');
        $this->actingAs($this->admin)->delete('/admin/currencies/INR')->assertSessionHas('error');
        $this->actingAs($this->admin)->get('/admin/currencies/INR/edit')->assertNotFound();
        $this->actingAs($this->admin)->put('/admin/currencies/INR', ['name' => 'Rupee', 'symbol' => 'Rs', 'decimals' => 0])->assertNotFound();
        $this->assertDatabaseHas('currencies', ['code' => 'INR', 'enabled' => true, 'symbol' => '₹', 'decimals' => 2]);

        // Even with the row gone some other way, rupees are on offer.
        Currency::where('code', 'INR')->delete();
        $this->assertSame('INR', Currencies::list()[0]['code']);
    }

    public function test_bad_input_is_refused(): void
    {
        $this->actingAs($this->admin)->post('/admin/currencies', ['code' => 'USD', 'name' => '', 'symbol' => '', 'decimals' => 5, 'default_rate' => '-3'])
            ->assertSessionHasErrors(['code', 'name', 'symbol', 'decimals', 'default_rate']);
        $this->actingAs($this->admin)->post('/admin/currencies', ['code' => 'X1', 'name' => 'Odd', 'symbol' => 'O', 'decimals' => 2, 'default_rate' => '0'])
            ->assertSessionHasErrors(['code', 'default_rate']);
        $this->assertSame(9, Currency::count());
    }

    public function test_switched_off_currencies_leave_the_website_and_the_currency_setting(): void
    {
        $user = User::factory()->create(['currency' => 'INR']);
        $this->actingAs($this->admin)->patch('/admin/currencies/EUR/toggle');

        $this->assertNotContains('EUR', array_map(fn ($c) => $c->code, DisplayCurrency::allFor($user)));

        Sanctum::actingAs($user);
        $this->putJson('/api/v1/settings/currency', ['currency' => 'EUR'])->assertUnprocessable();
        $this->putJson('/api/v1/settings/currency', ['currency' => 'USD'])->assertNoContent();

        // Someone who set a rate for it before keeps it, as their own.
        UserCurrency::create(['user_id' => $user->id, 'code' => 'EUR', 'rate' => 90]);
        $eur = collect(DisplayCurrency::allFor($user->fresh()))->firstWhere('code', 'EUR');
        $this->assertTrue($eur->custom);
        $this->assertSame('€', $eur->symbol);
        $this->assertSame('Euro', $eur->label);
    }

    public function test_the_website_fills_in_the_suggested_rate(): void
    {
        $this->actingAs($this->admin)->put('/admin/currencies/GBP', ['name' => 'British Pound', 'symbol' => '£', 'decimals' => 2, 'default_rate' => '105.5']);

        $user = User::factory()->create(['currency' => 'INR', 'onboarded' => true]);
        $this->actingAs($user)->get('/settings/currency/GBP')->assertOk()
            ->assertSee('105.5')
            ->assertSee('We filled in a recent rate.');

        // Their own rate wins.
        UserCurrency::create(['user_id' => $user->id, 'code' => 'GBP', 'rate' => 110]);
        $this->actingAs($user)->get('/settings/currency/GBP')->assertOk()->assertDontSee('We filled in a recent rate.');
    }
}
