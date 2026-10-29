<?php

namespace Tests\Feature\Tracker;

use App\Models\Group;
use App\Models\GroupExpense;
use App\Models\User;
use App\Support\Groups;
use Illuminate\Support\Facades\Notification;

/** Groups on the website: the list, a group's page, and its forms. Signed in as Asha. */
class GroupsPagesTest extends TrackerTestCase
{
    private User $rishi;
    private User $meera;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->rishi = $this->makeUser(['name' => 'Rishi Kapoor', 'email' => 'rishi@example.com']);
        $this->meera = $this->makeUser(['name' => 'Meera Iyer', 'email' => 'meera@example.com']);
    }

    /** Asha's "Goa trip" with Rishi and Meera. */
    private function trio(): Group
    {
        $group = Groups::create($this->user, 'Goa trip', '🏖️');
        Groups::addMember($group, $this->user, 'rishi@example.com');
        Groups::addMember($group, $this->user, 'meera@example.com');

        return $group;
    }

    private function expenseForm(Group $group, array $fields)
    {
        return $this->post("/groups/{$group->id}/expenses", $fields + ['description' => 'Dinner', 'spent_on' => '2026-10-20', 'paid_by' => $this->user->id]);
    }

    public function test_every_groups_page_opens(): void
    {
        $this->get('/groups')->assertOk()->assertSee('No groups yet');
        $this->get('/groups/new')->assertOk()->assertSee('Create group');

        $group = $this->trio();
        $this->expenseForm($group, ['amount' => '1200', 'split' => 'equal', 'participants' => [$this->user->id, $this->rishi->id, $this->meera->id]]);
        $expense = GroupExpense::firstOrFail();

        foreach (['/groups', "/groups/{$group->id}", "/groups/{$group->id}/edit", "/groups/{$group->id}/expenses/new", "/groups/{$group->id}/expenses/{$expense->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/dashboard')->assertSee('Groups')->assertSee(route('groups.index'), false);
    }

    public function test_making_a_group_and_adding_people_by_email(): void
    {
        $this->post('/groups', ['name' => ''])->assertSessionHasErrors(['name' => 'Give the group a name.']);

        $this->post('/groups', ['name' => 'Flat 302', 'emoji' => '🏠'])->assertRedirect();
        $group = Group::firstOrFail();
        $this->assertSame(['Flat 302', '🏠', $this->user->id], [$group->name, $group->emoji, $group->created_by]);
        $this->assertDatabaseHas('group_members', ['group_id' => $group->id, 'user_id' => $this->user->id]);

        $this->post("/groups/{$group->id}/members", ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors(['email' => 'No Expense Tracker account uses that email — ask them to sign up first.']);
        $this->post("/groups/{$group->id}/members", ['email' => 'rishi@example.com'])
            ->assertRedirect(route('groups.show', $group) . '#members')->assertSessionHas('success', 'Rishi Kapoor added to the group');

        $this->get("/groups/{$group->id}")->assertOk()->assertSee('Rishi Kapoor')->assertSee('rishi@example.com');
        $this->put("/groups/{$group->id}", ['name' => 'Flat 302B', 'emoji' => '🏠'])->assertRedirect(route('groups.show', $group));
        $this->assertSame('Flat 302B', $group->fresh()->name);
    }

    public function test_an_expense_split_equally_or_by_exact_amounts(): void
    {
        $group = $this->trio();

        // Typed as on screen, in rupees with grouping.
        $this->expenseForm($group, ['amount' => '1,200', 'split' => 'equal', 'participants' => [$this->user->id, $this->rishi->id, $this->meera->id]])
            ->assertRedirect(route('groups.show', $group))->assertSessionHas('success', 'Expense of ₹1,200.00 added · Dinner');
        $expense = GroupExpense::with('shares')->firstOrFail();
        $this->assertSame([40000, 40000, 40000], $expense->shares->sortBy('user_id')->pluck('amount_minor')->all());

        $exact = fn (string $a, string $r) => ['amount' => '1000', 'split' => 'exact', 'paid_by' => $this->rishi->id, 'description' => 'Cab',
            'shares' => [$this->user->id => $a, $this->rishi->id => $r, $this->meera->id => '']];
        $this->expenseForm($group, $exact('600', '300'))->assertSessionHasErrors(['shares' => 'The shares add up to ₹900.00, but the expense is ₹1,000.00.']);
        $this->expenseForm($group, $exact('6oo', '400'))->assertSessionHasErrors('shares.' . $this->user->id);
        $this->expenseForm($group, ['amount' => '100', 'split' => 'equal'])->assertSessionHasErrors(['participants' => 'Pick at least one person to split it with.']);
        $this->assertDatabaseCount('group_expenses', 1);

        $this->expenseForm($group, $exact('600.50', '399.50'))->assertRedirect(route('groups.show', $group));
        $cab = GroupExpense::with('shares')->where('description', 'Cab')->firstOrFail();
        $this->assertSame(['exact', $this->rishi->id, 100000], [$cab->split, $cab->paid_by, $cab->amount_minor]);
        $this->assertSame([$this->user->id => 60050, $this->rishi->id => 39950], $cab->shares->pluck('amount_minor', 'user_id')->all());

        // Asha paid ₹1,200 (her share ₹400) and owes ₹600.50 of Rishi's cab.
        $this->get("/groups/{$group->id}")->assertOk()
            ->assertSee('Your share ₹400.00')->assertSee('Your share ₹600.50')
            ->assertSee('you are owed ₹199.50');
        $this->get('/groups')->assertOk()->assertSee('You are owed')->assertSee('₹199.50');

        // Editing keeps untouched amounts as they were; deleting removes it.
        $this->get("/groups/{$group->id}/expenses/{$cab->id}/edit")->assertOk()->assertSee('600.50');
        $this->put("/groups/{$group->id}/expenses/{$cab->id}", ['description' => 'Cab', 'amount' => '1000.00', 'spent_on' => '2026-10-20',
            'paid_by' => $this->rishi->id, 'split' => 'equal', 'participants' => [$this->rishi->id, $this->meera->id]])
            ->assertRedirect(route('groups.show', $group));
        $this->assertSame([$this->rishi->id => 50000, $this->meera->id => 50000], $cab->fresh('shares')->shares->pluck('amount_minor', 'user_id')->all());
        $this->delete("/groups/{$group->id}/expenses/{$cab->id}")->assertRedirect(route('groups.show', $group) . '#expenses');
        $this->assertDatabaseMissing('group_expenses', ['id' => $cab->id]);
    }

    public function test_settling_up_from_a_suggestion_or_by_hand(): void
    {
        $group = $this->trio();
        $this->expenseForm($group, ['amount' => '900', 'split' => 'equal', 'participants' => [$this->user->id, $this->rishi->id, $this->meera->id]]);

        $this->get("/groups/{$group->id}")->assertOk()
            ->assertSeeInOrder(['Rishi Kapoor', 'pays', 'you', '₹300.00', 'Mark paid'])
            ->assertSee('You get back')->assertSee('Owes');

        $this->post("/groups/{$group->id}/settlements", ['from_user_id' => $this->rishi->id, 'to_user_id' => $this->user->id, 'amount_minor' => 30000])
            ->assertRedirect(route('groups.show', $group))->assertSessionHas('success', 'Payment recorded · Rishi Kapoor paid Asha Rao ₹300.00');
        $this->post("/groups/{$group->id}/settlements", ['from_user_id' => $this->meera->id, 'to_user_id' => $this->user->id, 'amount' => '', 'on' => '2026-10-21'])
            ->assertSessionHasErrors(['amount' => 'Enter an amount greater than zero.']);
        $this->post("/groups/{$group->id}/settlements", ['from_user_id' => $this->meera->id, 'to_user_id' => $this->user->id, 'amount' => '300', 'on' => '2026-10-21'])
            ->assertRedirect(route('groups.show', $group));

        $this->get("/groups/{$group->id}")->assertOk()->assertSee('Everyone is settled up')->assertSee('you are settled up')->assertSee('Recent payments');
        $this->get('/groups')->assertSee('Settled up');
    }

    public function test_a_recent_payment_can_be_undone(): void
    {
        $group = $this->trio();
        $this->expenseForm($group, ['amount' => '900', 'split' => 'equal', 'participants' => [$this->user->id, $this->rishi->id, $this->meera->id]]);
        $settlement = Groups::settle($group, $this->rishi, $this->rishi->id, $this->user->id, 30000, '2026-10-21');

        $this->get("/groups/{$group->id}")->assertOk()
            ->assertSee(route('groups.settlements.destroy', [$group, $settlement]), false)
            ->assertSee('Undo this payment?')
            ->assertSee('Undo');

        $this->delete("/groups/{$group->id}/settlements/{$settlement->id}")
            ->assertRedirect(route('groups.show', $group))
            ->assertSessionHas('success', 'Payment removed · Rishi Kapoor paid Asha Rao ₹300.00');
        $this->assertDatabaseMissing('group_settlements', ['id' => $settlement->id]);
        $this->get("/groups/{$group->id}")->assertSeeInOrder(['Rishi Kapoor', 'pays', 'you', '₹300.00', 'Mark paid'])->assertDontSee('Recent payments');

        $this->delete("/groups/{$group->id}/settlements/{$settlement->id}")->assertNotFound();
    }

    public function test_a_member_with_a_balance_cannot_be_removed_or_leave(): void
    {
        $group = $this->trio();
        $this->expenseForm($group, ['amount' => '100', 'split' => 'equal', 'participants' => [$this->user->id, $this->rishi->id]]);

        $this->delete("/groups/{$group->id}/members/{$this->rishi->id}")
            ->assertSessionHasErrors(['member' => "Rishi Kapoor can't be removed while they owe ₹50.00 — settle up first."]);
        $this->delete("/groups/{$group->id}/members/{$this->user->id}")
            ->assertSessionHasErrors(['member' => "You can't leave while you are owed ₹50.00 — settle up first."]);

        $this->delete("/groups/{$group->id}/members/{$this->meera->id}")
            ->assertRedirect(route('groups.show', $group) . '#members')->assertSessionHas('success', 'Meera Iyer removed from the group');
        $this->assertDatabaseMissing('group_members', ['group_id' => $group->id, 'user_id' => $this->meera->id]);

        // Leaving once square sends you back to the list.
        $this->actingAs($this->rishi)->post("/groups/{$group->id}/settlements", ['from_user_id' => $this->rishi->id, 'to_user_id' => $this->user->id, 'amount_minor' => 5000]);
        $this->actingAs($this->rishi)->delete("/groups/{$group->id}/members/{$this->rishi->id}")
            ->assertRedirect(route('groups.index'))->assertSessionHas('success', 'You left 🏖️ Goa trip');
        $this->actingAs($this->rishi)->get("/groups/{$group->id}")->assertNotFound();
    }

    public function test_only_the_creator_deletes_a_group(): void
    {
        $group = $this->trio();

        $this->actingAs($this->rishi)->get("/groups/{$group->id}")->assertOk()->assertDontSee('Delete group');
        $this->actingAs($this->rishi)->from("/groups/{$group->id}")->delete("/groups/{$group->id}")
            ->assertRedirect("/groups/{$group->id}")->assertSessionHas('error', 'Only Asha Rao, who made this group, can delete it.');
        $this->assertDatabaseHas('groups', ['id' => $group->id]);

        $this->actingAs($this->user)->get("/groups/{$group->id}")->assertSee('Delete group');
        $this->delete("/groups/{$group->id}")->assertRedirect(route('groups.index'));
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_someone_outside_the_group_gets_not_found(): void
    {
        $group = $this->trio();
        $this->expenseForm($group, ['amount' => '100', 'split' => 'equal', 'participants' => [$this->user->id]]);
        $expense = GroupExpense::firstOrFail();
        $this->actingAs($this->makeUser(['email' => 'out@example.com']));

        $this->get('/groups')->assertOk()->assertDontSee('Goa trip');
        foreach (["/groups/{$group->id}", "/groups/{$group->id}/edit", "/groups/{$group->id}/expenses/new", "/groups/{$group->id}/expenses/{$expense->id}/edit"] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post("/groups/{$group->id}/expenses", ['amount' => '5'])->assertNotFound();
        $this->delete("/groups/{$group->id}/expenses/{$expense->id}")->assertNotFound();
        $this->post("/groups/{$group->id}/members", ['email' => 'out@example.com'])->assertNotFound();
        $this->delete("/groups/{$group->id}")->assertNotFound();
        $this->assertDatabaseHas('group_expenses', ['id' => $expense->id]);
    }
}
