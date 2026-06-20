<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@financeapp.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password123')]
        );

        $defaultCategories = [
            ['name' => 'Rent', 'group' => 'home'],
            ['name' => 'Electricity Bill', 'group' => 'home'],
            ['name' => 'Water Bill', 'group' => 'home'],
            ['name' => 'Grocery', 'group' => 'home'],
            ['name' => 'Maintenance', 'group' => 'home'],
            ['name' => 'Food / Dining', 'group' => 'personal'],
            ['name' => 'Shopping', 'group' => 'personal'],
            ['name' => 'Travel', 'group' => 'personal'],
            ['name' => 'Medical', 'group' => 'personal'],
            ['name' => 'Entertainment', 'group' => 'personal'],
            ['name' => 'Office Expense', 'group' => 'business'],
            ['name' => 'Marketing', 'group' => 'business'],
            ['name' => 'Business Travel', 'group' => 'business'],
            ['name' => 'Employee Payment', 'group' => 'business'],
            ['name' => 'Other', 'group' => 'other'],
        ];

        foreach ($defaultCategories as $cat) {
            ExpenseCategory::firstOrCreate(
                ['user_id' => $user->id, 'name' => $cat['name']],
                ['group' => $cat['group'], 'is_default' => true]
            );
        }
    }
}
