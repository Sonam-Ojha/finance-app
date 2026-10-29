<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppExpense;

/** The per-minute limits on the API's costly or guessable endpoints. */
class RateLimitsTest extends ApiTestCase
{
    /** `$limit` requests get through (whatever they answer), the next is 429. */
    private function assertLimited(int $limit, callable $send): void
    {
        for ($i = 1; $i <= $limit; $i++) {
            $this->assertNotSame(429, $send()->status(), "request {$i} of {$limit} was throttled");
        }
        $send()->assertStatus(429);
    }

    public function test_reset_password_allows_five_tries_a_minute(): void
    {
        $this->assertLimited(5, fn () => $this->postJson('/api/v1/reset-password', ['email' => 'me@example.com', 'token' => 'guess', 'password' => 'newpass123']));
    }

    public function test_import_allows_five_a_minute(): void
    {
        $this->signIn();
        $this->assertLimited(5, fn () => $this->postJson('/api/v1/import', []));
    }

    public function test_avatar_uploads_allow_ten_a_minute(): void
    {
        $this->signIn();
        $this->assertLimited(10, fn () => $this->postJson('/api/v1/me/avatar', ['avatar' => 'not-an-image']));
    }

    public function test_receipt_uploads_allow_twenty_a_minute(): void
    {
        $user = $this->signIn();
        $expense = AppExpense::create([
            'user_id' => $user->id, 'amount_minor' => 1000,
            'category_id' => $this->category($user)->id, 'spent_at' => now()->toDateString(),
        ]);

        $this->assertLimited(20, fn () => $this->postJson("/api/v1/expenses/{$expense->id}/receipt", ['receipt' => 'not-an-image']));
    }
}
