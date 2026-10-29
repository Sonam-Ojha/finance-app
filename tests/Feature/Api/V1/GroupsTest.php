<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\AddedToGroup;
use App\Notifications\GroupActivity;
use App\Support\Groups;
use App\Support\Labels;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesFirebase;

/** Groups (Splitwise-style shared expenses) through /api/v1/groups. */
class GroupsTest extends ApiTestCase
{
    use FakesFirebase;

    private User $asha;
    private User $rishi;
    private User $meera;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Made in this order, so Asha has the lowest id and takes a leftover paisa first.
        $this->asha  = $this->makeUser(['name' => 'Asha Rao', 'email' => 'asha@example.com']);
        $this->rishi = $this->makeUser(['name' => 'Rishi Kapoor', 'email' => 'rishi@example.com']);
        $this->meera = $this->makeUser(['name' => 'Meera Iyer', 'email' => 'meera@example.com']);
    }

    private function actAs(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    /** Asha's "Goa trip" with Rishi and Meera in it, signed in as Asha. */
    private function trio(): int
    {
        $this->actAs($this->asha);
        $id = $this->postJson('/api/v1/groups', ['name' => 'Goa trip', 'emoji' => '🏖️'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/groups/{$id}/members", ['email' => 'rishi@example.com'])->assertOk();
        $this->postJson("/api/v1/groups/{$id}/members", ['email' => 'MEERA@Example.com'])->assertOk();

        return $id;
    }

    private function addExpense(int $group, array $body)
    {
        return $this->postJson("/api/v1/groups/{$group}/expenses", $body + ['spent_on' => '2026-10-20', 'description' => 'Dinner']);
    }

    /** @return array<int, int> user id => balance, as the detail endpoint reports them */
    private function balances(int $group): array
    {
        return collect($this->getJson("/api/v1/groups/{$group}")->assertOk()->json('data.members'))
            ->mapWithKeys(fn (array $m) => [$m['user_id'] => $m['balance_minor']])->all();
    }

    /** ₹10.00 equally between three (334/333/333), then Rishi's ₹5.01 split exactly 100/200/201. */
    private function unevenPaise(int $group): void
    {
        $this->actAs($this->asha);
        $this->addExpense($group, [
            'amount_minor' => 1000, 'paid_by' => $this->asha->id, 'split' => 'equal',
            'participants' => [$this->meera->id, $this->rishi->id, $this->asha->id],
        ])->assertCreated();

        $this->actAs($this->rishi);
        $this->addExpense($group, [
            'description' => 'Snacks', 'amount_minor' => 501, 'paid_by' => $this->rishi->id, 'split' => 'exact',
            'shares' => [
                ['user_id' => $this->asha->id, 'amount_minor' => 100],
                ['user_id' => $this->rishi->id, 'amount_minor' => 200],
                ['user_id' => $this->meera->id, 'amount_minor' => 201],
            ],
        ])->assertCreated();
    }

    public function test_creating_a_group_makes_the_creator_its_first_member(): void
    {
        $this->actAs($this->asha);
        $this->postJson('/api/v1/groups', ['name' => 'Flat 302'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'emoji', 'created_by', 'member_count', 'members' => [['user_id', 'name', 'email', 'avatar_url', 'balance_minor']], 'my_balance_minor', 'created_at']])
            ->assertJsonPath('data.name', 'Flat 302')
            ->assertJsonPath('data.emoji', '👥')
            ->assertJsonPath('data.created_by', $this->asha->id)
            ->assertJsonPath('data.member_count', 1)
            ->assertJsonPath('data.members.0.user_id', $this->asha->id)
            ->assertJsonPath('data.members.0.name', 'Asha Rao')
            ->assertJsonPath('data.members.0.email', 'asha@example.com')
            ->assertJsonPath('data.members.0.avatar_url', null)
            ->assertJsonPath('data.my_balance_minor', 0);

        $this->postJson('/api/v1/groups', ['name' => ''])->assertStatus(422)->assertJsonPath('errors.name.0', 'Give the group a name.');
        $this->postJson('/api/v1/groups', ['name' => str_repeat('a', 41)])->assertStatus(422)->assertJsonPath('errors.name.0', 'Keep the group name to 40 characters.');

        $this->getJson('/api/v1/groups')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_renaming_a_group(): void
    {
        $id = $this->trio();
        $this->actAs($this->rishi)->putJson("/api/v1/groups/{$id}", ['name' => 'Goa 2026', 'emoji' => '🌴'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Goa 2026')
            ->assertJsonPath('data.emoji', '🌴')
            ->assertJsonPath('data.member_count', 3);
    }

    public function test_balances_and_the_fewest_payments_for_three_people_with_uneven_paise(): void
    {
        $id = $this->trio();
        $this->unevenPaise($id);

        $this->actAs($this->asha);
        $this->assertSame([$this->asha->id => 566, $this->rishi->id => -32, $this->meera->id => -534], $this->balances($id));

        $this->getJson("/api/v1/groups/{$id}")
            ->assertJsonPath('data.my_balance_minor', 566)
            ->assertJsonPath('data.suggested_settlements', [
                ['from_user_id' => $this->meera->id, 'from_name' => 'Meera Iyer', 'to_user_id' => $this->asha->id, 'to_name' => 'Asha Rao', 'amount_minor' => 534],
                ['from_user_id' => $this->rishi->id, 'from_name' => 'Rishi Kapoor', 'to_user_id' => $this->asha->id, 'to_name' => 'Asha Rao', 'amount_minor' => 32],
            ])
            ->assertJsonPath('data.recent_settlements', []);

        // The equal split: Asha (lowest id) takes the leftover paisa.
        $dinner = $this->getJson("/api/v1/groups/{$id}/expenses")->assertOk()->json('data.1');
        $this->assertSame('equal', $dinner['split']);
        $this->assertSame([
            ['user_id' => $this->asha->id, 'name' => 'Asha Rao', 'amount_minor' => 334],
            ['user_id' => $this->rishi->id, 'name' => 'Rishi Kapoor', 'amount_minor' => 333],
            ['user_id' => $this->meera->id, 'name' => 'Meera Iyer', 'amount_minor' => 333],
        ], $dinner['shares']);
        $this->assertSame(334, $dinner['my_share_minor']);

        // Everyone's list shows their own side.
        $this->actAs($this->rishi)->getJson('/api/v1/groups')->assertJsonPath('data.0.my_balance_minor', -32);
        $this->actAs($this->meera)->getJson('/api/v1/groups')->assertJsonPath('data.0.my_balance_minor', -534);
    }

    public function test_an_expense_has_the_shape_the_app_reads(): void
    {
        $id = $this->trio();
        $this->addExpense($id, [
            'description' => 'Cab to airport', 'amount_minor' => 120000, 'paid_by' => $this->rishi->id, 'spent_on' => '2026-10-21',
            'split' => 'equal', 'participants' => [$this->asha->id, $this->rishi->id, $this->meera->id],
        ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'description', 'amount_minor', 'paid_by', 'paid_by_name', 'spent_on', 'split', 'shares' => [['user_id', 'name', 'amount_minor']], 'my_share_minor', 'created_by', 'created_at']])
            ->assertJsonPath('data.description', 'Cab to airport')
            ->assertJsonPath('data.amount_minor', 120000)
            ->assertJsonPath('data.paid_by', $this->rishi->id)
            ->assertJsonPath('data.paid_by_name', 'Rishi Kapoor')
            ->assertJsonPath('data.spent_on', '2026-10-21')
            ->assertJsonPath('data.my_share_minor', 40000)
            ->assertJsonPath('data.created_by', $this->asha->id);
    }

    public function test_an_exact_split_must_add_up_to_the_amount(): void
    {
        $id = $this->trio();
        $shares = fn (int $a, int $r) => [['user_id' => $this->asha->id, 'amount_minor' => $a], ['user_id' => $this->rishi->id, 'amount_minor' => $r]];

        $this->addExpense($id, ['amount_minor' => 501, 'paid_by' => $this->asha->id, 'split' => 'exact', 'shares' => $shares(300, 200)])
            ->assertStatus(422)
            ->assertJsonPath('errors.shares.0', 'The shares add up to ₹5.00, but the expense is ₹5.01.');
        $this->addExpense($id, ['amount_minor' => 500, 'paid_by' => $this->asha->id, 'split' => 'exact', 'shares' => $shares(-100, 600)])
            ->assertStatus(422);
        $this->addExpense($id, ['amount_minor' => 500, 'paid_by' => $this->asha->id, 'split' => 'exact', 'shares' => [
            ['user_id' => $this->rishi->id, 'amount_minor' => 250], ['user_id' => $this->rishi->id, 'amount_minor' => 250],
        ]])->assertStatus(422)->assertJsonPath('errors', fn ($errors) => isset($errors['shares.1.user_id']));
        $this->addExpense($id, ['amount_minor' => 500, 'paid_by' => $this->asha->id, 'split' => 'exact'])
            ->assertStatus(422)->assertJsonPath('errors.shares.0', 'Give each person their share.');

        // A zero share just means "not in this one".
        $this->addExpense($id, ['amount_minor' => 500, 'paid_by' => $this->asha->id, 'split' => 'exact', 'shares' => array_merge($shares(500, 0), [['user_id' => $this->meera->id, 'amount_minor' => 0]])])
            ->assertCreated()
            ->assertJsonPath('data.shares', [['user_id' => $this->asha->id, 'name' => 'Asha Rao', 'amount_minor' => 500]]);
        $this->assertDatabaseCount('group_expenses', 1);
    }

    public function test_the_payer_and_everyone_sharing_must_be_in_the_group(): void
    {
        $id = $this->trio();
        $outsider = $this->makeUser(['email' => 'out@example.com']);

        $this->addExpense($id, ['amount_minor' => 900, 'paid_by' => $outsider->id, 'split' => 'equal', 'participants' => [$this->asha->id]])
            ->assertStatus(422)->assertJsonPath('errors.paid_by.0', 'Pick who paid — someone in this group.');
        $this->addExpense($id, ['amount_minor' => 900, 'paid_by' => $this->asha->id, 'split' => 'equal', 'participants' => [$this->asha->id, $outsider->id]])
            ->assertStatus(422)->assertJsonPath('errors.participants.0', 'Everyone sharing the expense must be in this group.');
        $this->addExpense($id, ['amount_minor' => 900, 'paid_by' => $this->asha->id, 'split' => 'exact', 'shares' => [['user_id' => $outsider->id, 'amount_minor' => 900]]])
            ->assertStatus(422)->assertJsonPath('errors.shares.0', 'Everyone sharing the expense must be in this group.');
        $this->addExpense($id, ['amount_minor' => 900, 'paid_by' => $this->asha->id, 'split' => 'equal', 'participants' => []])
            ->assertStatus(422)->assertJsonPath('errors.participants.0', 'Pick at least one person to split it with.');
        $this->addExpense($id, ['amount_minor' => 0, 'paid_by' => $this->asha->id, 'split' => 'equal', 'participants' => [$this->asha->id]])
            ->assertStatus(422)->assertJsonPath('errors.amount_minor.0', 'Enter an amount greater than zero.');
        $this->addExpense($id, ['amount_minor' => 900, 'paid_by' => $this->asha->id, 'split' => 'half', 'participants' => [$this->asha->id]])
            ->assertStatus(422)->assertJsonPath('errors.split.0', 'Split it equally or by exact amounts.');
        $this->addExpense($id, ['amount_minor' => 900, 'paid_by' => $this->asha->id, 'split' => 'equal', 'participants' => [$this->asha->id], 'spent_on' => '2099-01-01'])
            ->assertStatus(422)->assertJsonPath('errors.spent_on.0', 'Pick a day that is not in the future.');

        $this->assertDatabaseCount('group_expenses', 0);
    }

    public function test_only_members_see_a_group(): void
    {
        $id = $this->trio();
        $expense = $this->addExpense($id, ['amount_minor' => 900, 'paid_by' => $this->asha->id, 'split' => 'equal', 'participants' => [$this->asha->id, $this->rishi->id]])->json('data.id');
        $this->actAs($this->makeUser(['email' => 'out@example.com']));

        $this->getJson('/api/v1/groups')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/groups/{$id}")->assertNotFound();
        $this->putJson("/api/v1/groups/{$id}", ['name' => 'Mine now'])->assertNotFound();
        $this->deleteJson("/api/v1/groups/{$id}")->assertNotFound();
        $this->getJson("/api/v1/groups/{$id}/expenses")->assertNotFound();
        $this->postJson("/api/v1/groups/{$id}/expenses", ['description' => 'x', 'amount_minor' => 100])->assertNotFound();
        $this->putJson("/api/v1/groups/{$id}/expenses/{$expense}", [])->assertNotFound();
        $this->deleteJson("/api/v1/groups/{$id}/expenses/{$expense}")->assertNotFound();
        $this->postJson("/api/v1/groups/{$id}/members", ['email' => 'out@example.com'])->assertNotFound();
        $this->deleteJson("/api/v1/groups/{$id}/members/{$this->rishi->id}")->assertNotFound();
        $this->postJson("/api/v1/groups/{$id}/settlements", ['from_user_id' => $this->rishi->id, 'to_user_id' => $this->asha->id, 'amount_minor' => 100])->assertNotFound();
        $this->getJson('/api/v1/groups/999999')->assertNotFound();

        // An expense reached through another group of the member's is not found either.
        $this->actAs($this->asha);
        $other = $this->postJson('/api/v1/groups', ['name' => 'Office'])->json('data.id');
        $this->deleteJson("/api/v1/groups/{$other}/expenses/{$expense}")->assertNotFound();
        $this->assertDatabaseHas('group_expenses', ['id' => $expense]);
    }

    public function test_people_are_added_by_the_email_of_their_account(): void
    {
        $this->actAs($this->asha);
        $id = $this->postJson('/api/v1/groups', ['name' => 'Goa trip', 'emoji' => '🏖️'])->json('data.id');

        $this->postJson("/api/v1/groups/{$id}/members", ['email' => 'nobody@example.com'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'No Expense Tracker account uses that email — ask them to sign up first.');
        $this->postJson("/api/v1/groups/{$id}/members", ['email' => 'not an email'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'Enter the email address of the person to add.');

        $this->postJson("/api/v1/groups/{$id}/members", ['email' => ' Rishi@Example.com '])
            ->assertOk()
            ->assertJsonPath('data.member_count', 2)
            ->assertJsonPath('data.members.1.user_id', $this->rishi->id)
            ->assertJsonPath('data.suggested_settlements', []);
        $this->postJson("/api/v1/groups/{$id}/members", ['email' => 'rishi@example.com'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'Rishi Kapoor is already in this group.');

        Notification::assertSentTo($this->rishi, AddedToGroup::class, function (AddedToGroup $mail) use ($id) {
            $html = (string) $mail->toMail($this->rishi)->render();

            return $mail->groupLabel === '🏖️ Goa trip' && $mail->addedBy === 'Asha Rao'
                && str_contains($html, "/groups/{$id}") && str_contains($html, 'Asha Rao added you to');
        });

        // Any member can add people.
        $this->actAs($this->rishi)->postJson("/api/v1/groups/{$id}/members", ['email' => 'meera@example.com'])->assertOk()->assertJsonPath('data.member_count', 3);
        $this->actAs($this->meera)->getJson("/api/v1/groups/{$id}")->assertOk();
    }

    public function test_nobody_leaves_or_is_removed_while_owing_or_owed(): void
    {
        $id = $this->trio();
        $this->addExpense($id, ['amount_minor' => 1000, 'paid_by' => $this->asha->id, 'split' => 'equal', 'participants' => [$this->asha->id, $this->rishi->id]]);

        $this->deleteJson("/api/v1/groups/{$id}/members/{$this->rishi->id}")
            ->assertStatus(422)->assertJsonPath('errors.member.0', "Rishi Kapoor can't be removed while they owe ₹5.00 — settle up first.");
        $this->deleteJson("/api/v1/groups/{$id}/members/{$this->asha->id}")
            ->assertStatus(422)->assertJsonPath('errors.member.0', "You can't leave while you are owed ₹5.00 — settle up first.");
        $this->actAs($this->rishi)->deleteJson("/api/v1/groups/{$id}/members/{$this->rishi->id}")
            ->assertStatus(422)->assertJsonPath('errors.member.0', "You can't leave while you owe ₹5.00 — settle up first.");

        // Meera owes nothing: removing her returns the group.
        $this->actAs($this->asha)->deleteJson("/api/v1/groups/{$id}/members/{$this->meera->id}")
            ->assertOk()->assertJsonPath('data.member_count', 2);
        $this->actAs($this->meera)->getJson("/api/v1/groups/{$id}")->assertNotFound();
        $this->actAs($this->asha)->deleteJson("/api/v1/groups/{$id}/members/{$this->meera->id}")->assertNotFound();

        // Settled, Rishi can leave: 204, and the group is gone from his list.
        $this->actAs($this->rishi)->postJson("/api/v1/groups/{$id}/settlements", ['from_user_id' => $this->rishi->id, 'to_user_id' => $this->asha->id, 'amount_minor' => 500])->assertCreated();
        $this->deleteJson("/api/v1/groups/{$id}/members/{$this->rishi->id}")->assertNoContent();
        $this->getJson('/api/v1/groups')->assertJsonCount(0, 'data');

        // The last one out takes the group with them.
        $this->actAs($this->asha)->deleteJson("/api/v1/groups/{$id}/members/{$this->asha->id}")->assertNoContent();
        $this->assertDatabaseMissing('groups', ['id' => $id]);
        $this->assertDatabaseCount('group_expenses', 0);
    }

    public function test_a_settlement_changes_the_balances(): void
    {
        $id = $this->trio();
        $this->unevenPaise($id);

        $this->actAs($this->meera)->postJson("/api/v1/groups/{$id}/settlements", ['from_user_id' => $this->meera->id, 'to_user_id' => $this->asha->id, 'amount_minor' => 534])
            ->assertCreated()
            ->assertJsonPath('data.my_balance_minor', 0)
            ->assertJsonPath('data.suggested_settlements', [
                ['from_user_id' => $this->rishi->id, 'from_name' => 'Rishi Kapoor', 'to_user_id' => $this->asha->id, 'to_name' => 'Asha Rao', 'amount_minor' => 32],
            ])
            ->assertJsonPath('data.recent_settlements.0.from_user_id', $this->meera->id)
            ->assertJsonPath('data.recent_settlements.0.to_user_id', $this->asha->id)
            ->assertJsonPath('data.recent_settlements.0.amount_minor', 534)
            ->assertJsonPath('data.recent_settlements.0.on', Labels::now()->toDateString());
        $this->assertSame([$this->asha->id => 32, $this->rishi->id => -32, $this->meera->id => 0], $this->balances($id));

        // Anyone may record anyone's payment, on a given day.
        $this->actAs($this->meera)->postJson("/api/v1/groups/{$id}/settlements", ['from_user_id' => $this->rishi->id, 'to_user_id' => $this->asha->id, 'amount_minor' => 32, 'on' => '2026-10-25'])
            ->assertCreated()->assertJsonPath('data.suggested_settlements', [])
            // Newest day first, so the back-dated one comes second.
            ->assertJsonPath('data.recent_settlements.1.on', '2026-10-25')
            ->assertJsonPath('data.recent_settlements.1.from_name', 'Rishi Kapoor');
        $this->assertSame([$this->asha->id => 0, $this->rishi->id => 0, $this->meera->id => 0], $this->balances($id));

        $outsider = $this->makeUser(['email' => 'out@example.com']);
        $message = 'Pick who paid and who was paid — two different people in this group.';
        $this->postJson("/api/v1/groups/{$id}/settlements", ['from_user_id' => $this->asha->id, 'to_user_id' => $this->asha->id, 'amount_minor' => 100])
            ->assertStatus(422)->assertJsonPath('errors.from_user_id.0', $message);
        $this->postJson("/api/v1/groups/{$id}/settlements", ['from_user_id' => $outsider->id, 'to_user_id' => $this->asha->id, 'amount_minor' => 100])
            ->assertStatus(422)->assertJsonPath('errors.from_user_id.0', $message);
        $this->postJson("/api/v1/groups/{$id}/settlements", ['from_user_id' => $this->rishi->id, 'to_user_id' => $this->asha->id, 'amount_minor' => 0])
            ->assertStatus(422)->assertJsonPath('errors.amount_minor.0', 'Enter an amount greater than zero.');
        $this->assertDatabaseCount('group_settlements', 2);
    }

    public function test_a_recorded_payment_can_be_undone_by_any_member(): void
    {
        $id = $this->trio();
        $this->addExpense($id, ['amount_minor' => 900, 'paid_by' => $this->asha->id, 'split' => 'equal',
            'participants' => [$this->asha->id, $this->rishi->id, $this->meera->id]])->assertCreated();
        $payment = $this->actAs($this->rishi)->postJson("/api/v1/groups/{$id}/settlements", ['from_user_id' => $this->rishi->id, 'to_user_id' => $this->asha->id, 'amount_minor' => 300])
            ->assertCreated()->json('data.recent_settlements.0.id');
        $this->assertSame([$this->asha->id => 300, $this->rishi->id => 0, $this->meera->id => -300], $this->balances($id));

        // Meera, who did not record it, takes it back: the group comes back as it was without it.
        $this->actAs($this->meera)->deleteJson("/api/v1/groups/{$id}/settlements/{$payment}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.recent_settlements', [])
            ->assertJsonPath('data.my_balance_minor', -300);
        $this->assertSame([$this->asha->id => 600, $this->rishi->id => -300, $this->meera->id => -300], $this->balances($id));
        $this->deleteJson("/api/v1/groups/{$id}/settlements/{$payment}")->assertNotFound();

        // Only a payment of this group, and only for its members.
        $this->actAs($this->asha);
        $other = $this->postJson('/api/v1/groups', ['name' => 'Office'])->json('data.id');
        $this->postJson("/api/v1/groups/{$other}/members", ['email' => 'rishi@example.com'])->assertOk();
        $theirs = $this->postJson("/api/v1/groups/{$other}/settlements", ['from_user_id' => $this->rishi->id, 'to_user_id' => $this->asha->id, 'amount_minor' => 100])
            ->json('data.recent_settlements.0.id');
        $this->deleteJson("/api/v1/groups/{$id}/settlements/{$theirs}")->assertNotFound();
        $this->actAs($this->meera)->deleteJson("/api/v1/groups/{$other}/settlements/{$theirs}")->assertNotFound();
        $this->assertDatabaseHas('group_settlements', ['id' => $theirs]);
    }

    public function test_a_member_with_no_phone_is_emailed_instead_of_pushed(): void
    {
        $this->fakeFirebase();
        $this->phone($this->rishi, 'rishi-phone'); // Rishi has the app signed in; Meera does not
        $id = $this->trio();

        $this->addExpense($id, ['amount_minor' => 600000, 'paid_by' => $this->asha->id, 'split' => 'equal',
            'participants' => [$this->asha->id, $this->rishi->id, $this->meera->id]])->assertCreated();

        // Rishi: the push only.
        $this->assertSame(['title' => '🏖️ Goa trip', 'body' => "Asha added 'Dinner' ₹6,000.00 in Goa trip — your share ₹2,000.00"], last($this->pushes()));
        Notification::assertNotSentTo($this->rishi, GroupActivity::class);
        // Meera: the same news by email, with a way into the group.
        Notification::assertSentTo($this->meera, GroupActivity::class, function (GroupActivity $mail) use ($id) {
            $html = (string) $mail->toMail($this->meera)->render();

            return $mail->subject === "🏖️ Goa trip — Asha added 'Dinner' ₹6,000.00"
                && $mail->lines === ['Your share ₹2,000.00']
                && str_contains($html, 'Your share ₹2,000.00') && str_contains($html, 'Open the group') && str_contains($html, "/groups/{$id}");
        });

        // A payment recorded for Meera reaches her by email too.
        $this->postJson("/api/v1/groups/{$id}/settlements", ['from_user_id' => $this->meera->id, 'to_user_id' => $this->asha->id, 'amount_minor' => 200000, 'on' => '2026-10-25'])->assertCreated();
        Notification::assertSentTo($this->meera, GroupActivity::class, fn (GroupActivity $mail) => $mail->subject === '🏖️ Goa trip — Asha recorded that you paid Asha Rao ₹2,000.00'
            && $mail->lines === ['On 25 Oct 2026']);
        Notification::assertSentToTimes($this->meera, GroupActivity::class, 2);
        Notification::assertNotSentTo($this->rishi, GroupActivity::class);
    }

    public function test_a_member_with_emails_off_and_no_phone_gets_neither(): void
    {
        $this->fakeFirebase();
        $this->meera->update(['email_notifications' => false]);
        $id = $this->trio();

        $this->addExpense($id, ['amount_minor' => 900, 'paid_by' => $this->asha->id, 'split' => 'equal',
            'participants' => [$this->asha->id, $this->meera->id]])->assertCreated();

        Notification::assertNotSentTo($this->meera, GroupActivity::class);
        Notification::assertNotSentTo($this->meera, AddedToGroup::class);
        $this->assertSame([], $this->pushes());
    }

    public function test_deleting_an_account_takes_them_out_of_their_groups_and_removes_the_empty_ones(): void
    {
        $shared = $this->trio();
        $this->actAs($this->meera);
        $solo = $this->postJson('/api/v1/groups', ['name' => 'Just me'])->json('data.id');
        $this->addExpense($solo, ['amount_minor' => 500, 'paid_by' => $this->meera->id, 'split' => 'equal', 'participants' => [$this->meera->id]])->assertCreated();

        \App\Support\Accounts::delete($this->meera);

        $this->assertDatabaseMissing('group_members', ['user_id' => $this->meera->id]);
        $this->assertDatabaseMissing('groups', ['id' => $solo]);
        $this->assertDatabaseMissing('group_expenses', ['group_id' => $solo]);
        $this->actAs($this->asha)->getJson("/api/v1/groups/{$shared}")->assertOk()->assertJsonPath('data.member_count', 2);
    }

    public function test_the_others_are_pushed_when_added_and_when_an_expense_involves_them(): void
    {
        $this->fakeFirebase();
        $this->phone($this->asha, 'asha-phone');
        $this->phone($this->rishi, 'rishi-phone');
        $this->phone($this->meera, 'meera-phone');

        $id = $this->trio();
        $this->assertSame([
            ['title' => 'Asha added you to 🏖️ Goa trip', 'body' => 'Split expenses and see who owes whom.'],
            ['title' => 'Asha added you to 🏖️ Goa trip', 'body' => 'Split expenses with 2 others and see who owes whom.'],
        ], $this->pushes());

        $this->addExpense($id, ['amount_minor' => 120000, 'paid_by' => $this->asha->id, 'split' => 'equal',
            'participants' => [$this->asha->id, $this->rishi->id, $this->meera->id]])->assertCreated();
        $this->assertSame([
            ['title' => '🏖️ Goa trip', 'body' => "Asha added 'Dinner' ₹1,200.00 in Goa trip — your share ₹400.00"],
            ['title' => '🏖️ Goa trip', 'body' => "Asha added 'Dinner' ₹1,200.00 in Goa trip — your share ₹400.00"],
        ], array_slice($this->pushes(), 2));

        // Only those on it; the payer hears it was them.
        $this->addExpense($id, ['description' => 'Cab', 'amount_minor' => 60000, 'paid_by' => $this->rishi->id, 'split' => 'exact',
            'shares' => [['user_id' => $this->asha->id, 'amount_minor' => 60000]]])->assertCreated();
        $this->assertSame([
            ['title' => '🏖️ Goa trip', 'body' => "Asha added 'Cab' ₹600.00 in Goa trip — paid by you"],
        ], array_slice($this->pushes(), 4));

        // Editing does not push again; the pushes carry what the app needs to open the group.
        $expense = $this->getJson("/api/v1/groups/{$id}/expenses")->json('data.0.id');
        $this->putJson("/api/v1/groups/{$id}/expenses/{$expense}", ['description' => 'Cab', 'amount_minor' => 50000, 'paid_by' => $this->rishi->id,
            'spent_on' => '2026-10-20', 'split' => 'equal', 'participants' => [$this->asha->id, $this->rishi->id]])->assertOk();
        $this->assertCount(5, $this->pushes());

        $data = collect(\Illuminate\Support\Facades\Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn ($r) => str_contains($r->url(), 'messages:send'))->map(fn ($r) => $r['message']['data'])->unique()->values()->all();
        $this->assertSame([['type' => 'group', 'group_id' => (string) $id]], $data);
    }

    public function test_expenses_are_listed_newest_first_and_any_member_can_edit_or_delete_them(): void
    {
        $id = $this->trio();
        $old = $this->addExpense($id, ['description' => 'Old', 'spent_on' => '2026-10-01', 'amount_minor' => 300, 'paid_by' => $this->asha->id, 'split' => 'equal', 'participants' => [$this->asha->id, $this->rishi->id, $this->meera->id]])->json('data.id');
        $new = $this->addExpense($id, ['description' => 'New', 'spent_on' => '2026-10-15', 'amount_minor' => 300, 'paid_by' => $this->asha->id, 'split' => 'equal', 'participants' => [$this->asha->id, $this->rishi->id, $this->meera->id]])->json('data.id');
        $this->getJson("/api/v1/groups/{$id}/expenses")->assertOk()->assertJsonPath('data.0.id', $new)->assertJsonPath('data.1.id', $old);

        $this->actAs($this->meera)->putJson("/api/v1/groups/{$id}/expenses/{$old}", [
            'description' => 'Old, fixed', 'amount_minor' => 450, 'paid_by' => $this->meera->id, 'spent_on' => '2026-10-02', 'split' => 'exact',
            'shares' => [['user_id' => $this->asha->id, 'amount_minor' => 450]],
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Old, fixed')
            ->assertJsonPath('data.split', 'exact')
            ->assertJsonPath('data.paid_by_name', 'Meera Iyer')
            ->assertJsonPath('data.my_share_minor', 0)
            ->assertJsonPath('data.shares', [['user_id' => $this->asha->id, 'name' => 'Asha Rao', 'amount_minor' => 450]]);
        $this->assertDatabaseCount('group_expense_shares', 4);

        $this->deleteJson("/api/v1/groups/{$id}/expenses/{$new}")->assertNoContent();
        $this->assertDatabaseMissing('group_expense_shares', ['group_expense_id' => $new]);
        $this->assertSame([$this->asha->id => -450, $this->rishi->id => 0, $this->meera->id => 450], $this->balances($id));
    }

    public function test_only_the_creator_deletes_the_group(): void
    {
        $id = $this->trio();
        $this->unevenPaise($id);

        $this->actAs($this->rishi)->deleteJson("/api/v1/groups/{$id}")
            ->assertForbidden()->assertJsonPath('message', 'Only Asha Rao, who made this group, can delete it.');

        $this->actAs($this->asha)->deleteJson("/api/v1/groups/{$id}")->assertNoContent();
        foreach (['groups', 'group_members', 'group_expenses', 'group_expense_shares', 'group_settlements'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_a_deleted_account_stays_in_the_ledger_as_a_former_member(): void
    {
        $id = $this->trio();
        $this->unevenPaise($id);
        $meeraId = $this->meera->id;

        \App\Support\Accounts::delete($this->meera);

        $this->actAs($this->asha)->getJson("/api/v1/groups/{$id}")
            ->assertOk()
            ->assertJsonPath('data.member_count', 2)
            ->assertJsonPath('data.my_balance_minor', 566)
            ->assertJsonPath('data.suggested_settlements.0.from_user_id', $meeraId)
            ->assertJsonPath('data.suggested_settlements.0.from_name', Groups::FORMER_MEMBER);
        $this->getJson("/api/v1/groups/{$id}/expenses")->assertOk()->assertJsonPath('data.1.shares.2.name', Groups::FORMER_MEMBER);

        // Their debt can still be recorded as paid.
        $this->postJson("/api/v1/groups/{$id}/settlements", ['from_user_id' => $meeraId, 'to_user_id' => $this->asha->id, 'amount_minor' => 534])->assertCreated();
    }
}
