<?php

namespace Tests\Feature\Tracker;

use App\Models\Budget;
use App\Models\Category;

class CategoriesAndBudgetsTest extends TrackerTestCase
{
    public function test_categories_are_made_and_renamed_with_the_apps_rules(): void
    {
        $this->post('/categories', ['name' => '  Coffee ', 'icon' => '☕', 'color' => '#7e8acc', 'budget' => '1500'])
            ->assertRedirect('/categories')->assertSessionHas('success', 'Category “Coffee” created');
        $coffee = Category::where('user_id', $this->user->id)->where('name', 'Coffee')->firstOrFail();
        $this->assertSame(['☕', '#7E8ACC', 150000, 8], [$coffee->icon, $coffee->color, $coffee->budget_minor, $coffee->sort_order]);

        $this->post('/categories', ['name' => 'Coffee', 'icon' => '☕', 'color' => '#7E8ACC'])
            ->assertSessionHasErrors(['name' => 'A category with that name already exists.']);
        $this->post('/categories', ['name' => '', 'icon' => '☕', 'color' => '#7E8ACC', 'budget' => '0'])
            ->assertSessionHasErrors(['name' => 'Give the category a name.', 'budget' => 'Budget must be a number greater than zero, or left empty.']);

        $this->put("/categories/{$coffee->id}", ['name' => 'Cafe', 'icon' => '🍿', 'color' => '#DA2020', 'budget' => ''])
            ->assertSessionHas('success', 'Category “Cafe” updated');
        $this->assertSame(['Cafe', null], [$coffee->fresh()->name, $coffee->fresh()->budget_minor]);
    }

    public function test_a_category_made_from_the_expense_form_goes_back_to_it_picked(): void
    {
        $response = $this->post('/categories', ['name' => 'Pets', 'icon' => '🐾', 'color' => '#34A866', 'for' => 'expense']);
        $pets = Category::where('name', 'Pets')->firstOrFail();
        $response->assertRedirect("/expenses/create?category_id={$pets->id}");
    }

    public function test_deleting_a_category_refiles_its_expenses_under_other(): void
    {
        $transport = $this->category('Transport');
        $expense = $this->expense(5000, 'Transport');

        $this->get("/categories/{$transport->id}/edit")->assertSee('Its 1 expense will be moved to “Other”. Nothing is lost.');
        $this->delete("/categories/{$transport->id}")->assertSessionHas('success', 'Category “Transport” deleted');

        $this->assertModelMissing($transport);
        $this->assertSame($this->category('Other')->id, $expense->fresh()->category_id);
    }

    public function test_the_last_category_stays(): void
    {
        Category::where('user_id', $this->user->id)->where('name', '!=', 'Other')->delete();
        $other = $this->category('Other');

        $this->delete("/categories/{$other->id}")->assertSessionHas('error', 'You need at least one category — create another before deleting this one.');
        $this->assertModelExists($other);
    }

    public function test_month_budgets_are_created_changed_and_cleared(): void
    {
        $month = now()->format('Y-m');
        $food = $this->category('Food & Drink');
        $transport = $this->category('Transport');

        $this->put('/budgets', ['month' => $month, 'whole' => '20000', 'budgets' => [$food->id => '5000', $transport->id => '']])
            ->assertRedirect("/budgets?month={$month}")->assertSessionHas('success', 'Budgets for ' . now()->format('F Y') . ' saved');
        $this->assertSame(2, Budget::count());
        $this->assertSame(2000000, Budget::whereNull('category_id')->value('amount_minor'));

        $this->put('/budgets', ['month' => $month, 'whole' => '', 'budgets' => [$food->id => '6000']]);
        $this->assertSame(1, Budget::count());
        $this->assertSame(600000, Budget::where('category_id', $food->id)->value('amount_minor'));

        $this->put('/budgets', ['month' => $month, 'budgets' => [$food->id => 'lots']])
            ->assertSessionHasErrors(["budgets.{$food->id}" => 'Food & Drink needs a number greater than zero, or an empty field.']);

        // A month's own budget wins over the every-month one.
        $food->update(['budget_minor' => 100000]);
        $this->expense(30000, 'Food & Drink');
        $this->get('/analytics')->assertSee('₹300.00 of ₹6,000.00');
    }

    public function test_every_month_budgets(): void
    {
        $food = $this->category('Food & Drink');
        $this->put('/budgets/every-month', ['budgets' => [$food->id => '2,500']])
            ->assertRedirect('/budgets/every-month')->assertSessionHas('success', 'Budgets saved');
        $this->assertSame(250000, $food->fresh()->budget_minor);

        $this->put('/budgets/every-month', ['budgets' => [$food->id => '']]);
        $this->assertNull($food->fresh()->budget_minor);
    }
}
