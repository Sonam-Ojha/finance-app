<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\CreditCardController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CashbackController;
use App\Http\Controllers\Api\BadDebtController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::apiResource('income', IncomeController::class);
    Route::apiResource('expense', ExpenseController::class);
    Route::apiResource('bank', BankAccountController::class);
    Route::post('bank/{bank}/transaction', [BankAccountController::class, 'storeTransaction']);
    Route::apiResource('credit-card', CreditCardController::class);
    Route::post('credit-card/{creditCard}/transaction', [CreditCardController::class, 'storeTransaction']);
    Route::apiResource('loan', LoanController::class);
    Route::post('loan/{loan}/payment', [LoanController::class, 'storePayment']);
    Route::apiResource('investment', InvestmentController::class);
    Route::apiResource('commission', CommissionController::class);
    Route::apiResource('cashback', CashbackController::class);
    Route::apiResource('bad-debt', BadDebtController::class);
    Route::apiResource('contact', ContactController::class);
    Route::post('contact/{contact}/transaction', [ContactController::class, 'storeTransaction']);
    Route::apiResource('reminder', ReminderController::class);
    Route::patch('reminder/{reminder}/done', [ReminderController::class, 'markDone']);

    Route::get('report', [ReportController::class, 'index']);

    // Settings / Masters
    Route::get('settings', [SettingsController::class, 'index']);
    Route::post('settings/expense-category', [SettingsController::class, 'storeExpenseCategory']);
    Route::delete('settings/expense-category/{expenseCategory}', [SettingsController::class, 'destroyExpenseCategory']);
    Route::post('settings/income-category', [SettingsController::class, 'storeIncomeCategory']);
    Route::delete('settings/income-category/{incomeCategory}', [SettingsController::class, 'destroyIncomeCategory']);
    Route::post('settings/payment-mode', [SettingsController::class, 'storePaymentMode']);
    Route::delete('settings/payment-mode/{paymentMode}', [SettingsController::class, 'destroyPaymentMode']);
    Route::post('settings/investment-category', [SettingsController::class, 'storeInvestmentCategory']);
    Route::delete('settings/investment-category/{investmentCategory}', [SettingsController::class, 'destroyInvestmentCategory']);
    Route::post('settings/bank-master', [SettingsController::class, 'storeBankMaster']);
    Route::delete('settings/bank-master/{bankMaster}', [SettingsController::class, 'destroyBankMaster']);
});

// ─────────────────────────────────────────────────────────────────────────────
// API v1 — Expo Mobile App
// ─────────────────────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\V1\AuthController as V1Auth;
use App\Http\Controllers\Api\V1\SettingsController as V1Settings;
use App\Http\Controllers\Api\V1\BootstrapController as V1Bootstrap;
use App\Http\Controllers\Api\V1\CategoryController as V1Category;
use App\Http\Controllers\Api\V1\ExpenseController as V1Expense;
use App\Http\Controllers\Api\V1\IncomeController as V1Income;
use App\Http\Controllers\Api\V1\ReportController as V1Report;
use App\Http\Controllers\Api\V1\MetaController as V1Meta;
use App\Http\Controllers\Api\V1\ImportController as V1Import;
use App\Http\Controllers\Api\V1\PasswordController as V1Password;

Route::prefix('v1')->group(function () {

    // Public
    Route::post('register', [V1Auth::class, 'register'])->middleware('throttle:10,1');
    Route::post('login',    [V1Auth::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [V1Password::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password',  [V1Password::class, 'resetPassword']);
    Route::get('currencies', [V1Settings::class, 'currencies']);

    // Protected
    Route::middleware('auth:sanctum')->group(function () {

        // Auth / user
        Route::post('logout',        [V1Auth::class, 'logout']);
        Route::get('me',             [V1Auth::class, 'me']);
        Route::put('me/password',    [V1Auth::class, 'updatePassword']);
        Route::patch('me/profile',   [V1Auth::class, 'updateProfile']);
        Route::post('me/onboarded',  [V1Auth::class, 'markOnboarded']);
        Route::put('me/pin',         [V1Auth::class, 'setPin']);
        Route::delete('me/pin',      [V1Auth::class, 'removePin']);

        // Settings
        Route::put('settings/currency', [V1Settings::class, 'updateCurrency']);
        Route::put('settings/theme',    [V1Settings::class, 'updateTheme']);

        // Bootstrap
        Route::get('bootstrap', [V1Bootstrap::class, 'index']);

        // Categories — bulk budget BEFORE {category} to avoid routing collision
        Route::patch('categories/budgets',        [V1Category::class, 'bulkUpdateBudgets']);
        Route::get('categories',                  [V1Category::class, 'index']);
        Route::post('categories',                 [V1Category::class, 'store']);
        Route::get('categories/{category}',       [V1Category::class, 'show'])->whereNumber('category');
        Route::put('categories/{category}',       [V1Category::class, 'update'])->whereNumber('category');
        Route::delete('categories/{category}',    [V1Category::class, 'destroy'])->whereNumber('category');
        Route::patch('categories/{category}/budget', [V1Category::class, 'updateBudget'])->whereNumber('category');

        // Expenses
        Route::get('expenses',          [V1Expense::class, 'index']);
        Route::post('expenses',         [V1Expense::class, 'store']);
        Route::get('expenses/{expense}', [V1Expense::class, 'show']);
        Route::put('expenses/{expense}', [V1Expense::class, 'update']);
        Route::delete('expenses/{expense}', [V1Expense::class, 'destroy']);

        // Incomes
        Route::get('incomes',           [V1Income::class, 'index']);
        Route::post('incomes',          [V1Income::class, 'store']);
        Route::get('incomes/{income}',  [V1Income::class, 'show']);
        Route::put('incomes/{income}',  [V1Income::class, 'update']);
        Route::delete('incomes/{income}', [V1Income::class, 'destroy']);

        // Reports
        Route::get('reports/month-summary',   [V1Report::class, 'monthSummary']);
        Route::get('reports/category-totals', [V1Report::class, 'categoryTotals']);
        Route::get('reports/months',          [V1Report::class, 'months']);
        Route::get('reports/flow',            [V1Report::class, 'flow']);
        Route::get('reports/day-flow',        [V1Report::class, 'dayFlow']);
        Route::get('reports/lifetime',        [V1Report::class, 'lifetime']);
        Route::get('dashboard',               [V1Report::class, 'dashboard']);
        Route::get('alerts',                  [V1Report::class, 'alerts']);

        // Meta & Import
        Route::get('meta',    [V1Meta::class, 'index']);
        Route::post('import', [V1Import::class, 'store']);
    });
});
