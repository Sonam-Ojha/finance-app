<?php

namespace Tests\Feature\Api\V1;

use Laravel\Sanctum\Sanctum;

class UserCurrenciesTest extends ApiTestCase
{
    public function test_rates_and_added_currencies_are_kept_per_user(): void
    {
        $this->signIn();

        $this->putJson('/api/v1/me/currencies/THB', ['rate' => 2.31, 'label' => 'Thai Baht', 'symbol' => '฿'])
            ->assertOk()
            ->assertExactJson(['data' => ['code' => 'THB', 'label' => 'Thai Baht', 'symbol' => '฿', 'rate' => 2.31]]);
        // A built-in code keeps its own name and symbol; only the rate is stored.
        $this->putJson('/api/v1/me/currencies/USD', ['rate' => '83.25', 'label' => 'Dollar', 'symbol' => 'D'])
            ->assertOk()
            ->assertJsonPath('data.label', null)
            ->assertJsonPath('data.symbol', null)
            ->assertJsonPath('data.rate', 83.25);
        // Updating, not adding a second row.
        $this->putJson('/api/v1/me/currencies/USD', ['rate' => 84])->assertOk()->assertJsonPath('data.rate', 84);

        $this->getJson('/api/v1/me/currencies')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'THB')
            ->assertJsonPath('data.1.rate', 84);
        $this->getJson('/api/v1/bootstrap')->assertJsonCount(2, 'data.currencies');

        Sanctum::actingAs($this->makeUser(['email' => 'other@example.com']));
        $this->getJson('/api/v1/me/currencies')->assertExactJson(['data' => []]);
    }

    public function test_bad_currencies_are_rejected_with_the_apps_messages(): void
    {
        $this->signIn();

        $this->putJson('/api/v1/me/currencies/INR', ['rate' => 1])->assertUnprocessable();
        $this->putJson('/api/v1/me/currencies/thb', ['rate' => 1, 'label' => 'x', 'symbol' => 'x'])
            ->assertJsonPath('errors.code.0', 'Give it a code of 2 to 5 letters, like THB.');
        $this->putJson('/api/v1/me/currencies/THB', ['rate' => 2.31, 'symbol' => '฿'])
            ->assertJsonPath('errors.label.0', 'Give the currency a name.');
        $this->putJson('/api/v1/me/currencies/THB', ['rate' => 2.31, 'label' => 'Thai Baht'])
            ->assertJsonPath('errors.symbol.0', 'Pick a symbol, or type one — the code works too.');
        foreach ([0, -1, 'abc', '1.1234567', '1234567890'] as $rate) {
            $this->putJson('/api/v1/me/currencies/USD', ['rate' => $rate])
                ->assertJsonPath('errors.rate.0', 'Enter how many rupees one USD is worth.');
        }
    }

    public function test_a_users_own_currency_can_be_selected_and_removing_it_falls_back_to_rupees(): void
    {
        $user = $this->signIn();

        $this->putJson('/api/v1/settings/currency', ['currency' => 'THB'])->assertUnprocessable();
        $this->putJson('/api/v1/me/currencies/THB', ['rate' => 2.31, 'label' => 'Thai Baht', 'symbol' => '฿']);
        $this->putJson('/api/v1/settings/currency', ['currency' => 'THB'])->assertNoContent();
        $this->assertSame('THB', $user->fresh()->currency);

        $this->deleteJson('/api/v1/me/currencies/THB')->assertNoContent();
        $this->assertSame('INR', $user->fresh()->currency);
        $this->getJson('/api/v1/me/currencies')->assertExactJson(['data' => []]);

        // Removing a built-in's rate leaves a selected built-in alone.
        $this->putJson('/api/v1/me/currencies/USD', ['rate' => 83]);
        $this->putJson('/api/v1/settings/currency', ['currency' => 'USD'])->assertNoContent();
        $this->deleteJson('/api/v1/me/currencies/USD')->assertNoContent();
        $this->assertSame('USD', $user->fresh()->currency);
    }
}
