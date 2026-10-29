<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Support\AppControl;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Admin › App control: maintenance mode, app versions, email verification in the app; GET /api/v1/app-status. */
class AppControlTest extends TestCase
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

    public function test_app_status_starts_with_everything_off(): void
    {
        $this->getJson('/api/v1/app-status')->assertOk()->assertExactJson(['data' => [
            'maintenance'                 => false,
            'maintenance_message'         => null,
            'min_version'                 => null,
            'latest_version'              => null,
            'update_message'              => null,
            'email_verification_required' => false,
        ]]);
    }

    public function test_only_admins_reach_the_page_and_its_switches(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/app-control')->assertForbidden();
        $this->actingAs($user)->put('/admin/app-control/maintenance', ['enabled' => 1])->assertForbidden();
        $this->assertFalse(AppControl::maintenance());

        $this->actingAs($this->admin)->get('/admin/app-control')->assertOk()
            ->assertSee('Maintenance mode')
            ->assertSee('App versions')
            ->assertSee('Require a verified email in the app')
            ->assertSee('/api/v1/app-status');
    }

    public function test_maintenance_mode_shuts_out_everyone_but_admins(): void
    {
        $user = User::factory()->create(['onboarded' => true]);

        $this->actingAs($this->admin)->put('/admin/app-control/maintenance', ['message' => 'Upgrading the servers, back by 6 PM.'])->assertRedirect();
        $this->actingAs($this->admin)->from('/admin')->put('/admin/app-control/maintenance', ['enabled' => 1])
            ->assertRedirect('/admin')->assertSessionHas('success');
        $this->assertTrue(AppControl::maintenance());

        // The website: a friendly 503 for users and visitors, the sign-in page stays open.
        $this->actingAs($user)->get('/dashboard')->assertStatus(503)->assertSee('Upgrading the servers, back by 6 PM.')->assertSee('Sign out');
        $this->actingAs($user)->getJson('/transactions')->assertStatus(503)->assertJson(['code' => 'maintenance']);
        auth()->logout();
        $this->get('/dashboard')->assertStatus(503)->assertSee('Team sign-in');
        $this->get('/login')->assertOk();

        // Admins carry on, on both sides of the panel.
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('Maintenance mode is on');
        // …with a strip on every page saying it is on.
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee('Only admins can use the website')->assertSee('Turn it off');

        // The API: 503 for everything but app-status, admins included.
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/categories')->assertStatus(503)
            ->assertExactJson(['message' => 'Upgrading the servers, back by 6 PM.', 'code' => 'maintenance']);
        $this->postJson('/api/v1/login', ['email' => 'a@b.co', 'password' => 'x'])->assertStatus(503);
        $this->getJson('/api/v1/currencies')->assertStatus(503);
        $this->getJson('/api/v1/app-status')->assertOk()
            ->assertJsonPath('data.maintenance', true)
            ->assertJsonPath('data.maintenance_message', 'Upgrading the servers, back by 6 PM.');

        // Off again.
        $this->actingAs($this->admin)->put('/admin/app-control/maintenance', ['enabled' => 0])->assertSessionHas('success');
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/categories')->assertOk();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame(
            ['app.maintenance_message', 'app.maintenance_on', 'app.maintenance_off'],
            AdminActivityLog::orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_an_empty_maintenance_message_uses_the_default(): void
    {
        AppSettings::set(AppControl::MAINTENANCE, true);
        $this->getJson('/api/v1/me')->assertStatus(503)->assertJsonPath('message', AppControl::DEFAULT_MAINTENANCE_MESSAGE);
        $this->getJson('/api/v1/app-status')->assertJsonPath('data.maintenance_message', AppControl::DEFAULT_MAINTENANCE_MESSAGE);
    }

    public function test_an_app_below_the_minimum_version_must_update(): void
    {
        $this->actingAs($this->admin)->put('/admin/app-control/versions', [
            'min_version' => '1.2.0', 'latest_version' => '1.3', 'update_message' => 'Get the new budgets screen.',
        ])->assertRedirect('/admin/app-control')->assertSessionHas('success');

        $this->getJson('/api/v1/app-status')->assertOk()->assertJson(['data' => [
            'min_version' => '1.2.0', 'latest_version' => '1.3', 'update_message' => 'Get the new budgets screen.',
        ]]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $old = ['X-App-Version' => '1.1.9'];

        $this->getJson('/api/v1/categories', $old)->assertStatus(426)
            ->assertExactJson(['message' => 'Get the new budgets screen.', 'code' => 'update_required']);
        $this->postJson('/api/v1/login', [], $old)->assertStatus(426);
        // Who am I, and signing out, still work; app-status always does.
        $this->getJson('/api/v1/me', $old)->assertOk();
        $this->getJson('/api/v1/app-status', $old)->assertOk();
        $this->postJson('/api/v1/logout', [], $old)->assertSuccessful();

        Sanctum::actingAs($user);
        foreach (['1.2.0', '1.2', '1.10.0', 'v1.2.1', '1.2.0 (45)'] as $version) {
            $this->getJson('/api/v1/categories', ['X-App-Version' => $version])->assertOk();
        }
        // No header (older builds), or nonsense: never blocked.
        $this->getJson('/api/v1/categories')->assertOk();
        $this->getJson('/api/v1/categories', ['X-App-Version' => 'dev'])->assertOk();

        $this->assertSame('set the minimum app version to 1.2.0, set the latest app version to 1.3, changed the update message',
            AdminActivityLog::where('action', 'app.versions')->value('description'));

        // Cleared: nothing is blocked.
        $this->actingAs($this->admin)->put('/admin/app-control/versions', ['min_version' => '', 'latest_version' => '', 'update_message' => '']);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/categories', $old)->assertOk();
        $this->assertNull(AppControl::minVersion());
    }

    public function test_the_default_update_message_and_bad_versions(): void
    {
        AppSettings::set(AppControl::MIN_VERSION, '2.0.0');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/categories', ['X-App-Version' => '1.9.9'])->assertStatus(426)
            ->assertJsonPath('message', AppControl::DEFAULT_UPDATE_MESSAGE);

        $this->actingAs($this->admin)->put('/admin/app-control/versions', ['min_version' => 'one point two', 'latest_version' => '1.2.0.0.1'])
            ->assertSessionHasErrors(['min_version', 'latest_version']);
        $this->actingAs($this->admin)->put('/admin/app-control/versions', ['min_version' => '1.3.0', 'latest_version' => '1.2.9'])
            ->assertSessionHasErrors(['latest_version' => 'The latest version cannot be older than the minimum.']);
        $this->assertSame('2.0.0', AppControl::minVersion());
    }

    public function test_version_comparison(): void
    {
        $this->assertSame(0, AppControl::compare('1.2', '1.2.0'));
        $this->assertSame(-1, AppControl::compare('1.2.9', '1.10'));
        $this->assertSame(1, AppControl::compare('2', '1.99.99'));
        $this->assertSame('1.4.2', AppControl::parseVersion('v1.4.2-beta (88)'));
        $this->assertNull(AppControl::parseVersion(''));
    }

    public function test_the_email_verification_switch_replaces_the_env_flag(): void
    {
        $unverified = User::factory()->unverified()->create();
        Sanctum::actingAs($unverified);
        $this->getJson('/api/v1/categories')->assertOk();

        // Set here: required, whatever the .env says.
        $this->actingAs($this->admin)->put('/admin/app-control/email-verification', ['required' => '1'])->assertSessionHas('success');
        $this->assertTrue(AppControl::emailVerificationRequired());
        Sanctum::actingAs($unverified);
        $this->getJson('/api/v1/categories')->assertForbidden()->assertJsonPath('code', 'email_unverified');
        $this->getJson('/api/v1/app-status')->assertJsonPath('data.email_verification_required', true);

        // Set here: optional, even with the .env flag on.
        config(['services.email_check.required_for_api' => true]);
        $this->actingAs($this->admin)->put('/admin/app-control/email-verification', ['required' => '0']);
        Sanctum::actingAs($unverified);
        $this->getJson('/api/v1/categories')->assertOk();

        // Back to the .env flag.
        $this->actingAs($this->admin)->put('/admin/app-control/email-verification', ['required' => 'default']);
        $this->assertFalse(AppControl::emailVerificationSetHere());
        Sanctum::actingAs($unverified);
        $this->getJson('/api/v1/categories')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/app-control')->assertSee('following the server setting');

        $this->assertSame(3, AdminActivityLog::where('action', 'app.email_verification')->count());
        $this->actingAs($this->admin)->put('/admin/app-control/email-verification', ['required' => 'maybe'])->assertSessionHasErrors('required');
    }

    public function test_the_dashboard_shows_the_real_switch(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('Off · app is open for everyone')
            ->assertSee(route('admin.app-control.maintenance'))
            ->assertSee(route('admin.default-categories.index'));

        AppSettings::set(AppControl::MAINTENANCE, true);
        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('On · only admins get in')
            ->assertSee('Maintenance mode is on: only admins can use the app and the website');
    }

    public function test_the_new_admin_pages_show_nothing_anyone_recorded(): void
    {
        $someone = User::factory()->create(['email' => 'priya.sharma@gmail.com', 'currency' => 'USD']);
        \App\Support\DefaultCategories::seed($someone);
        $category = $someone->categories()->where('name', 'Transport')->first();
        $category->update(['name' => 'Secret stash', 'budget_minor' => 1234500]);
        \App\Models\AppExpense::create(['user_id' => $someone->id, 'amount_minor' => 987654, 'category_id' => $category->id, 'note' => 'Secret biryani run', 'spent_at' => now()->toDateString()]);
        \App\Models\AppIncome::create(['user_id' => $someone->id, 'amount_minor' => 4567890, 'source' => 'Secret bonus', 'note' => 'Hidden payout', 'received_at' => now()->toDateString()]);
        \App\Models\UserCurrency::create(['user_id' => $someone->id, 'code' => 'USD', 'rate' => 77.77]);
        \App\Support\SupportTickets::open($someone, 'Help please', 'Something broke');

        foreach (['/admin/default-categories', '/admin/currencies', '/admin/app-control', '/admin/support', '/admin/support/1'] as $url) {
            $page = $this->actingAs($this->admin)->get($url)->assertOk();
            foreach (['987654', '9,876.54', '4567890', '45,678.90', '12,345', 'Secret stash', 'Secret biryani run', 'Secret bonus', 'Hidden payout', '77.77'] as $private) {
                $page->assertDontSee($private);
            }
        }
        // Counts are fine: one person reads amounts in dollars.
        $this->actingAs($this->admin)->get('/admin/currencies')->assertSeeInOrder(['US Dollar', 'USD', '2', '1']);
    }

    public function test_settings_survive_a_missing_table(): void
    {
        \Illuminate\Support\Facades\Schema::drop('app_settings');
        AppSettings::forgetCache();
        $this->assertFalse(AppControl::maintenance());
        $this->getJson('/api/v1/app-status')->assertOk()->assertJsonPath('data.maintenance', false);
    }
}
