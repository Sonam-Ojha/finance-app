<?php

namespace Tests\Feature;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\RecurringEntry;
use App\Models\User;
use App\Support\DefaultCategories;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo($this->india('2026-10-29 15:00'));
        $this->user = User::factory()->create(['currency' => 'INR']);
        DefaultCategories::seed($this->user);
    }

    private function india(string $at): Carbon
    {
        return Carbon::parse($at, 'Asia/Kolkata');
    }

    private function bills(): int
    {
        return $this->user->categories()->where('name', 'Bills & Utilities')->value('id');
    }

    private function rule(array $fields): RecurringEntry
    {
        $rule = new RecurringEntry($fields + ['user_id' => $this->user->id, 'kind' => 'expense', 'amount_minor' => 1500000, 'category_id' => $this->bills(), 'frequency' => 'monthly']);
        $rule->startOn(Carbon::parse($fields['next_on'] ?? '2026-11-01'));
        $rule->save();

        return $rule;
    }

    private function runAt(string $at): void
    {
        $this->travelTo($this->india($at));
        $this->artisan('recurring:post')->assertSuccessful();
    }

    public function test_rent_is_added_on_its_day_every_month_once(): void
    {
        $this->actingAs($this->user)->post('/recurring', [
            'kind' => 'expense', 'amount' => '15000', 'category_id' => $this->bills(), 'note' => 'Rent',
            'frequency' => 'monthly', 'next_on' => '2026-11-01', 'payment_method' => 'upi',
        ])->assertRedirect('/recurring')->assertSessionHas('success', 'Added: Every month on the 1st. The first one goes in on Sun 1 Nov.');

        $this->runAt('2026-10-31 23:00');
        $this->assertSame(0, AppExpense::count());

        $this->runAt('2026-11-01 00:30');
        $this->runAt('2026-11-01 01:00'); // again the same day: nothing new
        $expense = AppExpense::sole();
        $rule = RecurringEntry::sole();
        $this->assertSame([1500000, 'Rent', 'upi', '2026-11-01', $rule->id], [
            $expense->amount_minor, $expense->note, $expense->payment_method, $expense->spent_at->toDateString(), $expense->recurring_entry_id,
        ]);
        $this->assertSame(['2026-12-01', '2026-11-01'], [$rule->next_on->toDateString(), $rule->last_on->toDateString()]);

        $this->actingAs($this->user)->get('/recurring')->assertOk()
            ->assertSee('Rent')->assertSee('Every month on the 1st')->assertSee('Next:')->assertSee('Tue 1 Dec 2026');
    }

    public function test_the_31st_falls_back_to_shorter_months_and_returns(): void
    {
        $rule = $this->rule(['next_on' => '2027-01-31']);
        $this->runAt('2027-01-31 08:00');
        $this->assertSame('2027-02-28', $rule->fresh()->next_on->toDateString());
        $this->runAt('2027-02-28 08:00');
        $this->assertSame('2027-03-31', $rule->fresh()->next_on->toDateString());
        $this->assertSame(['2027-01-31', '2027-02-28'], AppExpense::orderBy('spent_at')->pluck('spent_at')->map->toDateString()->all());
    }

    public function test_weekly_yearly_and_income(): void
    {
        $weekly = $this->rule(['frequency' => 'weekly', 'next_on' => '2026-10-30']);
        $yearly = $this->rule(['frequency' => 'yearly', 'next_on' => '2026-10-30']);
        $salary = new RecurringEntry(['user_id' => $this->user->id, 'kind' => 'income', 'amount_minor' => 5000000, 'source' => 'Salary', 'frequency' => 'monthly']);
        $salary->startOn(Carbon::parse('2026-10-30'))->save();

        $this->runAt('2026-10-30 09:00');
        $this->assertSame('2026-11-06', $weekly->fresh()->next_on->toDateString());
        $this->assertSame('2027-10-30', $yearly->fresh()->next_on->toDateString());
        $this->assertSame(['Every week on Friday', 'Every year on 30 October'], [$weekly->fresh()->describe(), $yearly->fresh()->describe()]);
        $income = AppIncome::sole();
        $this->assertSame(['Salary', 5000000, $salary->id], [$income->source, $income->amount_minor, $income->recurring_entry_id]);
    }

    public function test_missed_days_are_caught_up_but_paused_ones_are_not(): void
    {
        $missed = $this->rule(['next_on' => '2026-08-05']);
        $paused = $this->rule(['next_on' => '2026-08-10', 'active' => false]);

        $this->runAt('2026-10-29 10:00');
        $this->assertSame(['2026-08-05', '2026-09-05', '2026-10-05'], AppExpense::orderBy('spent_at')->pluck('spent_at')->map->toDateString()->all());
        $this->assertSame('2026-11-05', $missed->fresh()->next_on->toDateString());

        // Resumed: it carries on from today, the paused months are not added.
        $this->actingAs($this->user)->patch("/recurring/{$paused->id}/toggle")->assertSessionHas('success');
        $this->assertSame('2026-11-10', $paused->fresh()->next_on->toDateString());
        $this->runAt('2026-10-29 11:00');
        $this->assertSame(3, AppExpense::count());
    }

    public function test_the_app_api(): void
    {
        Sanctum::actingAs($this->user);
        $id = $this->postJson('/api/v1/recurring', [
            'kind' => 'expense', 'amount_minor' => 64900, 'category_id' => $this->bills(), 'note' => 'Netflix',
            'frequency' => 'monthly', 'next_on' => '2026-11-15',
        ])->assertCreated()
            ->assertJsonPath('data.description', 'Every month on the 15th')
            ->assertJsonPath('data.category_name', 'Bills & Utilities')
            ->assertJsonPath('data.active', true)
            ->json('data.id');

        $this->getJson('/api/v1/recurring')->assertOk()->assertJsonPath('data.0.note', 'Netflix');

        $this->postJson('/api/v1/recurring', ['kind' => 'expense', 'amount_minor' => 0, 'frequency' => 'daily', 'next_on' => '2026-01-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['amount_minor', 'category_id', 'frequency', 'next_on']);
        $this->postJson('/api/v1/recurring', ['kind' => 'income', 'amount_minor' => 100, 'frequency' => 'monthly', 'next_on' => '2026-11-01'])
            ->assertStatus(422)->assertJsonPath('errors.source.0', 'Say where the money comes from.');

        // Paused, then its day passes; resuming sends the old day back unchanged.
        $this->putJson("/api/v1/recurring/{$id}", ['kind' => 'expense', 'amount_minor' => 64900, 'category_id' => $this->bills(), 'frequency' => 'monthly', 'next_on' => '2026-11-15', 'active' => false])
            ->assertOk()->assertJsonPath('data.active', false);
        $this->travelTo($this->india('2026-12-20 10:00'));
        $this->putJson("/api/v1/recurring/{$id}", ['kind' => 'expense', 'amount_minor' => 64900, 'category_id' => $this->bills(), 'frequency' => 'monthly', 'next_on' => '2026-11-15', 'active' => true])
            ->assertOk()->assertJsonPath('data.next_on', '2027-01-15');

        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson("/api/v1/recurring/{$id}")->assertNotFound();
        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/recurring/{$id}")->assertNoContent();
        $this->assertSame(0, RecurringEntry::count());
    }

    public function test_the_website_form(): void
    {
        $this->actingAs($this->user)->get('/recurring')->assertOk()->assertSee('Nothing repeats yet');
        $this->actingAs($this->user)->get('/recurring/new?kind=income')->assertOk()->assertSee('Source');
        $rule = $this->rule(['next_on' => '2026-11-01']);
        $this->actingAs($this->user)->get("/recurring/{$rule->id}/edit")->assertOk()->assertSee('Next one on');
        $this->actingAs(User::factory()->create())->get("/recurring/{$rule->id}/edit")->assertNotFound();
    }
}
