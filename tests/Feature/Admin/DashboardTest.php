<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['name' => 'Asha Admin', 'email' => 'asha@example.com']);
        $this->admin->forceFill(['role' => User::ROLE_ADMIN])->save();
    }

    public function test_only_admins_reach_the_dashboard_and_the_log(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/admin/activity')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/admin/activity')->assertForbidden();

        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Aggregate view only.');
        $this->actingAs($this->admin)->get('/admin/activity')->assertOk()->assertSee('Activity log');
    }

    public function test_no_admin_page_shows_what_anyone_recorded(): void
    {
        $someone = User::factory()->create(['email' => 'priya.sharma@gmail.com']);
        foreach ([$someone, $this->admin] as $owner) {
            DefaultCategories::seed($owner);
            $category = $owner->categories()->where('name', 'Groceries')->first();
            AppExpense::create(['user_id' => $owner->id, 'amount_minor' => 987654, 'category_id' => $category->id, 'note' => 'Secret biryani run', 'spent_at' => now()->toDateString()]);
            AppIncome::create(['user_id' => $owner->id, 'amount_minor' => 4567890, 'source' => 'Secret bonus', 'note' => 'Hidden payout', 'received_at' => now()->toDateString()]);
        }

        foreach (['/admin', '/admin?range=7d', '/admin?range=12m', '/admin/users', '/admin/activity', '/admin/notifications', '/admin/settings'] as $url) {
            $page = $this->actingAs($this->admin)->get($url)->assertOk();
            foreach (['987654', '9,876.54', '4567890', '45,678.90', 'Secret biryani run', 'Secret bonus', 'Hidden payout', 'Groceries'] as $private) {
                $page->assertDontSee($private);
            }
        }

        // Counts are fine: two of each, four entries.
        $this->actingAs($this->admin)->get('/admin')->assertSee('Count of expenses and incomes, no amounts')
            ->assertSeeInOrder(['Entries logged', '4']);
    }

    public function test_the_numbers(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(14)->setTime(12, 0));
        User::factory()->count(3)->create(['created_at' => now()->subDays(3), 'email_verified_at' => null]);
        User::factory()->count(2)->create(['created_at' => now()->subDays(40)]);
        $android = User::factory()->create(['created_at' => now()->subDays(2), 'email' => 'rahul.kumar@gmail.com']);
        DeviceToken::create(['user_id' => $android->id, 'token' => 'tok-a', 'platform' => 'android']);

        // Signed in on the website, and through the app, today.
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $android->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
        $android->createToken('app')->accessToken->forceFill(['last_used_at' => now()])->save();

        $page = $this->actingAs($this->admin)->get('/admin?range=30d')->assertOk();
        // 3 + 1 this month, and the admin; 2 in the 30 days before.
        $page->assertSeeInOrder(['Total users', '7']);
        $page->assertSeeInOrder(['New sign-ups', '5', '↑ 150.0%', 'vs previous 30 days']);
        $page->assertSeeInOrder(['Active users', '1', '14.3% of all users']);

        // Recent sign-ups: masked email and platform.
        $page->assertSee('r•••••@gmail.com')->assertDontSee('rahul.kumar@gmail.com')->assertSee('Android');
        // 4 of 7 have not verified (the factory verifies by default).
        $page->assertSee("3 users haven't verified their email");
        $page->assertSee('Monthly sign-ups from');

        $this->actingAs($this->admin)->get('/admin/users?status=unverified')->assertOk()->assertSee('3 accounts not verified');
    }

    public function test_admin_actions_are_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->patch("/admin/users/{$user->id}/role", ['role' => 'admin'])->assertSessionHas('success');
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_id' => $this->admin->id, 'action' => 'user.role_changed',
            'subject_type' => $user->getMorphClass(), 'subject_id' => $user->id,
            'description' => "made user #{$user->id} an admin",
        ]);

        $this->actingAs($this->admin)->post('/admin/notifications', ['title' => 'Budgets are here', 'body' => 'Try them', 'audience' => 'all']);
        $this->assertSame(['user.role_changed', 'notification.sent'], AdminActivityLog::orderBy('id')->pluck('action')->all());

        $this->actingAs($this->admin)->get('/admin')->assertSee('made user')->assertSee('sent “Budgets are here” to all users');
        $this->actingAs($this->admin)->get('/admin/activity')->assertSee('notification.sent');
    }

    public function test_the_view_switch_is_for_admins_only(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('Switch view')->assertDontSee(route('admin.dashboard'));

        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee('Switch view')->assertSee(route('admin.dashboard'));
        $this->actingAs($this->admin)->get('/admin')->assertSee('Switch view')->assertSee('My own expenses');
    }

    public function test_admins_pick_the_sidebar_colour_in_their_settings(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/settings')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/settings')->assertOk()
            ->assertSee('Sidebar colour')
            ->assertSee('background-color: #002e62', false); // navy until one is picked

        $this->actingAs($this->admin)->from('/admin/settings')->put('/settings/sidebar', ['sidebar_color' => 'wine'])
            ->assertRedirect('/admin/settings')
            ->assertSessionHas('success', 'Sidebar colour set to Wine.');

        // One choice, and one frame, for both views.
        $this->actingAs($this->admin)->get('/admin')->assertSee('background-color: #881337', false)->assertSee('Admin panel');
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('background-color: #881337', false)->assertSee('My account');
    }

    public function test_mask_email(): void
    {
        $this->assertSame('r•••••@gmail.com', mask_email('rishidubey5676@gmail.com'));
        $this->assertSame('a•••••@b.co', mask_email('a@b.co'));
        $this->assertSame('', mask_email(null));
    }
}
