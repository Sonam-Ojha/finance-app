<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\AppExpense;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportReplied;
use App\Support\DefaultCategories;
use App\Support\SupportTickets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesFirebase;
use Tests\TestCase;

/** Admin › Support: the list, a conversation, replies, closing and reopening. */
class SupportTest extends TestCase
{
    use FakesFirebase, RefreshDatabase;

    /** A 1×1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake(SupportTickets::DISK);
        $this->admin = tap(User::factory()->create(['name' => 'Asha Admin']))->forceFill(['role' => User::ROLE_ADMIN]);
        $this->admin->save();
        $this->user = User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya.sharma@gmail.com']);
    }

    private function ticket(string $subject = 'Budget alert did not arrive', ?User $user = null): SupportTicket
    {
        return SupportTickets::open($user ?? $this->user, $subject, "I set a food budget but got no push.\nPhone: Pixel 7", [base64_decode(self::PNG), 'png']);
    }

    public function test_only_admins_reach_support(): void
    {
        $ticket = $this->ticket();
        $this->get('/admin/support')->assertRedirect('/login');
        $this->actingAs($this->user)->get('/admin/support')->assertForbidden();
        $this->actingAs($this->user)->get("/admin/support/{$ticket->id}")->assertForbidden();
        $this->actingAs($this->user)->post("/admin/support/{$ticket->id}/reply", ['message' => 'hi'])->assertForbidden();
        $this->actingAs($this->user)->get("/admin/support/{$ticket->id}/messages/{$ticket->messages()->first()->id}/screenshot")->assertForbidden();
    }

    public function test_the_list_filters_by_status_and_badges_what_is_waiting(): void
    {
        $open = $this->ticket('Cannot export PDF');
        $answered = $this->ticket('Dark mode question');
        SupportTickets::reply($answered, $this->admin, 'Settings › Appearance.');
        $closed = $this->ticket('Old issue');
        SupportTickets::close($closed);

        $page = $this->actingAs($this->admin)->get('/admin/support')->assertOk()
            ->assertSee('Cannot export PDF')->assertSee('Dark mode question')->assertSee('Old issue')
            ->assertSee('priya.sharma@gmail.com')->assertSee('Priya Sharma')
            ->assertSee('1 request waiting for a reply');
        // The menu badge: one waiting.
        $page->assertSeeInOrder(['Support', '1', 'waiting']);

        $this->actingAs($this->admin)->get('/admin/support?status=open')->assertOk()
            ->assertSee('Cannot export PDF')->assertDontSee('Dark mode question')->assertDontSee('Old issue');
        $this->actingAs($this->admin)->get('/admin/support?status=closed')->assertOk()
            ->assertSee('Old issue')->assertDontSee('Cannot export PDF');
        $this->actingAs($this->admin)->get('/admin/support?q=dark')->assertOk()
            ->assertSee('Dark mode question')->assertDontSee('Cannot export PDF');

        // The dashboard says so too.
        $this->actingAs($this->admin)->get('/admin')->assertSee('1 support request waiting');
    }

    public function test_a_reply_is_stored_emailed_pushed_and_logged(): void
    {
        Notification::fake();
        $this->fakeFirebase();
        $this->phone($this->user);
        $ticket = $this->ticket();

        $this->actingAs($this->admin)->get("/admin/support/{$ticket->id}")->assertOk()
            ->assertSee('Budget alert did not arrive')
            ->assertSee('Phone: Pixel 7')
            ->assertSee('priya.sharma@gmail.com')
            ->assertSee(route('admin.support.screenshot', [$ticket, $ticket->messages()->first()]))
            ->assertSee('Waiting for your reply.');

        $this->actingAs($this->admin)->post("/admin/support/{$ticket->id}/reply", ['message' => "Thanks! Fixed in 1.2.0.\nUpdate the app."])
            ->assertRedirect("/admin/support/{$ticket->id}")->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame('answered', $ticket->status);
        $this->assertSame('support', $ticket->last_reply_from);
        $this->assertTrue($ticket->unread_by_user);
        $this->assertDatabaseHas('support_messages', ['ticket_id' => $ticket->id, 'sender' => 'support', 'author_id' => $this->admin->id, 'body' => "Thanks! Fixed in 1.2.0.\nUpdate the app."]);

        Notification::assertSentTo($this->user, SupportReplied::class, function (SupportReplied $n) use ($ticket) {
            $mail = $n->toMail($this->user);

            return $n->ticketId === $ticket->id
                && $mail->subject === 'Re: Budget alert did not arrive'
                && str_contains($mail->render(), 'Fixed in 1.2.0.');
        });

        $push = collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->first(fn (Request $r) => str_contains($r->url(), 'messages:send'));
        $this->assertSame('Support replied: Budget alert did not arrive', $push['message']['notification']['title']);
        $this->assertSame(['type' => 'support', 'ticket_id' => (string) $ticket->id], $push['message']['data']);

        $this->assertSame("replied to support request #{$ticket->id} from user #{$this->user->id}", AdminActivityLog::where('action', 'support.replied')->value('description'));
        $this->actingAs($this->admin)->post("/admin/support/{$ticket->id}/reply", ['message' => ''])->assertSessionHasErrors('message');
    }

    public function test_close_and_reopen(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->admin)->patch("/admin/support/{$ticket->id}/close")->assertRedirect("/admin/support/{$ticket->id}");
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);
        $this->actingAs($this->admin)->get("/admin/support/{$ticket->id}")->assertSee('Reopen');

        $this->actingAs($this->admin)->patch("/admin/support/{$ticket->id}/reopen")->assertRedirect();
        $this->assertSame('open', $ticket->fresh()->status);

        // Reopening one support answered last puts it back to answered.
        SupportTickets::reply($ticket, $this->admin, 'Done.');
        SupportTickets::close($ticket);
        $this->actingAs($this->admin)->patch("/admin/support/{$ticket->id}/reopen");
        $this->assertSame('answered', $ticket->fresh()->status);

        $this->assertSame(['support.closed', 'support.reopened', 'support.reopened'], AdminActivityLog::orderBy('id')->pluck('action')->all());
    }

    public function test_admins_see_the_screenshot_and_who_asked_but_nothing_they_recorded(): void
    {
        DefaultCategories::seed($this->user);
        $category = $this->user->categories()->where('name', 'Groceries')->first();
        AppExpense::create(['user_id' => $this->user->id, 'amount_minor' => 987654, 'category_id' => $category->id, 'note' => 'Secret biryani run', 'spent_at' => now()->toDateString()]);
        $ticket = $this->ticket();
        $message = $ticket->messages()->first();

        $photo = $this->actingAs($this->admin)->get("/admin/support/{$ticket->id}/messages/{$message->id}/screenshot")->assertOk();
        $this->assertSame('image/png', $photo->headers->get('Content-Type'));

        $other = $this->ticket('Another', User::factory()->create());
        $this->actingAs($this->admin)->get("/admin/support/{$other->id}/messages/{$message->id}/screenshot")->assertNotFound();

        foreach (['/admin/support', "/admin/support/{$ticket->id}"] as $url) {
            $page = $this->actingAs($this->admin)->get($url)->assertOk();
            foreach (['987654', '9,876.54', 'Secret biryani run', 'Groceries'] as $private) {
                $page->assertDontSee($private);
            }
        }
    }
}
