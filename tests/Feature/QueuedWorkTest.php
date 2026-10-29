<?php

namespace Tests\Feature;

use App\Jobs\SendAdminNotification;
use App\Models\AdminNotification;
use App\Models\User;
use App\Support\DefaultCategories;
use App\Support\Groups;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesFirebase;
use Tests\TestCase;

/**
 * Emails and pushes go out from the queue, which the scheduler runs every
 * minute — not inside the request. phpunit.xml uses the sync queue, so the
 * other tests see them sent at once; here the queue is the database, as on
 * the server.
 */
class QueuedWorkTest extends TestCase
{
    use FakesFirebase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['queue.default' => 'database']);
        $this->fakeFirebase();
        // Thursday 29 October 2026, 3 p.m. in India.
        $this->travelTo(Carbon::parse('2026-10-29 15:00', 'Asia/Kolkata'));
    }

    /** @return list<string> the subjects of the emails sent so far */
    private function mails(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage()->getSubject())->values()->all();
    }

    /** What the scheduler does every minute. */
    private function work(): void
    {
        $this->artisan('queue:work', ['--stop-when-empty' => true, '--memory' => 1024])->assertSuccessful();
    }

    public function test_the_scheduler_runs_the_queue_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'queue:work'));

        $this->assertNotNull($event, 'routes/console.php schedules queue:work');
        $this->assertStringContainsString('queue:work --stop-when-empty --max-time=50 --tries=3', $event->command);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(2, $event->expiresAt);
    }

    public function test_an_expense_is_saved_at_once_and_its_email_and_budget_push_wait_for_the_worker(): void
    {
        $user = User::factory()->create(['name' => 'Asha Rao', 'currency' => 'INR']);
        DefaultCategories::seed($user);
        $this->phone($user);
        $food = $user->categories()->where('name', 'Food & Drink')->firstOrFail();
        $food->update(['budget_minor' => 100000]); // ₹1,000
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/expenses', ['amount_minor' => 95000, 'category_id' => $food->id, 'spent_at' => '2026-10-29'])->assertCreated();

        // Nothing sent while the app waited: the email and the budget check are queued.
        $this->assertSame([], $this->pushes());
        $this->assertSame([], $this->mails());
        $this->assertSame(2, DB::table('jobs')->count());

        $this->work();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(['Expense added: ₹950.00'], $this->mails());
        $this->assertSame([['title' => '🍔 Food & Drink: 95% of budget used', 'body' => '₹950.00 of ₹1,000.00 spent. ₹50.00 left this month.']], $this->pushes());
    }

    public function test_group_news_waits_for_the_worker_too(): void
    {
        $asha  = User::factory()->create(['name' => 'Asha Rao', 'email' => 'asha@example.com']);
        $rishi = User::factory()->create(['name' => 'Rishi Kapoor', 'email' => 'rishi@example.com']);
        $this->phone($rishi);
        $group = Groups::create($asha, 'Goa trip', '🏖️');

        Groups::addMember($group, $asha, 'rishi@example.com');

        $this->assertSame([], $this->pushes());
        $this->assertSame([], $this->mails());
        $this->assertSame(2, DB::table('jobs')->count()); // the push, the "added you" email

        $this->work();

        $this->assertSame([['title' => 'Asha added you to 🏖️ Goa trip', 'body' => 'Split expenses and see who owes whom.']], $this->pushes());
        $this->assertSame(['Asha Rao added you to 🏖️ Goa trip'], $this->mails());
    }

    public function test_an_admin_notification_says_sending_until_the_worker_has_sent_it(): void
    {
        $admin = tap(User::factory()->create()->forceFill(['role' => User::ROLE_ADMIN]))->save();
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $this->phone($alice, 'alice-phone');

        $this->actingAs($admin)->post('/admin/notifications', [
            'title' => 'Hi Alice', 'body' => 'Just for you.', 'audience' => 'user', 'email' => 'alice@example.com', 'channels' => ['push', 'email'],
        ])->assertSessionHas('success', 'Sent to alice@example.com. Sending to 1 phone and 1 email — the counts appear in Sent shortly.');

        // Saved for the inbox straight away; the push and the email are queued.
        $sent = AdminNotification::sole();
        $this->assertTrue($sent->pushesPending());
        $this->assertTrue($sent->emailsPending());
        $this->assertSame(1, AdminNotification::forUser($alice)->count());
        $this->assertSame([], $this->pushes());
        $this->assertSame(2, DB::table('jobs')->count());
        $this->get('/admin/notifications')->assertSee('Push sending…')->assertSee('Email sending…');

        $this->work();

        $sent->refresh();
        $this->assertSame([1, 0, 1, 0], [$sent->delivered, $sent->failed, $sent->emails_sent, $sent->emails_failed]);
        $this->assertFalse($sent->pushesPending());
        $this->assertFalse($sent->emailsPending());
        $this->assertSame([['title' => 'Hi Alice', 'body' => 'Just for you.']], $this->pushes());
        $this->assertSame(['Hi Alice'], $this->mails());
        $this->get('/admin/notifications')->assertSee('Push 1/1 delivered')->assertSee('Email 1/1 sent')->assertDontSee('sending…');
    }

    public function test_a_batch_that_gives_up_counts_its_people_as_failed(): void
    {
        $sent = AdminNotification::create(['title' => 't', 'body' => 'b', 'audience' => AdminNotification::AUDIENCE_ALL, 'via_push' => true, 'via_email' => true,
            'status' => AdminNotification::STATUS_SENT, 'devices' => 2, 'email_recipients' => 3]);

        (new SendAdminNotification($sent->id, ['phone-1', 'phone-2'], []))->failed(new \RuntimeException('Firebase timed out'));
        (new SendAdminNotification($sent->id, [], [1, 2, 3]))->failed(null);

        $sent->refresh();
        $this->assertSame([0, 2, 0, 3], [$sent->delivered, $sent->failed, $sent->emails_sent, $sent->emails_failed]);
        $this->assertFalse($sent->pushesPending());
        $this->assertFalse($sent->emailsPending());
    }
}
