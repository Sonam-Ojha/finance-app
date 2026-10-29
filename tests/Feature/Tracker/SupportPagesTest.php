<?php

namespace Tests\Feature\Tracker;

use App\Models\SupportTicket;
use App\Models\User;
use App\Support\SupportTickets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ReceiptsTest;

/** My account › Help & support. */
class SupportPagesTest extends TrackerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(SupportTickets::DISK);
    }

    private function png(string $name = 'screen.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(ReceiptsTest::PNG));
    }

    public function test_settings_links_to_help_and_support(): void
    {
        $this->get('/settings')->assertOk()->assertSee('Help & support', false)->assertSee(route('support.index'));
        $this->get('/support')->assertOk()->assertSee('New request')->assertSee('No requests yet');
    }

    public function test_sending_a_request_with_a_screenshot(): void
    {
        $this->post('/support', ['subject' => 'Charts look odd', 'message' => "The pie is empty.\nOn Chrome.", 'screenshot' => $this->png()])
            ->assertRedirect()->assertSessionHas('success');

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame($this->user->id, $ticket->user_id);
        $this->assertSame('open', $ticket->status);
        $message = $ticket->messages()->first();
        Storage::disk(SupportTickets::DISK)->assertExists($message->screenshot_path);

        $this->get("/support/{$ticket->id}")->assertOk()
            ->assertSee('Charts look odd')
            ->assertSee('The pie is empty.')
            ->assertSee(route('support.screenshot', [$ticket, $message]));
        $this->get('/support')->assertOk()->assertSee('Charts look odd')->assertSee('Waiting for a reply');

        $photo = $this->get("/support/{$ticket->id}/messages/{$message->id}/screenshot")->assertOk();
        $this->assertSame('image/png', $photo->headers->get('Content-Type'));
    }

    public function test_bad_requests_are_refused(): void
    {
        $this->post('/support', ['subject' => '', 'message' => ''])->assertSessionHasErrors(['subject', 'message']);
        $this->post('/support', ['subject' => 'Hi', 'message' => 'x', 'screenshot' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors(['screenshot' => SupportTickets::SCREENSHOT_MESSAGE]);
        $this->post('/support', ['subject' => 'Hi', 'message' => 'x', 'screenshot' => UploadedFile::fake()->create('huge.png', 6000, 'image/png')])
            ->assertSessionHasErrors('screenshot');
        $this->assertSame(0, SupportTicket::count());
    }

    public function test_replies_show_as_new_until_read_and_the_user_writes_back(): void
    {
        $ticket = SupportTickets::open($this->user, 'Dark mode?', 'Where?');
        $admin = User::factory()->create();
        SupportTickets::reply($ticket, $admin, 'Settings › Appearance.');

        $this->get('/settings')->assertSee('1 new reply');
        $this->get('/support')->assertSee('New reply');
        $this->get("/support/{$ticket->id}")->assertOk()->assertSee('Settings › Appearance.')->assertSee('Support');
        $this->get('/support')->assertDontSee('New reply');

        $this->post("/support/{$ticket->id}/messages", ['message' => 'Got it, thanks'])->assertRedirect("/support/{$ticket->id}");
        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame(3, $ticket->messages()->count());
    }

    public function test_other_peoples_tickets_are_not_found(): void
    {
        $other = $this->makeUser(['email' => 'someone@example.com']);
        $ticket = SupportTickets::open($other, 'Private', 'Mine', [base64_decode(ReceiptsTest::PNG), 'png']);
        $message = $ticket->messages()->first();

        $this->get('/support')->assertDontSee('Private');
        $this->get("/support/{$ticket->id}")->assertNotFound();
        $this->post("/support/{$ticket->id}/messages", ['message' => 'hi'])->assertNotFound();
        $this->get("/support/{$ticket->id}/messages/{$message->id}/screenshot")->assertNotFound();
    }
}
