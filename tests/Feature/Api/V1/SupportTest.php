<?php

namespace Tests\Feature\Api\V1;

use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Accounts;
use App\Support\SupportTickets;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/** Help & support in the app: /api/v1/support. */
class SupportTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(SupportTickets::DISK);
        $this->user = $this->signIn();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        return $admin;
    }

    public function test_a_request_with_a_screenshot_is_created_and_listed(): void
    {
        $created = $this->postJson('/api/v1/support', [
            'subject' => 'Export is empty',
            'message' => 'The CSV has only headers.',
            'screenshot' => 'data:image/png;base64,' . ReceiptsTest::PNG,
        ])->assertCreated();

        $id = $created->json('data.id');
        $created->assertJson(['data' => [
            'id' => $id, 'subject' => 'Export is empty', 'status' => 'open',
            'last_reply_from' => 'user', 'unread' => false,
            'messages' => [['from' => 'user', 'body' => 'The CSV has only headers.', 'has_screenshot' => true]],
        ]]);
        $this->assertNotNull($created->json('data.created_at'));
        $this->assertNotNull($created->json('data.updated_at'));

        $path = SupportTicket::find($id)->messages()->first()->screenshot_path;
        $this->assertMatchesRegularExpression("#^{$this->user->id}/{$id}-\\d+-[a-z0-9]{10}\\.png$#", $path);
        Storage::disk(SupportTickets::DISK)->assertExists($path);

        $this->getJson('/api/v1/support')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonMissingPath('data.0.messages');
        $this->assertSame(['id', 'subject', 'status', 'created_at', 'updated_at', 'last_reply_from', 'unread'], array_keys($this->getJson('/api/v1/support')->json('data.0')));

        $messageId = $created->json('data.messages.0.id');
        $photo = $this->get("/api/v1/support/{$id}/messages/{$messageId}/screenshot")->assertOk();
        $this->assertSame('image/png', $photo->headers->get('Content-Type'));
        $this->assertSame(base64_decode(ReceiptsTest::PNG), $photo->streamedContent());
    }

    public function test_validation(): void
    {
        $this->postJson('/api/v1/support', [])->assertUnprocessable()->assertJsonValidationErrors(['subject', 'message']);
        $this->postJson('/api/v1/support', ['subject' => str_repeat('a', 121), 'message' => 'x'])->assertJsonValidationErrors('subject');
        $this->postJson('/api/v1/support', ['subject' => 'Hi', 'message' => 'x', 'screenshot' => 'data:image/png;base64,bm90IGFuIGltYWdl'])
            ->assertUnprocessable()->assertJsonValidationErrors(['screenshot' => SupportTickets::SCREENSHOT_MESSAGE]);
        $this->assertSame(0, SupportTicket::count());
    }

    public function test_a_reply_shows_as_unread_until_opened_and_the_user_can_write_back(): void
    {
        $ticket = SupportTickets::open($this->user, 'Dark mode?', 'Where is it?');
        SupportTickets::reply($ticket, $this->admin(), 'Settings › Appearance.');

        $this->getJson('/api/v1/support')->assertJsonPath('data.0.unread', true)->assertJsonPath('data.0.status', 'answered')
            ->assertJsonPath('data.0.last_reply_from', 'support');

        $detail = $this->getJson("/api/v1/support/{$ticket->id}")->assertOk();
        $this->assertSame(['user', 'support'], array_column($detail->json('data.messages'), 'from'));
        $this->assertSame('Settings › Appearance.', $detail->json('data.messages.1.body'));
        $this->assertFalse($detail->json('data.messages.1.has_screenshot'));
        $this->getJson('/api/v1/support')->assertJsonPath('data.0.unread', false);

        // Writing back opens it again, even once closed.
        SupportTickets::close($ticket);
        $this->postJson("/api/v1/support/{$ticket->id}/messages", ['message' => 'Found it, thanks!'])->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.last_reply_from', 'user')
            ->assertJsonCount(3, 'data.messages');
        $this->postJson("/api/v1/support/{$ticket->id}/messages", ['message' => ''])->assertJsonValidationErrors('message');
    }

    public function test_someone_elses_ticket_is_a_404(): void
    {
        $other = $this->makeUser();
        $ticket = SupportTickets::open($other, 'Mine', 'Not yours', [base64_decode(ReceiptsTest::PNG), 'png']);
        $message = $ticket->messages()->first();

        $this->getJson('/api/v1/support')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/support/{$ticket->id}")->assertNotFound();
        $this->postJson("/api/v1/support/{$ticket->id}/messages", ['message' => 'hi'])->assertNotFound();
        $this->get("/api/v1/support/{$ticket->id}/messages/{$message->id}/screenshot")->assertNotFound();

        // A message from another ticket, through one of mine.
        $mine = SupportTickets::open($this->user, 'Mine', 'Mine');
        $this->get("/api/v1/support/{$mine->id}/messages/{$message->id}/screenshot")->assertNotFound();
        // No screenshot on that message.
        $this->get("/api/v1/support/{$mine->id}/messages/{$mine->messages()->first()->id}/screenshot")->assertNotFound();
    }

    public function test_signed_out_is_a_401_and_new_requests_are_rate_limited(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/support', ['subject' => "Request {$i}", 'message' => 'x'])->assertCreated();
        }
        $this->postJson('/api/v1/support', ['subject' => 'Sixth', 'message' => 'x'])->assertStatus(429);
        // Writing back is not held up by it.
        $this->postJson('/api/v1/support/' . SupportTicket::first()->id . '/messages', ['message' => 'more'])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/support')->assertUnauthorized();
    }

    public function test_deleting_the_account_removes_tickets_and_screenshots(): void
    {
        $ticket = SupportTickets::open($this->user, 'Bye', 'x', [base64_decode(ReceiptsTest::PNG), 'png']);
        $path = $ticket->messages()->first()->screenshot_path;
        Storage::disk(SupportTickets::DISK)->assertExists($path);

        Accounts::delete($this->user);

        $this->assertSame(0, SupportTicket::count());
        $this->assertDatabaseCount('support_messages', 0);
        Storage::disk(SupportTickets::DISK)->assertMissing($path);
    }

    public function test_the_app_status_and_support_routes_answer_an_outdated_app_as_the_rest(): void
    {
        \App\Support\AppSettings::set(\App\Support\AppControl::MIN_VERSION, '1.2.0');
        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/support', ['X-App-Version' => '1.1.0'])->assertStatus(426)->assertJsonPath('code', 'update_required');
        $this->getJson('/api/v1/support', ['X-App-Version' => '1.2.0'])->assertOk();
    }
}
