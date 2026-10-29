<?php

namespace Tests\Feature;

use App\Http\Controllers\RetiredController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The older "Smart Paisa" pages and API are retired: redirects and 410 Gone, the data kept. */
class RetiredSmartPaisaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_api_answers_gone_for_every_path_and_method_without_signing_in(): void
    {
        $gone = ['message' => RetiredController::API_MESSAGE, 'code' => 'api_retired'];

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'secret'])->assertStatus(410)->assertExactJson($gone);
        $this->postJson('/api/auth/register', [])->assertStatus(410)->assertExactJson($gone);
        $this->getJson('/api/income')->assertStatus(410)->assertExactJson($gone);
        $this->postJson('/api/bank/1/transaction', ['amount' => 5])->assertStatus(410);
        $this->deleteJson('/api/settings/expense-category/3')->assertStatus(410);
        $this->patchJson('/api/reminder/2/done')->assertStatus(410);
        $this->getJson('/api/dashboard')->assertStatus(410);

        // Rate-limited like any public endpoint.
        $route = Route::getRoutes()->match(Request::create('/api/income', 'GET'));
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());

        // /api/v1 is untouched.
        $this->getJson('/api/v1/currencies')->assertOk();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_the_old_pages_redirect_permanently_to_the_new_ones(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());

        $expect = [
            '/income'                         => '/transactions?type=income',
            '/income/create'                  => '/transactions?type=income',
            '/expense/12/edit'                => '/transactions?type=expense',
            '/expense-categories'             => '/categories',
            '/bank'                           => '/dashboard',
            '/credit-card/3'                  => '/dashboard',
            '/loan/4/edit'                    => '/recurring',
            '/investment'                     => '/goals',
            '/commission/create'              => '/transactions?type=income',
            '/cashback'                       => '/transactions?type=income',
            '/bad-debt'                       => '/groups',
            '/contact/7'                      => '/groups',
            '/reminder'                       => '/settings',
            '/report'                         => '/analytics',
            '/settings/masters'               => '/settings',
        ];
        foreach ($expect as $old => $new) {
            $this->get($old)->assertStatus(301)->assertRedirect(url($new));
        }

        // Old forms too; the report form's month carries over.
        $this->post('/report/generate', ['type' => 'expense', 'month' => 9, 'year' => 2026, 'format' => 'pdf'])
            ->assertStatus(301)->assertRedirect(url('/reports/monthly.pdf?month=2026-09'));
        $this->post('/report/generate', ['type' => 'loan', 'year' => 2026])->assertRedirect(url('/reports/monthly.pdf'));
        $this->delete('/settings/expense-category/3')->assertStatus(301)->assertRedirect(url('/categories'));

        // The new pages with similar addresses are not caught.
        $this->get('/settings')->assertOk();
        $this->get('/expenses/create')->assertOk();
        $this->get('/incomes/create')->assertOk();
    }

    public function test_signed_out_the_redirect_leads_to_sign_in(): void
    {
        $this->get('/bank/1')->assertStatus(301)->assertRedirect(url('/dashboard'));
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_the_old_data_stays(): void
    {
        foreach (['expenses', 'incomes', 'bank_accounts', 'credit_cards', 'loans', 'investments', 'commissions', 'cashbacks', 'bad_debts', 'contacts', 'reminders'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} is kept");
        }
        $user = User::factory()->create();
        DB::table('bank_accounts')->insert(['user_id' => $user->id, 'bank_name' => 'SBI', 'account_number_last4' => '1234', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get('/bank')->assertStatus(301);
        $this->assertSame(1, DB::table('bank_accounts')->count());
    }

    public function test_the_route_list_still_builds(): void
    {
        $this->artisan('route:list')->assertSuccessful();
    }
}
