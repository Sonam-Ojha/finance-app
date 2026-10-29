<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\DefaultCategory;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Admin › Default categories, and the categories new accounts start with. */
class DefaultCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = tap(User::factory()->create())->forceFill(['role' => User::ROLE_ADMIN]);
        $this->admin->save();
    }

    public function test_the_table_starts_with_the_eight_that_were_hardcoded(): void
    {
        $this->assertSame(
            ['Food & Drink', 'Groceries', 'Transport', 'Shopping', 'Bills & Utilities', 'Health', 'Entertainment', 'Other'],
            DefaultCategory::ordered()->pluck('name')->all(),
        );
        $this->assertSame(array_column(DefaultCategories::FALLBACK, 'name'), array_column(DefaultCategories::list(), 'name'));
    }

    public function test_only_admins_reach_the_page(): void
    {
        $this->get('/admin/default-categories')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/default-categories')->assertForbidden();
        $this->actingAs($user)->post('/admin/default-categories', ['name' => 'Rent', 'icon' => '🏠', 'color' => '#228085'])->assertForbidden();

        $this->actingAs($this->admin)->get('/admin/default-categories')->assertOk()
            ->assertSee('Default categories')
            ->assertSee('New sign-ups only.')
            ->assertSee('Groceries')
            ->assertSee('Add a category');
    }

    public function test_add_edit_reorder_and_delete_reach_new_sign_ups_only(): void
    {
        $existing = User::factory()->create();
        DefaultCategories::seed($existing);

        $this->actingAs($this->admin)->post('/admin/default-categories', [
            'name' => '  Rent ', 'icon' => '🏠', 'color' => '#228085', 'budget' => '15,000.50',
        ])->assertRedirect('/admin/default-categories')->assertSessionHas('success');
        $rent = DefaultCategory::where('name', 'Rent')->firstOrFail();
        $this->assertSame(1500050, $rent->budget_minor);
        $this->assertSame(8, $rent->sort_order);

        // Up one place, then edit, then delete another.
        $this->actingAs($this->admin)->patch("/admin/default-categories/{$rent->id}/move", ['direction' => 'up'])->assertRedirect();
        $this->assertSame('Rent', DefaultCategory::ordered()->get()[7]->name);
        $this->assertSame('Other', DefaultCategory::ordered()->get()[8]->name);

        $this->actingAs($this->admin)->get("/admin/default-categories/{$rent->id}/edit")->assertOk()->assertSee('Edit category')->assertSee('15000.5');
        $this->actingAs($this->admin)->put("/admin/default-categories/{$rent->id}", [
            'name' => 'Rent & EMI', 'icon' => '🔑', 'color' => '#b28b4e', 'budget' => '',
        ])->assertRedirect('/admin/default-categories');
        $this->assertDatabaseHas('default_categories', ['id' => $rent->id, 'name' => 'Rent & EMI', 'icon' => '🔑', 'color' => '#B28B4E', 'budget_minor' => null]);

        $health = DefaultCategory::where('name', 'Health')->firstOrFail();
        $this->actingAs($this->admin)->delete("/admin/default-categories/{$health->id}")->assertRedirect('/admin/default-categories');

        // A new account gets the new list, in order, with its budgets.
        $new = User::factory()->create();
        DefaultCategories::seed($new);
        $this->assertSame(
            ['Food & Drink', 'Groceries', 'Transport', 'Shopping', 'Bills & Utilities', 'Entertainment', 'Rent & EMI', 'Other'],
            Category::where('user_id', $new->id)->orderBy('sort_order')->pluck('name')->all(),
        );

        // Nobody's existing categories changed.
        $this->assertSame(8, Category::where('user_id', $existing->id)->count());
        $this->assertTrue(Category::where('user_id', $existing->id)->where('name', 'Health')->exists());

        $this->assertSame(
            ['default_category.added', 'default_category.moved', 'default_category.updated', 'default_category.deleted'],
            AdminActivityLog::orderBy('id')->pluck('action')->all(),
        );
        $this->assertSame('renamed the default category “Rent” to “Rent & EMI”', AdminActivityLog::where('action', 'default_category.updated')->value('description'));
    }

    public function test_a_starting_budget_is_copied_to_new_accounts(): void
    {
        $food = DefaultCategory::where('name', 'Food & Drink')->firstOrFail();
        $this->actingAs($this->admin)->put("/admin/default-categories/{$food->id}", [
            'name' => 'Food & Drink', 'icon' => '🍔', 'color' => '#FF6B6B', 'budget' => '5000',
        ])->assertSessionHasNoErrors();

        $new = User::factory()->create();
        DefaultCategories::seed($new);
        $this->assertSame(500000, Category::where('user_id', $new->id)->where('name', 'Food & Drink')->value('budget_minor'));
    }

    public function test_bad_input_is_refused(): void
    {
        $this->actingAs($this->admin)->post('/admin/default-categories', ['name' => 'Groceries', 'icon' => '', 'color' => 'blue', 'budget' => '0'])
            ->assertSessionHasErrors(['name', 'icon', 'color', 'budget']);
        $this->actingAs($this->admin)->post('/admin/default-categories', ['name' => 'Pets', 'icon' => '🐾', 'color' => '#915FC8', 'budget' => 'lots'])
            ->assertSessionHasErrors(['budget' => 'Enter the monthly budget in rupees, like 5000, or leave it empty.']);
        $this->assertSame(8, DefaultCategory::count());
    }

    public function test_the_last_category_cannot_be_deleted_and_the_ends_do_not_move(): void
    {
        $first = DefaultCategory::ordered()->first();
        $this->actingAs($this->admin)->patch("/admin/default-categories/{$first->id}/move", ['direction' => 'up'])->assertRedirect();
        $this->assertSame($first->id, DefaultCategory::ordered()->first()->id);

        DefaultCategory::whereKeyNot($first->id)->delete();
        $this->actingAs($this->admin)->delete("/admin/default-categories/{$first->id}")->assertSessionHas('error');
        $this->assertSame(1, DefaultCategory::count());
    }

    public function test_an_empty_or_missing_table_falls_back_to_the_built_in_list(): void
    {
        DefaultCategory::query()->delete();
        $user = User::factory()->create();
        DefaultCategories::seed($user);
        $this->assertSame(8, Category::where('user_id', $user->id)->count());

        Schema::drop('default_categories');
        $this->assertCount(8, DefaultCategories::list());
    }
}
