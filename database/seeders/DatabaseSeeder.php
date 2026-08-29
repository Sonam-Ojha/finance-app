<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\PaymentMode;
use App\Models\InvestmentCategory;
use App\Models\BankMaster;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@financeapp.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password123')]
        );

        // ── Expense Categories ──────────────────────────────
        $expenseCategories = [
            ['name' => 'Rent',             'group' => 'home'],
            ['name' => 'Electricity Bill', 'group' => 'home'],
            ['name' => 'Water Bill',       'group' => 'home'],
            ['name' => 'Grocery',          'group' => 'home'],
            ['name' => 'Maintenance',      'group' => 'home'],
            ['name' => 'Food / Dining',    'group' => 'personal'],
            ['name' => 'Shopping',         'group' => 'personal'],
            ['name' => 'Travel',           'group' => 'personal'],
            ['name' => 'Medical',          'group' => 'personal'],
            ['name' => 'Entertainment',    'group' => 'personal'],
            ['name' => 'Fuel',             'group' => 'personal'],
            ['name' => 'Office Expense',   'group' => 'business'],
            ['name' => 'Marketing',        'group' => 'business'],
            ['name' => 'Business Travel',  'group' => 'business'],
            ['name' => 'Employee Payment', 'group' => 'business'],
            ['name' => 'Other',            'group' => 'other'],
        ];
        foreach ($expenseCategories as $cat) {
            ExpenseCategory::firstOrCreate(
                ['user_id' => $user->id, 'name' => $cat['name']],
                ['group' => $cat['group'], 'is_default' => true]
            );
        }

        // ── Income Categories ───────────────────────────────
        $incomeCategories = [
            ['name' => 'Monthly Salary',   'type' => 'salary'],
            ['name' => 'LIC Commission',   'type' => 'lic_commission'],
            ['name' => 'Freelance',        'type' => 'business'],
            ['name' => 'Part-time Income', 'type' => 'business'],
            ['name' => 'Bonus',            'type' => 'other'],
            ['name' => 'Gift',             'type' => 'other'],
            ['name' => 'Dividend',         'type' => 'other'],
        ];
        foreach ($incomeCategories as $cat) {
            IncomeCategory::firstOrCreate(
                ['user_id' => $user->id, 'name' => $cat['name']],
                ['type' => $cat['type'], 'is_default' => true]
            );
        }

        // ── Payment Modes ───────────────────────────────────
        $paymentModes = ['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'Credit Card', 'Debit Card', 'Online', 'Other'];
        foreach ($paymentModes as $mode) {
            PaymentMode::firstOrCreate(
                ['user_id' => $user->id, 'name' => $mode],
                ['is_default' => true]
            );
        }

        // ── Investment Categories ────────────────────────────
        $investCategories = ['LIC Policy', 'Mutual Fund', 'Stocks / Equity', 'Fixed Deposit (FD)', 'Recurring Deposit (RD)', 'Gold / Jewellery', 'Real Estate', 'PPF', 'NPS', 'Crypto', 'Other'];
        foreach ($investCategories as $cat) {
            InvestmentCategory::firstOrCreate(
                ['user_id' => $user->id, 'name' => $cat],
                ['is_default' => true]
            );
        }

        // ── Bank Masters ─────────────────────────────────────
        $banks = [
            ['name' => 'State Bank of India',          'short_name' => 'SBI'],
            ['name' => 'HDFC Bank',                    'short_name' => 'HDFC'],
            ['name' => 'ICICI Bank',                   'short_name' => 'ICICI'],
            ['name' => 'Axis Bank',                    'short_name' => 'Axis'],
            ['name' => 'Punjab National Bank',         'short_name' => 'PNB'],
            ['name' => 'Bank of Baroda',               'short_name' => 'BOB'],
            ['name' => 'Kotak Mahindra Bank',          'short_name' => 'Kotak'],
            ['name' => 'IndusInd Bank',                'short_name' => 'IndusInd'],
            ['name' => 'Yes Bank',                     'short_name' => 'Yes'],
            ['name' => 'Canara Bank',                  'short_name' => 'Canara'],
            ['name' => 'Union Bank of India',          'short_name' => 'UBI'],
            ['name' => 'Bank of India',                'short_name' => 'BOI'],
            ['name' => 'IDBI Bank',                    'short_name' => 'IDBI'],
            ['name' => 'Federal Bank',                 'short_name' => 'Federal'],
            ['name' => 'Indian Bank',                  'short_name' => 'IB'],
            ['name' => 'Post Office Savings Bank',     'short_name' => 'POSB'],
            ['name' => 'Paytm Payments Bank',          'short_name' => 'Paytm'],
            ['name' => 'Other',                        'short_name' => null],
        ];
        foreach ($banks as $bank) {
            BankMaster::firstOrCreate(
                ['user_id' => $user->id, 'name' => $bank['name']],
                ['short_name' => $bank['short_name'], 'is_default' => true]
            );
        }
    }
}
