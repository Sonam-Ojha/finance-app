<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Support\Money;
use Illuminate\Support\Facades\Notification;

class ActivityMailTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function assertMailed(User $user, string $subject, array $details = []): void
    {
        Notification::assertSentTo($user, ActivityNotification::class, function (ActivityNotification $n) use ($subject, $details) {
            return $n->subject === $subject && array_intersect_assoc($details, $n->details) === $details;
        });
    }

    public function test_each_thing_added_sends_one_email(): void
    {
        $user = $this->signIn(['name' => 'Rishi']);
        $transport = $this->category($user);

        $this->postJson('/api/v1/expenses', ['amount_minor' => 54444700, 'category_id' => $transport->id, 'note' => 'Bike', 'payment_method' => 'upi', 'spent_at' => '2026-10-27'])->assertCreated();
        $this->assertMailed($user, 'Expense added: ₹5,44,447.00', [
            'Amount' => '₹5,44,447.00', 'Category' => '🚗 Transport', 'Date' => '27 Oct 2026', 'Paid with' => 'UPI', 'Note' => 'Bike',
        ]);

        $this->postJson('/api/v1/incomes', ['amount_minor' => 5000000, 'source' => 'Salary', 'received_at' => '2026-10-01'])->assertCreated();
        $this->assertMailed($user, 'Income added: ₹50,000.00', ['Source' => 'Salary']);

        $this->postJson('/api/v1/categories', ['name' => 'Pets', 'icon' => '🐾', 'color' => '#AABBCC', 'budget_minor' => 300000])->assertCreated();
        $this->assertMailed($user, 'New category: Pets', ['Category' => '🐾 Pets', 'Monthly budget' => '₹3,000.00']);

        $this->postJson('/api/v1/budgets', ['month' => '2026-10', 'amount_minor' => 2000000])->assertCreated();
        $this->assertMailed($user, 'Budget set for October 2026', ['For' => 'All spending', 'Amount' => '₹20,000.00']);

        $this->putJson('/api/v1/me/currencies/THB', ['rate' => 2.31, 'label' => 'Thai Baht', 'symbol' => '฿'])->assertOk();
        $this->assertMailed($user, 'Currency added: THB', ['Rate' => '1 THB = ₹2.31']);

        $this->putJson('/api/v1/settings/currency', ['currency' => 'USD'])->assertNoContent();
        $this->assertMailed($user, 'Currency changed to USD', ['From' => 'INR', 'To' => 'USD']);

        Notification::assertSentToTimes($user, ActivityNotification::class, 6);
    }

    public function test_empty_details_are_left_out_of_the_email(): void
    {
        $user = $this->signIn();
        $this->postJson('/api/v1/expenses', ['amount_minor' => 100, 'category_id' => $this->category($user)->id, 'spent_at' => '2026-10-27']);

        Notification::assertSentTo($user, ActivityNotification::class, function (ActivityNotification $n) {
            return ! array_key_exists('Note', $n->details) && ! array_key_exists('Paid with', $n->details);
        });
    }

    public function test_edits_deletes_and_repeats_send_nothing(): void
    {
        $user = $this->signIn();
        $categoryId = $this->category($user)->id;
        $body = ['amount_minor' => 100, 'category_id' => $categoryId, 'spent_at' => '2026-10-27'];
        $id = $this->postJson('/api/v1/expenses', $body)->json('data.id');

        $this->putJson("/api/v1/expenses/{$id}", ['amount_minor' => 200] + $body);
        $this->deleteJson("/api/v1/expenses/{$id}");
        // Updating a rate, and choosing the currency already chosen.
        $this->putJson('/api/v1/me/currencies/USD', ['rate' => 83]);
        $this->putJson('/api/v1/me/currencies/USD', ['rate' => 84]);
        $this->putJson('/api/v1/settings/currency', ['currency' => 'INR']);

        Notification::assertSentToTimes($user, ActivityNotification::class, 1);
    }

    public function test_nothing_is_sent_when_turned_off(): void
    {
        $user = $this->signIn(['email_notifications' => false]);
        $this->postJson('/api/v1/incomes', ['amount_minor' => 100, 'source' => 'Gift', 'received_at' => '2026-10-27'])->assertCreated();
        Notification::assertNothingSentTo($user);

        $user->update(['email_notifications' => true]);
        config(['mail.activity_notifications' => false]);
        $this->postJson('/api/v1/incomes', ['amount_minor' => 100, 'source' => 'Gift', 'received_at' => '2026-10-27'])->assertCreated();
        Notification::assertNothingSentTo($user);
    }

    public function test_the_email_reads_well(): void
    {
        $user = User::factory()->make(['name' => 'Rishi']);
        $mail = (new ActivityNotification('Expense added: ₹45.00', 'You added an expense.', [
            'Amount' => '₹45.00',
            'Note'   => '<b>lunch</b> & tea',
        ]))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Expense added: ₹45.00', $mail->subject);
        $this->assertStringContainsString('Hi Rishi,', $html);
        $this->assertStringContainsString('You added an expense.', $html);
        $this->assertMatchesRegularExpression('#<strong[^>]*>Amount:</strong> ₹45\.00#u', $html);
        // What the user typed is shown, never run as HTML.
        $this->assertStringContainsString('&lt;b&gt;lunch&lt;/b&gt; &amp; tea', $html);
        // The app's own name, whatever the server's APP_NAME is — which only
        // survives in the <title>, a tag mail apps do not show.
        $visible = preg_replace('#<title>.*?</title>#s', '', $html);
        $this->assertStringContainsString('Expense Tracker', $visible);
        $this->assertStringNotContainsString('Laravel', $visible);

        $anonymous = (string) (new ActivityNotification('s', 'h', []))->toMail(User::factory()->make(['name' => '']))->render();
        $this->assertStringContainsString('Hi,', $anonymous);
    }

    public function test_rupees_are_grouped_the_indian_way(): void
    {
        $this->assertSame('₹0.00', Money::inr(0));
        $this->assertSame('₹45.05', Money::inr(4505));
        $this->assertSame('₹999.99', Money::inr(99999));
        $this->assertSame('₹1,000.00', Money::inr(100000));
        $this->assertSame('₹5,44,447.00', Money::inr(54444700));
        $this->assertSame('₹12,34,56,789.10', Money::inr(12345678910));
    }
}
