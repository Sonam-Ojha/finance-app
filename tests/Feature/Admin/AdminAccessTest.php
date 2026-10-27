<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(array $attributes = []): User
    {
        return User::factory()->create($attributes)->forceFill(['role' => User::ROLE_ADMIN]);
    }

    public function test_only_admins_reach_the_admin_pages(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/notifications')->assertForbidden();
        $this->actingAs($user)->post('/admin/notifications', ['title' => 't', 'body' => 'b', 'audience' => 'all'])->assertForbidden();

        $admin = tap($this->admin())->save();
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee($user->email)->assertSee('Send Notification');
        $this->actingAs($admin)->get('/admin/notifications')->assertOk()->assertSee('New notification');
    }

    public function test_the_admin_menu_is_hidden_from_ordinary_users(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk()->assertDontSee('Send Notification');
    }

    public function test_an_admin_can_make_and_unmake_admins(): void
    {
        $admin = tap($this->admin())->save();
        $user  = User::factory()->create();

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/role", ['role' => 'admin'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($user->fresh()->isAdmin());

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/role", ['role' => 'user'])
            ->assertSessionHas('success');
        $this->assertFalse($user->fresh()->isAdmin());

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/role", ['role' => 'owner'])
            ->assertSessionHasErrors('role');
    }

    public function test_an_admin_cannot_remove_their_own_access(): void
    {
        $admin = tap($this->admin())->save();

        $this->actingAs($admin)->patch("/admin/users/{$admin->id}/role", ['role' => 'user'])
            ->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_nobody_can_make_themselves_an_admin_through_the_profile(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me/profile', ['name' => 'Me', 'email' => 'me@example.com', 'role' => 'admin'])->assertOk();
        $this->assertFalse($user->fresh()->isAdmin());

        $this->actingAs($user)->patch('/profile', ['name' => 'Me', 'email' => 'me@example.com', 'role' => 'admin']);
        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_the_first_admin_is_made_from_the_command_line(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com']);

        $this->artisan('user:admin', ['email' => 'BOSS@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());

        $this->artisan('user:admin', ['email' => 'boss@example.com', '--remove' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->isAdmin());

        $this->artisan('user:admin', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
