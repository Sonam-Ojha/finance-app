<?php

namespace Tests\Feature\Tracker;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/** The one-time copy of the older website's expenses and incomes into the app's tables. */
class CopyWebsiteEntriesTest extends TrackerTestCase
{
    public function test_old_website_entries_are_copied_into_the_apps_tables(): void
    {
        $uid = $this->user->id;
        $groceries = DB::table('expense_categories')->insertGetId(['user_id' => $uid, 'name' => 'groceries', 'created_at' => now(), 'updated_at' => now()]);
        $rent      = DB::table('expense_categories')->insertGetId(['user_id' => $uid, 'name' => 'House Rent', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('expenses')->insert([
            ['user_id' => $uid, 'expense_category_id' => $groceries, 'date' => '2026-09-14', 'amount' => '1250.50', 'payment_mode' => 'Cash', 'description' => 'Big shop', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $uid, 'expense_category_id' => $rent, 'date' => '2026-09-01', 'amount' => '15000.00', 'payment_mode' => 'Cheque', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('incomes')->insert(['user_id' => $uid, 'type' => 'salary', 'date' => '2026-09-01', 'amount' => '50000.00', 'payment_mode' => 'Bank Transfer', 'note' => 'September', 'created_at' => now(), 'updated_at' => now()]);

        (require database_path('migrations/2026_10_28_000001_copy_website_entries_into_app_tables.php'))->up();

        $shop = AppExpense::where('note', 'Big shop')->firstOrFail();
        // Matched to the app's own "Groceries" whatever its case.
        $this->assertSame([125050, $this->category('Groceries')->id, 'cash', '2026-09-14'], [$shop->amount_minor, $shop->category_id, $shop->payment_method, $shop->spent_at->toDateString()]);

        $rentRow = AppExpense::where('amount_minor', 1500000)->firstOrFail();
        $this->assertSame(['House Rent', 'other'], [$rentRow->category->name, $rentRow->payment_method]);

        $salary = AppIncome::firstOrFail();
        $this->assertSame([5000000, 'Salary', 'September', 'bank_transfer'], [$salary->amount_minor, $salary->source, $salary->note, $salary->payment_method]);

        // The originals stay.
        $this->assertSame(2, DB::table('expenses')->count());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_28_000001_copy_website_entries_into_app_tables.php');
    }

    private function oldExpense(int $categoryId, string $amount, ?string $description, string $date = '2026-09-14'): void
    {
        DB::table('expenses')->insert(['user_id' => $this->user->id, 'expense_category_id' => $categoryId, 'date' => $date, 'amount' => $amount,
            'payment_mode' => 'Cash', 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_long_category_names_are_cut_to_40_characters_and_shared_not_duplicated(): void
    {
        $uid  = $this->user->id;
        $long = 'Household repairs, plumbing and electricals'; // 43 characters
        $a = DB::table('expense_categories')->insertGetId(['user_id' => $uid, 'name' => $long, 'created_at' => now(), 'updated_at' => now()]);
        // A different old name that is the same once cut to 40 characters.
        $b = DB::table('expense_categories')->insertGetId(['user_id' => $uid, 'name' => mb_substr($long, 0, 40) . ' (old)', 'created_at' => now(), 'updated_at' => now()]);
        $this->oldExpense($a, '500.00', 'Tap');
        $this->oldExpense($a, '700.00', 'Fan');
        $this->oldExpense($b, '900.00', 'Wiring');

        $this->migration()->up();

        $cut = mb_substr($long, 0, 40);
        $this->assertSame(1, Category::where('user_id', $uid)->where('name', $cut)->count());
        $this->assertSame(3, AppExpense::where('category_id', $this->categoryNamed($cut)->id)->count());
    }

    public function test_running_it_again_copies_nothing_twice(): void
    {
        $uid = $this->user->id;
        $tea = DB::table('expense_categories')->insertGetId(['user_id' => $uid, 'name' => 'Tea stall', 'created_at' => now(), 'updated_at' => now()]);
        // Two old entries that are exact twins: both are real, both are copied.
        $this->oldExpense($tea, '20.00', 'Chai');
        $this->oldExpense($tea, '20.00', 'Chai');
        $this->oldExpense($tea, '35.00', null);
        DB::table('incomes')->insert(['user_id' => $uid, 'type' => 'business', 'date' => '2026-09-05', 'amount' => '1200.00', 'payment_mode' => 'UPI', 'note' => null, 'created_at' => now(), 'updated_at' => now()]);

        $migration = $this->migration();
        $migration->up();
        $this->assertSame([3, 1, 1], [AppExpense::count(), AppIncome::count(), Category::where('user_id', $uid)->where('name', 'Tea stall')->count()]);

        $migration->up();
        $this->assertSame([3, 1, 1], [AppExpense::count(), AppIncome::count(), Category::where('user_id', $uid)->where('name', 'Tea stall')->count()]);
        $this->assertSame(2, AppExpense::where('note', 'Chai')->count());

        // Something new in the old tables since: only that is copied.
        $this->oldExpense($tea, '20.00', 'Chai');
        $migration->up();
        $this->assertSame([4, 3], [AppExpense::count(), AppExpense::where('note', 'Chai')->count()]);
    }

    private function categoryNamed(string $name): Category
    {
        return Category::where('user_id', $this->user->id)->where('name', $name)->firstOrFail();
    }
}
