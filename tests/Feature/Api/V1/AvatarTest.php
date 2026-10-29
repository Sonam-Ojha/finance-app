<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Support\Accounts;
use Illuminate\Support\Facades\File;

class AvatarTest extends ApiTestCase
{
    /** A 1×1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private string $public;

    protected function setUp(): void
    {
        parent::setUp();
        // Photos land in a scratch folder, never in the real public/avatars.
        $this->public = storage_path('framework/testing/public');
        File::ensureDirectoryExists($this->public);
        $this->app->usePublicPath($this->public);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->public);
        parent::tearDown();
    }

    public function test_a_photo_is_stored_with_an_address_that_changes_with_it(): void
    {
        $user = $this->signIn();

        $url = $this->postJson('/api/v1/me/avatar', ['avatar' => 'data:image/png;base64,' . self::PNG])
            ->assertOk()->json('data.avatar_url');

        $this->assertSame("avatars/{$user->id}.png", $user->fresh()->avatar);
        $this->assertSame(base64_decode(self::PNG), file_get_contents("{$this->public}/avatars/{$user->id}.png"));
        $this->assertMatchesRegularExpression("#/avatars/{$user->id}\\.png\\?v=\\d+$#", $url);
        $this->getJson('/api/v1/me')->assertJsonPath('data.avatar_url', $url);

        // A changed photo gets a new address, so no one keeps seeing the cached old one.
        touch("{$this->public}/avatars/{$user->id}.png", time() + 60);
        $this->assertNotSame($url, Accounts::avatarUrl($user->fresh()));
    }

    public function test_something_that_is_not_a_photo_is_refused_and_the_old_one_kept(): void
    {
        $user = $this->signIn();
        $this->postJson('/api/v1/me/avatar', ['avatar' => 'data:image/png;base64,' . self::PNG])->assertOk();

        $this->postJson('/api/v1/me/avatar', ['avatar' => 'data:image/jpeg;base64,' . base64_encode('not a picture')])
            ->assertStatus(422)
            ->assertJsonPath('errors.avatar.0', 'Could not use that photo. Pick a JPEG or PNG image under 2 MB.');

        $this->assertSame("avatars/{$user->id}.png", $user->fresh()->avatar);
        $this->assertFileExists("{$this->public}/avatars/{$user->id}.png");
    }

    public function test_a_lost_photo_file_shows_initials_instead_of_a_broken_image(): void
    {
        $user = User::factory()->create(['name' => 'Asha Rao', 'avatar' => 'avatars/404.jpg']);

        $this->assertNull(Accounts::avatarUrl($user));
        $this->assertSame('AR', Accounts::initials($user));
    }

    public function test_admins_see_each_photo_in_the_user_list_and_can_open_it(): void
    {
        $admin = User::factory()->create(['name' => 'Asha Admin']);
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $priya = User::factory()->create(['name' => 'Priya Sharma']);
        $this->actingAs($priya);
        $this->post('/profile/photo', ['avatar' => 'data:image/png;base64,' . self::PNG])->assertSessionHas('success', 'Photo saved');
        User::factory()->create(['name' => 'Ravi Kumar']);

        $this->withoutVite()->actingAs($admin)->get('/admin/users')->assertOk()
            ->assertSee("Open Priya Sharma's photo", false)
            ->assertSee("avatars/{$priya->id}.png?v=", false)
            ->assertSee('open-photo', false)
            ->assertSee('RK'); // no photo: initials
    }
}
