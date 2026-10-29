<?php

namespace Tests\Feature;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Budget;
use App\Models\User;
use App\Notifications\BirthdayWish;
use App\Notifications\MonthlySummaryMail;
use App\Support\AppControl;
use App\Support\AppSettings;
use App\Support\DefaultCategories;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesFirebase;
use Tests\TestCase;

/** What the server sends on its own: budget alert pushes, the daily reminder, birthday wishes, the monthly summary. */
class RemindersTest extends TestCase
{
    use FakesFirebase, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->fakeFirebase();
        // Thursday 29 October 2026, 3 p.m. in India.
        $this->travelTo($this->india('2026-10-29 15:00'));
        $this->user = User::factory()->create(['name' => 'Asha Rao', 'currency' => 'INR']);
        DefaultCategories::seed($this->user);
        $this->phone($this->user);
    }

    private function india(string $at): Carbon
    {
        return Carbon::parse($at, 'Asia/Kolkata');
    }

    private function category(string $name)
    {
        return $this->user->categories()->where('name', $name)->firstOrFail();
    }

    private function spend(int $rupees, string $category = 'Food & Drink', string $day = '2026-10-29')
    {
        Sanctum::actingAs($this->user);

        return $this->postJson('/api/v1/expenses', [
            'amount_minor' => $rupees * 100, 'category_id' => $this->category($category)->id, 'spent_at' => $day,
        ])->assertCreated();
    }

    public function test_a_budget_alert_is_pushed_once_at_80_and_once_past_100(): void
    {
        $this->category('Food & Drink')->update(['budget_minor' => 500000]); // ₹5,000

        $this->spend(3000);
        $this->assertSame([], $this->pushes());

        $this->spend(1250); // ₹4,250: 85%
        $this->assertSame([['title' => '🍔 Food & Drink: 85% of budget used', 'body' => '₹4,250.00 of ₹5,000.00 spent. ₹750.00 left this month.']], $this->pushes());

        $this->spend(100);  // still between 80 and 100: nothing new
        $this->spend(800);  // ₹5,150: over
        $pushes = $this->pushes();
        $this->assertCount(2, $pushes);
        $this->assertSame('🍔 Food & Drink is over budget', $pushes[1]['title']);

        $this->spend(500);  // further over: no repeat
        $this->assertCount(2, $this->pushes());
    }

    public function test_no_budget_push_for_an_old_month_or_when_an_admin_turns_them_off(): void
    {
        Budget::create(['user_id' => $this->user->id, 'category_id' => null, 'year' => 2026, 'month' => 9, 'amount_minor' => 100000]);
        $this->spend(5000, day: '2026-09-15');
        $this->assertSame([], $this->pushes());

        // A user who switched them off under the old Settings › Reminders gets them anyway.
        $this->category('Groceries')->update(['budget_minor' => 100000]);
        $this->user->forceFill(['budget_alert_push' => false])->save();
        $this->spend(900, 'Groceries');
        $this->assertCount(1, $this->pushes());

        // Only Admin › App control switches them off, for everyone.
        AppSettings::set(AppControl::BUDGET_ALERT_PUSH, false);
        $this->spend(200, 'Groceries');
        $this->assertCount(1, $this->pushes());
    }

    public function test_the_daily_reminder_goes_at_nine_to_those_who_logged_nothing(): void
    {
        $busy = User::factory()->create();
        $this->phone($busy, 'busy-phone');
        DefaultCategories::seed($busy);
        AppExpense::create(['user_id' => $busy->id, 'amount_minor' => 100, 'category_id' => $busy->categories()->first()->id, 'spent_at' => '2026-10-29']);
        // Turned off under the old Settings › Reminders: reminded all the same.
        $old = User::factory()->create(['daily_reminder_at' => null]);
        $this->phone($old, 'old-phone');

        $this->travelTo($this->india('2026-10-29 20:59'));
        $this->artisan('reminders:daily');
        $this->assertSame([], $this->pushes());

        $this->travelTo($this->india('2026-10-29 21:00'));
        $this->artisan('reminders:daily');
        $this->artisan('reminders:daily'); // the same minute again: no repeat
        $this->assertSame(['Logged today’s spending? ✍️', 'Logged today’s spending? ✍️'], array_column($this->pushes(), 'title'));

        // An admin moves it to 6 PM: by 9:30 PM a missed one is too late.
        AppSettings::set(AppControl::DAILY_REMINDER_AT, '18:00');
        $this->user->forceFill(['last_reminded_on' => null])->save();
        $this->travelTo($this->india('2026-10-29 21:30'));
        $this->artisan('reminders:daily');
        $this->assertCount(2, $this->pushes());

        // Switched off in Admin › App control: nobody, the next evening.
        AppSettings::set(AppControl::DAILY_REMINDER, false);
        $this->travelTo($this->india('2026-10-30 18:00'));
        $this->artisan('reminders:daily');
        $this->assertCount(2, $this->pushes());
    }

    public function test_a_birthday_is_wished_once_by_push_and_email(): void
    {
        $this->user->update(['birthday' => '1995-10-29']);
        User::factory()->create(['birthday' => '1995-10-30']);

        $this->travelTo($this->india('2026-10-29 08:30'));
        $this->artisan('birthdays:wish');
        $this->assertSame([], $this->pushes());

        $this->travelTo($this->india('2026-10-29 09:00'));
        $this->artisan('birthdays:wish');
        $this->artisan('birthdays:wish');
        $this->assertSame(['Happy birthday, Asha! 🎂'], array_column($this->pushes(), 'title'));
        Notification::assertSentToTimes($this->user, BirthdayWish::class, 1);

        // Switched off in Admin › App control: no wishes next year.
        AppSettings::set(AppControl::BIRTHDAY_WISHES, false);
        $this->travelTo($this->india('2027-10-29 10:00'));
        $this->artisan('birthdays:wish');
        Notification::assertSentToTimes($this->user, BirthdayWish::class, 1);
    }

    public function test_the_monthly_summary_sums_up_last_month_on_the_first(): void
    {
        $this->category('Transport')->update(['budget_minor' => 100000]);
        foreach ([[2000, 'Food & Drink'], [1500, 'Transport']] as [$rupees, $category]) {
            AppExpense::create(['user_id' => $this->user->id, 'amount_minor' => $rupees * 100, 'category_id' => $this->category($category)->id, 'spent_at' => '2026-10-10']);
        }
        AppIncome::create(['user_id' => $this->user->id, 'amount_minor' => 5000000, 'source' => 'Salary', 'received_at' => '2026-10-01']);
        $quiet = User::factory()->create(); // nothing recorded: nothing to sum up
        // Turned off under the old Settings › Reminders: gets it all the same.
        $this->user->forceFill(['monthly_summary' => false])->save();

        $this->travelTo($this->india('2026-11-01 08:00'));
        $this->artisan('summary:monthly');
        Notification::assertNothingSent();

        $this->travelTo($this->india('2026-11-01 09:00'));
        $this->artisan('summary:monthly');
        $this->artisan('summary:monthly');

        Notification::assertSentToTimes($this->user, MonthlySummaryMail::class, 1);
        Notification::assertSentTo($this->user, MonthlySummaryMail::class, function (MonthlySummaryMail $mail) {
            $s = $mail->summary;
            $html = (string) $mail->toMail($this->user)->render();

            return $s['label'] === 'October 2026' && $s['in'] === '₹50,000.00' && $s['spent'] === '₹3,500.00'
                && $s['saved'] === '₹46,500.00' && $s['categories'][0]['name'] === '🍔 Food & Drink'
                && $s['over'] === ['Transport went ₹500.00 over its ₹1,000.00 budget.']
                && str_contains($html, '₹46,500.00') && str_contains($html, 'Every budget held') === false;
        });
        Notification::assertNotSentTo($quiet, MonthlySummaryMail::class);

        // Switched off in Admin › App control: nothing the next month.
        AppSettings::set(AppControl::MONTHLY_SUMMARY, false);
        $this->travelTo($this->india('2026-12-01 10:00'));
        $this->artisan('summary:monthly');
        Notification::assertSentToTimes($this->user, MonthlySummaryMail::class, 1);
    }

    public function test_users_cannot_turn_the_reminders_off(): void
    {
        $this->actingAs($this->user)->get('/settings')->assertOk()->assertDontSee('Reminders');
        $this->actingAs($this->user)->get('/settings/reminders')->assertNotFound();
        $this->actingAs($this->user)->put('/settings/reminders', ['monthly_summary' => 0])->assertNotFound();

        Sanctum::actingAs($this->user);
        $this->putJson('/api/v1/settings/reminders', ['budget_alert_push' => false])->assertNotFound();
        $this->assertArrayNotHasKey('daily_reminder_at', $this->getJson('/api/v1/me')->json('data'));
    }

    public function test_admins_switch_the_automatic_messages_for_everyone(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->get('/admin/app-control')->assertOk()
            ->assertSee('Reminders and automatic messages')->assertSee('Daily reminder')->assertSee('9:00 PM')
            ->assertSee('Budget alerts')->assertSee('Monthly summary email')->assertSee('Birthday wishes');

        $this->actingAs($admin)->put('/admin/app-control/reminders', ['daily_reminder_at' => '20:30'])
            ->assertSessionHas('success', 'The daily reminder now goes out at 8:30 PM.');
        $this->assertSame('20:30', AppControl::dailyReminderAt());
        $this->actingAs($admin)->put('/admin/app-control/reminders', ['daily_reminder_at' => '8pm'])->assertSessionHasErrors('daily_reminder_at');

        $this->actingAs($admin)->put('/admin/app-control/reminders', ['daily_reminder' => 0])
            ->assertSessionHas('success', 'The daily reminder will not go out to anyone.');
        $this->assertNull(AppControl::dailyReminderAt());
        $this->actingAs($admin)->put('/admin/app-control/reminders', ['monthly_summary' => 0]);
        $this->assertFalse(AppControl::monthlySummary());
        $this->actingAs($admin)->put('/admin/app-control/reminders', ['daily_reminder' => 1]);
        $this->assertSame('20:30', AppControl::dailyReminderAt()); // back on, at the time it had

        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'app.daily_reminder_off', 'admin_id' => $admin->id]);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'app.monthly_summary_off']);

        // Not for users.
        $this->actingAs($this->user)->put('/admin/app-control/reminders', ['daily_reminder' => 0])->assertForbidden();
    }
}
