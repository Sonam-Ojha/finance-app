<?php

namespace Tests\Feature\Admin;

use App\Models\AdminNotification;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\AdminAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = tap(User::factory()->create()->forceFill(['role' => User::ROLE_ADMIN]))->save();
        $this->credentialsPath = storage_path('framework/testing/firebase-test-credentials.json');
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);
        parent::tearDown();
    }

    /** A service-account key file like Firebase's, with a fresh RSA key. */
    private function configureFirebase(): void
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        // Windows builds of PHP ship their openssl.cnf beside the binary and do not find it on their own.
        $config = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_file($config)) $options['config'] = $config;
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);

        @mkdir(dirname($this->credentialsPath), 0777, true);
        file_put_contents($this->credentialsPath, json_encode([
            'type'         => 'service_account',
            'project_id'   => 'expense-tracker-test',
            'client_email' => 'firebase-adminsdk@expense-tracker-test.iam.gserviceaccount.com',
            'private_key'  => $pem,
            'token_uri'    => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.firebase.credentials' => $this->credentialsPath]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'ya29.test-token', 'expires_in' => 3600]);
            }
            if ($request['message']['token'] === 'dead-token') {
                return Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404);
            }
            return Http::response(['name' => 'projects/expense-tracker-test/messages/1']);
        });
    }

    private function device(User $user, string $token): void
    {
        DeviceToken::create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android']);
    }

    public function test_it_pushes_to_every_signed_in_phone_and_drops_dead_tokens(): void
    {
        $this->configureFirebase();
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $this->device($alice, 'alice-phone');
        $this->device($bob, 'bob-phone');
        $this->device($bob, 'dead-token');

        $this->actingAs($this->admin)->post('/admin/notifications', [
            'title' => 'New feature', 'body' => 'Monthly budgets are here.', 'audience' => 'all', 'channels' => ['push'],
        ])->assertRedirect('/admin/notifications')
            ->assertSessionHas('success', 'Sent to all users. Sending to 3 phones — the counts appear in Sent shortly.');

        $sent = AdminNotification::firstOrFail();
        $this->assertSame([3, 2, 1, 'sent'], [$sent->devices, $sent->delivered, $sent->failed, $sent->status]);
        $this->assertSame($this->admin->id, $sent->sent_by);
        // The phone that removed the app is forgotten.
        $this->assertFalse(DeviceToken::where('token', 'dead-token')->exists());
        $this->assertSame(2, DeviceToken::count());

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'projects/expense-tracker-test/messages:send')
            && $r->hasHeader('Authorization', 'Bearer ya29.test-token')
            && $r['message']['notification'] === ['title' => 'New feature', 'body' => 'Monthly budgets are here.']
            && $r['message']['android']['notification']['channel_id'] === 'default'
            && $r['message']['data']['id'] === (string) $sent->id);
    }

    public function test_it_pushes_to_one_user_by_email(): void
    {
        $this->configureFirebase();
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $bob   = User::factory()->create();
        $this->device($alice, 'alice-phone');
        $this->device($bob, 'bob-phone');

        $this->actingAs($this->admin)->post('/admin/notifications', [
            'title' => 'Hi Alice', 'body' => 'Just for you.', 'audience' => 'user', 'channels' => ['push'], 'email' => 'ALICE@example.com',
        ])->assertSessionHas('success');

        $sent = AdminNotification::firstOrFail();
        $this->assertSame([$alice->id, 1, 1], [$sent->target_user_id, $sent->devices, $sent->delivered]);
        Http::assertSentCount(2); // the access token, then Alice's one phone
        Http::assertNotSent(fn (Request $r) => ($r['message']['token'] ?? null) === 'bob-phone');
    }

    public function test_bad_input_is_refused(): void
    {
        $this->actingAs($this->admin)->post('/admin/notifications', ['title' => '', 'body' => 'x', 'audience' => 'all', 'channels' => ['push']])
            ->assertSessionHasErrors('title');
        $this->actingAs($this->admin)->post('/admin/notifications', ['title' => 't', 'body' => 'x', 'audience' => 'user', 'channels' => ['push']])
            ->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post('/admin/notifications', ['title' => 't', 'body' => 'x', 'audience' => 'user', 'channels' => ['push'], 'email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');
        $this->assertSame(0, AdminNotification::count());
    }

    public function test_without_firebase_it_still_keeps_the_message_for_the_inbox(): void
    {
        config(['services.firebase.credentials' => storage_path('framework/testing/missing.json')]);
        Http::fake();
        $this->device(User::factory()->create(), 'some-phone');

        $this->actingAs($this->admin)->get('/admin/notifications')->assertSee('Push notifications are not set up yet');
        $this->actingAs($this->admin)->post('/admin/notifications', ['title' => 't', 'body' => 'b', 'audience' => 'all', 'channels' => ['push']])
            ->assertSessionHas('error');

        $this->assertSame(AdminNotification::STATUS_NOT_CONFIGURED, AdminNotification::firstOrFail()->status);
        Http::assertNothingSent();
        $this->actingAs($this->admin)->get('/admin/notifications')->assertSee('Push: inbox only');
    }

    public function test_it_emails_everyone_who_keeps_emails_on(): void
    {
        Notification::fake();
        Http::fake();
        $alice = User::factory()->create();
        $bob   = User::factory()->create(['email_notifications' => false]);
        $this->device($alice, 'alice-phone');

        $this->actingAs($this->admin)->post('/admin/notifications', [
            'title' => 'Budgets are here', 'body' => "Set one per category.
Try it today.", 'audience' => 'all', 'channels' => ['email'],
        ])->assertSessionHas('success', 'Sent to all users. Sending to 2 emails — the counts appear in Sent shortly.');

        Notification::assertSentTo($alice, AdminAnnouncement::class, function (AdminAnnouncement $mail) use ($alice) {
            $message = $mail->toMail($alice);
            return $message->subject === 'Budgets are here' && $message->viewData['body'] === "Set one per category.
Try it today.";
        });
        Notification::assertSentTo($this->admin, AdminAnnouncement::class);
        Notification::assertNotSentTo($bob, AdminAnnouncement::class);
        Http::assertNothingSent(); // email only: no push

        $sent = AdminNotification::firstOrFail();
        $this->assertSame([false, true, 0, 2, 2, 0], [$sent->via_push, $sent->via_email, $sent->devices, $sent->email_recipients, $sent->emails_sent, $sent->emails_failed]);
        $this->actingAs($this->admin)->get('/admin/notifications')->assertSee('Email 2/2 sent')->assertDontSee('Push 0/0');
        // Still in the app's notification list.
        $this->assertSame(1, AdminNotification::forUser($alice)->count());
    }

    public function test_push_and_email_together_to_one_user(): void
    {
        Notification::fake();
        $this->configureFirebase();
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $this->device($alice, 'alice-phone');

        $this->actingAs($this->admin)->post('/admin/notifications', [
            'title' => 'Hi Alice', 'body' => 'Just for you.', 'audience' => 'user', 'email' => 'alice@example.com', 'channels' => ['push', 'email'],
        ])->assertSessionHas('success', 'Sent to alice@example.com. Sending to 1 phone and 1 email — the counts appear in Sent shortly.');

        Notification::assertSentTo($alice, AdminAnnouncement::class);
        Notification::assertNotSentTo($this->admin, AdminAnnouncement::class);
        $sent = AdminNotification::firstOrFail();
        $this->assertSame([true, true, 1, 1], [$sent->via_push, $sent->via_email, $sent->delivered, $sent->emails_sent]);
        $this->actingAs($this->admin)->get('/admin/activity')->assertSee('by push and email');
    }

    public function test_a_user_who_turned_emails_off_gets_none(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'quiet@example.com', 'email_notifications' => false]);

        $this->actingAs($this->admin)->post('/admin/notifications', [
            'title' => 't', 'body' => 'b', 'audience' => 'user', 'email' => 'quiet@example.com', 'channels' => ['email'],
        ])->assertSessionHas('success', 'Sent to quiet@example.com. quiet@example.com turned emails off, so no email went out.');

        Notification::assertNothingSent();
        $this->assertSame(0, AdminNotification::firstOrFail()->email_recipients);
    }

    public function test_it_needs_a_channel_and_shows_the_preview(): void
    {
        $this->actingAs($this->admin)->post('/admin/notifications', ['title' => 't', 'body' => 'b', 'audience' => 'all'])
            ->assertSessionHasErrors(['channels' => 'Pick push, email or both.']);
        $this->actingAs($this->admin)->post('/admin/notifications', ['title' => 't', 'body' => 'b', 'audience' => 'all', 'channels' => ['sms']])
            ->assertSessionHasErrors('channels.0');
        $this->assertSame(0, AdminNotification::count());

        $this->actingAs($this->admin)->get('/admin/notifications')->assertOk()
            ->assertSee('Preview')
            ->assertSee('Your message shows here as you type.')
            ->assertSee('1 user with emails on');
    }
}
