<?php

namespace Tests\Feature\Tracker;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Category;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The website's expense-tracker pages, signed in through the session. */
abstract class TrackerTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = $this->makeUser();
        $this->actingAs($this->user);
    }

    protected function makeUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['name' => 'Asha Rao', 'currency' => 'INR', 'theme_mode' => 'light', 'onboarded' => true]);
        DefaultCategories::seed($user);

        return $user;
    }

    protected function category(string $name = 'Transport', ?User $user = null): Category
    {
        return Category::where('user_id', ($user ?? $this->user)->id)->where('name', $name)->firstOrFail();
    }

    protected function expense(int $amount, string $category = 'Transport', ?string $day = null, array $extra = []): AppExpense
    {
        return AppExpense::create($extra + [
            'user_id' => $this->user->id, 'amount_minor' => $amount,
            'category_id' => $this->category($category)->id, 'spent_at' => $day ?? now()->toDateString(),
        ]);
    }

    protected function income(int $amount, string $source = 'Salary', ?string $day = null, array $extra = []): AppIncome
    {
        return AppIncome::create($extra + [
            'user_id' => $this->user->id, 'amount_minor' => $amount, 'source' => $source,
            'received_at' => $day ?? now()->toDateString(),
        ]);
    }
}
