<?php

namespace Tests\Feature\Api\V1;

use App\Models\GoalContribution;
use App\Models\SavingsGoal;
use App\Models\User;
use Carbon\Carbon;
use Tests\Concerns\FakesFirebase;

/** Savings goals: /api/v1/goals and /api/v1/goals/{id}/contributions */
class GoalsTest extends ApiTestCase
{
    use FakesFirebase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Thursday 29 October 2026, 3 p.m. in India.
        $this->travelTo(Carbon::parse('2026-10-29 15:00', 'Asia/Kolkata'));
        $this->user = $this->signIn(['currency' => 'INR']);
    }

    private function goal(array $overrides = []): array
    {
        return $this->postJson('/api/v1/goals', $overrides + [
            'name' => 'Goa trip', 'emoji' => '🏖️', 'color' => '#199cda', 'target_minor' => 2000000, 'deadline' => '2027-03-31',
        ])->assertCreated()->json('data');
    }

    private function contribute(int $goalId, int $amount, array $extra = [])
    {
        return $this->postJson("/api/v1/goals/{$goalId}/contributions", $extra + ['amount_minor' => $amount]);
    }

    public function test_a_goal_is_created_with_the_documented_shape(): void
    {
        $goal = $this->goal();

        $this->assertSame(
            ['id', 'name', 'emoji', 'color', 'target_minor', 'saved_minor', 'progress', 'reached', 'deadline', 'monthly_needed_minor', 'archived', 'created_at'],
            array_keys($goal),
        );
        $this->assertSame('Goa trip', $goal['name']);
        $this->assertSame('🏖️', $goal['emoji']);
        $this->assertSame('#199CDA', $goal['color']);
        $this->assertSame(2000000, $goal['target_minor']);
        $this->assertSame(0, $goal['saved_minor']);
        $this->assertEquals(0, $goal['progress']);
        $this->assertFalse($goal['reached']);
        $this->assertSame('2027-03-31', $goal['deadline']);
        // ₹20,000 over the five whole months to 31 March: ₹4,000 a month.
        $this->assertSame(400000, $goal['monthly_needed_minor']);
        $this->assertFalse($goal['archived']);
        $this->assertNotNull($goal['created_at']);

        $this->getJson("/api/v1/goals/{$goal['id']}")->assertOk()->assertJsonPath('data.name', 'Goa trip');
    }

    public function test_without_a_deadline_there_is_no_monthly_amount(): void
    {
        $goal = $this->goal(['deadline' => null]);

        $this->assertNull($goal['deadline']);
        $this->assertNull($goal['monthly_needed_minor']);
    }

    public function test_bad_goals_are_refused_with_sentences(): void
    {
        $this->postJson('/api/v1/goals', [])->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Give the goal a name.')
            ->assertJsonPath('errors.emoji.0', 'Pick an emoji.')
            ->assertJsonPath('errors.color.0', 'Pick a colour.')
            ->assertJsonPath('errors.target_minor.0', 'Enter a target greater than zero.');

        $this->postJson('/api/v1/goals', [
            'name' => str_repeat('a', 41), 'emoji' => '🎯', 'color' => 'blue', 'target_minor' => 0, 'deadline' => '2026-10-28',
        ])->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Keep the name to 40 characters.')
            ->assertJsonPath('errors.color.0', 'Pick a colour.')
            ->assertJsonPath('errors.target_minor.0', 'Enter a target greater than zero.')
            ->assertJsonPath('errors.deadline.0', 'Pick a deadline that is today or later.');

        // Today is fine.
        $this->goal(['deadline' => '2026-10-29']);
    }

    public function test_money_goes_in_and_out_and_saved_is_their_sum(): void
    {
        $id = $this->goal()['id'];

        $this->contribute($id, 500000, ['note' => 'Diwali bonus', 'on' => '2026-10-20'])->assertOk()
            ->assertJsonPath('data.saved_minor', 500000)
            ->assertJsonPath('data.progress', 0.25)
            // ₹15,000 left over five months.
            ->assertJsonPath('data.monthly_needed_minor', 300000);
        $this->contribute($id, 250000)->assertOk()->assertJsonPath('data.saved_minor', 750000);
        $this->contribute($id, -100000, ['note' => 'Dentist'])->assertOk()->assertJsonPath('data.saved_minor', 650000);

        $this->assertSame(650000, (int) GoalContribution::where('goal_id', $id)->sum('amount_minor'));
        $this->assertSame(650000, SavingsGoal::find($id)->saved_minor);

        // Newest first, with the documented shape; "on" defaults to today in India.
        $list = $this->getJson("/api/v1/goals/{$id}/contributions")->assertOk()->json('data');
        $this->assertSame(['id', 'amount_minor', 'note', 'on'], array_keys($list[0]));
        $this->assertSame([[-100000, 'Dentist', '2026-10-29'], [250000, null, '2026-10-29'], [500000, 'Diwali bonus', '2026-10-20']],
            array_map(fn ($c) => [$c['amount_minor'], $c['note'], $c['on']], $list));
    }

    public function test_a_withdrawal_cannot_take_more_than_is_saved(): void
    {
        $id = $this->goal()['id'];

        $this->contribute($id, -100)->assertStatus(422)
            ->assertJsonPath('errors.amount_minor.0', 'There is nothing saved in this goal to withdraw yet.');

        $this->contribute($id, 300000)->assertOk();
        $this->contribute($id, -300001)->assertStatus(422)
            ->assertJsonPath('errors.amount_minor.0', 'You can withdraw at most ₹3,000.00 — that is all this goal holds.');
        $this->assertSame(300000, SavingsGoal::find($id)->saved_minor);

        // Taking out exactly what is there is fine.
        $this->contribute($id, -300000)->assertOk()->assertJsonPath('data.saved_minor', 0);
    }

    public function test_bad_contributions_are_refused_with_sentences(): void
    {
        $id = $this->goal()['id'];

        $this->contribute($id, 0)->assertStatus(422)
            ->assertJsonPath('errors.amount_minor.0', 'Enter an amount to add, or a negative one to withdraw.');
        $this->postJson("/api/v1/goals/{$id}/contributions", ['amount_minor' => 'lots'])->assertStatus(422);
        $this->contribute($id, 100, ['note' => str_repeat('n', 121)])->assertStatus(422)
            ->assertJsonPath('errors.note.0', 'Keep the note to 120 characters.');
        $this->contribute($id, 100, ['on' => '2026-11-15'])->assertStatus(422)
            ->assertJsonPath('errors.on.0', 'Pick a day that is not in the future.');
        $this->assertSame(0, GoalContribution::count());
    }

    public function test_reaching_the_target_pushes_once(): void
    {
        $this->fakeFirebase();
        $this->phone($this->user);
        $id = $this->goal(['target_minor' => 1000000])['id'];

        $this->contribute($id, 600000)->assertOk()->assertJsonPath('data.reached', false);
        $this->assertSame([], $this->pushes());

        $this->contribute($id, 500000)->assertOk()
            ->assertJsonPath('data.reached', true)
            ->assertJsonPath('data.progress', 1)
            ->assertJsonPath('data.saved_minor', 1100000)
            ->assertJsonPath('data.monthly_needed_minor', null);
        $this->assertSame([['title' => '🎉 Goal reached: Goa trip', 'body' => 'You saved ₹11,000.00 for 🏖️ Goa trip. Well done!']], $this->pushes());

        // Out and back in again: no second push.
        $this->contribute($id, -200000)->assertOk()->assertJsonPath('data.reached', false);
        $this->contribute($id, 200000)->assertOk()->assertJsonPath('data.reached', true);
        $this->assertCount(1, $this->pushes());
    }

    public function test_lowering_the_target_below_what_is_saved_counts_as_reaching_it(): void
    {
        $this->fakeFirebase();
        $this->phone($this->user);
        $goal = $this->goal();
        $this->contribute($goal['id'], 500000)->assertOk();

        $this->putJson("/api/v1/goals/{$goal['id']}", ['name' => 'Goa trip', 'emoji' => '🏖️', 'color' => '#199CDA', 'target_minor' => 500000, 'deadline' => '2027-03-31'])
            ->assertOk()->assertJsonPath('data.reached', true);
        $this->assertCount(1, $this->pushes());
    }

    public function test_a_goal_is_edited_and_archived(): void
    {
        $id = $this->goal()['id'];

        $this->putJson("/api/v1/goals/{$id}", [
            'name' => 'Goa & Gokarna', 'emoji' => '✈️', 'color' => '#34A866', 'target_minor' => 2500000, 'deadline' => null, 'archived' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Goa & Gokarna')
            ->assertJsonPath('data.emoji', '✈️')
            ->assertJsonPath('data.target_minor', 2500000)
            ->assertJsonPath('data.deadline', null)
            ->assertJsonPath('data.archived', true);

        // Leaving `archived` out leaves it as it is; false brings it back.
        $this->putJson("/api/v1/goals/{$id}", ['name' => 'Goa', 'emoji' => '✈️', 'color' => '#34A866', 'target_minor' => 2500000])
            ->assertOk()->assertJsonPath('data.archived', true);
        $this->putJson("/api/v1/goals/{$id}", ['name' => 'Goa', 'emoji' => '✈️', 'color' => '#34A866', 'target_minor' => 2500000, 'archived' => false])
            ->assertOk()->assertJsonPath('data.archived', false);
    }

    public function test_a_deadline_that_has_passed_can_be_kept_but_not_newly_set(): void
    {
        $id = $this->goal(['deadline' => '2026-11-15'])['id'];
        $this->travelTo(Carbon::parse('2026-12-01 10:00', 'Asia/Kolkata'));

        $body = ['name' => 'Goa trip', 'emoji' => '🏖️', 'color' => '#199CDA', 'target_minor' => 2000000];
        $this->putJson("/api/v1/goals/{$id}", $body + ['deadline' => '2026-11-15'])->assertOk()
            // Past its deadline: everything left, now.
            ->assertJsonPath('data.monthly_needed_minor', 2000000);
        $this->putJson("/api/v1/goals/{$id}", $body + ['deadline' => '2026-11-20'])->assertStatus(422)
            ->assertJsonPath('errors.deadline.0', 'Pick a deadline that is today or later.');
    }

    public function test_the_list_has_active_goals_first_then_archived(): void
    {
        $first  = $this->goal(['name' => 'Laptop'])['id'];
        $second = $this->goal(['name' => 'Bike'])['id'];
        $third  = $this->goal(['name' => 'Phone'])['id'];
        SavingsGoal::find($first)->update(['archived_at' => now()]);

        $this->assertSame([$second, $third, $first], array_column($this->getJson('/api/v1/goals')->assertOk()->json('data'), 'id'));
    }

    public function test_a_goal_is_deleted_with_its_history(): void
    {
        $id = $this->goal()['id'];
        $this->contribute($id, 1000)->assertOk();

        $this->deleteJson("/api/v1/goals/{$id}")->assertNoContent();

        $this->assertNull(SavingsGoal::find($id));
        $this->assertSame(0, GoalContribution::where('goal_id', $id)->count());
    }

    public function test_someone_elses_goal_is_not_found(): void
    {
        $other = $this->makeUser(['email' => 'other@example.com']);
        $theirs = SavingsGoal::create(['user_id' => $other->id, 'name' => 'Theirs', 'emoji' => '🎯', 'color' => '#DA2020', 'target_minor' => 100]);
        $body = ['name' => 'Mine now', 'emoji' => '🎯', 'color' => '#DA2020', 'target_minor' => 100];

        $this->getJson("/api/v1/goals/{$theirs->id}")->assertNotFound();
        $this->putJson("/api/v1/goals/{$theirs->id}", $body)->assertNotFound();
        $this->deleteJson("/api/v1/goals/{$theirs->id}")->assertNotFound();
        $this->getJson("/api/v1/goals/{$theirs->id}/contributions")->assertNotFound();
        $this->contribute($theirs->id, 100)->assertNotFound();

        $this->assertSame([], $this->getJson('/api/v1/goals')->json('data'));
        $this->assertSame('Theirs', $theirs->fresh()->name);
        $this->assertSame(0, $theirs->fresh()->saved_minor);
    }
}
