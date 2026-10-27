<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    /** A user with the default categories, signed in for the requests that follow. */
    protected function signIn(array $attributes = []): User
    {
        $user = $this->makeUser($attributes);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function makeUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['currency' => 'INR', 'theme_mode' => 'light', 'onboarded' => true]);
        DefaultCategories::seed($user);

        return $user;
    }

    protected function category(User $user, string $name = 'Transport'): Category
    {
        return Category::where('user_id', $user->id)->where('name', $name)->firstOrFail();
    }
}
