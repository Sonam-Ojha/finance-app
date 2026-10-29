<?php

namespace Tests\Feature\Tracker;

use App\Models\AppExpense;
use App\Models\AppIncome;
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
}
