<?php

namespace Tests\Feature\Admin;

use App\Models\AdminNotification;
use App\Models\ScheduledNotification;
use App\Models\User;
use App\Notifications\AdminAnnouncement;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ScheduledNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->admin = tap(User::factory()->create(['name' => 'Asha Admin'])->forceFill(['role' => User::ROLE_ADMIN]))->save();
        // Thursday 29 October 2026, 3 p.m. in India.
        $this->travelTo($this->india('2026-10-29 15:00'));
    }

    private function india(string $at): Carbon
    {
        return Carbon::parse($at, 'Asia/Kolkata');
    }

    private function schedule(array $fields)
    {
        return $this->actingAs($this->admin)->post('/admin/notifications', $fields + [
            'title' => 'Log today’s spending', 'body' => 'Two minutes now saves a headache later.',
            'audience' => 'all', 'channels' => ['email'], 'when' => 'later',
        ]);
    }

    public function test_every_day_at_seven_goes_out_at_seven_once_each_day(): void
    {
        $this->schedule(['repeat' => 'daily', 'time' => '19:00'])
            ->assertRedirect('/admin/notifications?tab=scheduled')
            ->assertSessionHas('success', 'Scheduled: Every day at 7:00 PM. The first one goes on Thu 29 Oct, 7:00 PM.');
        $schedule = ScheduledNotification::firstOrFail();
        $this->assertTrue($schedule->next_run_at->eq($this->india('2026-10-29 19:00')));
        $this->assertSame(0, AdminNotification::count()); // nothing goes now

        $this->travelTo($this->india('2026-10-29 18:59'));
        $this->artisan('notifications:send-scheduled')->assertSuccessful();
        $this->assertSame(0, AdminNotification::count());

        $this->travelTo($this->india('2026-10-29 19:00'));
        $this->artisan('notifications:send-scheduled')->assertSuccessful();
        $this->artisan('notifications:send-scheduled')->assertSuccessful(); // the same minute again: no double

        $sent = AdminNotification::sole();
        $this->assertSame([$schedule->id, 'Log today’s spending', $this->admin->id, 1, 1], [
            $sent->scheduled_notification_id, $sent->title, $sent->sent_by, $sent->email_recipients, $sent->emails_sent,
        ]);
        Notification::assertSentToTimes($this->admin, AdminAnnouncement::class, 1);

        $schedule->refresh();
        $this->assertTrue($schedule->next_run_at->eq($this->india('2026-10-30 19:00')));
        $this->assertSame(1, $schedule->runs);
        $this->assertTrue($schedule->active);

        $this->actingAs($this->admin)->get('/admin/notifications?tab=scheduled')->assertOk()
            ->assertSee('Every day at 7:00 PM')
            ->assertSee('Next: Fri 30 Oct, 7:00 PM')
            ->assertSee('Sent 1 time');
        $this->actingAs($this->admin)->get('/admin/activity')->assertSee('notification.scheduled');
    }

    public function test_once_and_every_week(): void
    {
        $this->schedule(['repeat' => 'once', 'date' => '2026-10-30', 'time' => '09:30'])
            ->assertSessionHas('success', 'Scheduled: Once, Fri 30 Oct at 9:30 AM. It goes on Fri 30 Oct, 9:30 AM.');
        $this->schedule(['repeat' => 'weekly', 'weekday' => 1, 'time' => '09:00']);
        [$once, $weekly] = ScheduledNotification::orderBy('id')->get()->all();
        // Next Monday, 2 November.
        $this->assertTrue($weekly->next_run_at->eq($this->india('2026-11-02 09:00')));
        $this->assertSame('Every Monday at 9:00 AM', $weekly->describe());

        $this->travelTo($this->india('2026-10-30 09:30'));
        $this->artisan('notifications:send-scheduled');
        $once->refresh();
        $this->assertFalse($once->active);
        $this->assertNull($once->next_run_at);
        $this->assertSame(1, AdminNotification::count());

        $this->travelTo($this->india('2026-11-02 09:00'));
        $this->artisan('notifications:send-scheduled');
        $this->assertTrue($weekly->fresh()->next_run_at->eq($this->india('2026-11-09 09:00')));
        $this->assertSame(2, AdminNotification::count());
    }

    public function test_pause_resume_and_delete(): void
    {
        $this->schedule(['repeat' => 'daily', 'time' => '19:00']);
        $schedule = ScheduledNotification::firstOrFail();

        $this->actingAs($this->admin)->patch("/admin/notifications/scheduled/{$schedule->id}")->assertSessionHas('success');
        $this->assertFalse($schedule->fresh()->active);
        $this->travelTo($this->india('2026-10-29 19:00'));
        $this->artisan('notifications:send-scheduled');
        $this->assertSame(0, AdminNotification::count()); // paused: nothing

        // Resumed after 7: the next one is tomorrow's.
        $this->travelTo($this->india('2026-10-29 20:00'));
        $this->actingAs($this->admin)->patch("/admin/notifications/scheduled/{$schedule->id}");
        $this->assertTrue($schedule->fresh()->next_run_at->eq($this->india('2026-10-30 19:00')));

        $this->actingAs($this->admin)->delete("/admin/notifications/scheduled/{$schedule->id}")->assertSessionHas('success');
        $this->assertSame(0, ScheduledNotification::count());
    }

    public function test_a_run_hours_late_is_skipped_not_sent_at_night(): void
    {
        $this->schedule(['repeat' => 'daily', 'time' => '19:00']);

        // The server was down from 7 until 9:30 p.m.
        $this->travelTo($this->india('2026-10-29 21:30'));
        $this->artisan('notifications:send-scheduled');

        $this->assertSame(0, AdminNotification::count());
        $this->assertTrue(ScheduledNotification::firstOrFail()->next_run_at->eq($this->india('2026-10-30 19:00')));
    }

    public function test_bad_schedules_are_refused(): void
    {
        $this->schedule(['repeat' => 'daily'])->assertSessionHasErrors(['time' => 'Pick a time.']);
        $this->schedule(['repeat' => 'weekly', 'time' => '09:00'])->assertSessionHasErrors(['weekday' => 'Pick a day.']);
        $this->schedule(['repeat' => 'once', 'date' => '2026-10-29', 'time' => '14:00'])
            ->assertSessionHasErrors(['date' => 'Pick a time that has not passed yet.']);
        $this->assertSame(0, ScheduledNotification::count());

        // Only admins.
        $this->actingAs(User::factory()->create())->post('/admin/notifications', ['when' => 'later'])->assertForbidden();
    }
}
