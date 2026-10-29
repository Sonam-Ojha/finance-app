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
Route::middleware('auth:sanctum')->name('api.')->group(function () {

    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::apiResource('income', IncomeController::class);
    Route::apiResource('expense', ExpenseController::class);
    Route::apiResource('bank', BankAccountController::class);
    Route::post('bank/{bank}/transaction', [BankAccountController::class, 'storeTransaction'])->name('bank.transaction.store');
    Route::apiResource('credit-card', CreditCardController::class);
    Route::post('credit-card/{creditCard}/transaction', [CreditCardController::class, 'storeTransaction'])->name('credit-card.transaction.store');
    Route::apiResource('loan', LoanController::class);
    Route::post('loan/{loan}/payment', [LoanController::class, 'storePayment'])->name('loan.payment.store');
    Route::apiResource('investment', InvestmentController::class);
    Route::apiResource('commission', CommissionController::class);
    Route::apiResource('cashback', CashbackController::class);
    Route::apiResource('bad-debt', BadDebtController::class);
    Route::apiResource('contact', ContactController::class);
    Route::post('contact/{contact}/transaction', [ContactController::class, 'storeTransaction'])->name('contact.transaction.store');
    Route::apiResource('reminder', ReminderController::class);
    Route::patch('reminder/{reminder}/done', [ReminderController::class, 'markDone'])->name('reminder.done');

    Route::get('report', [ReportController::class, 'index'])->name('report');

    // Settings / Masters
    Route::get('settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('settings/expense-category', [SettingsController::class, 'storeExpenseCategory'])->name('settings.expense-category.store');
    Route::delete('settings/expense-category/{expenseCategory}', [SettingsController::class, 'destroyExpenseCategory'])->name('settings.expense-category.destroy');
    Route::post('settings/income-category', [SettingsController::class, 'storeIncomeCategory'])->name('settings.income-category.store');
    Route::delete('settings/income-category/{incomeCategory}', [SettingsController::class, 'destroyIncomeCategory'])->name('settings.income-category.destroy');
    Route::post('settings/payment-mode', [SettingsController::class, 'storePaymentMode'])->name('settings.payment-mode.store');
    Route::delete('settings/payment-mode/{paymentMode}', [SettingsController::class, 'destroyPaymentMode'])->name('settings.payment-mode.destroy');
    Route::post('settings/investment-category', [SettingsController::class, 'storeInvestmentCategory'])->name('settings.investment-category.store');
    Route::delete('settings/investment-category/{investmentCategory}', [SettingsController::class, 'destroyInvestmentCategory'])->name('settings.investment-category.destroy');
    Route::post('settings/bank-master', [SettingsController::class, 'storeBankMaster'])->name('settings.bank-master.store');
    Route::delete('settings/bank-master/{bankMaster}', [SettingsController::class, 'destroyBankMaster'])->name('settings.bank-master.destroy');
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
use App\Http\Controllers\Api\V1\SocialAuthController as V1SocialAuth;
use App\Http\Controllers\Api\V1\TransactionController as V1Transaction;
use App\Http\Controllers\Api\V1\BudgetController as V1Budget;
use App\Http\Controllers\Api\V1\UserCurrencyController as V1UserCurrency;
use App\Http\Controllers\Api\V1\DeviceController as V1Device;
use App\Http\Controllers\Api\V1\NotificationController as V1Notification;
use App\Http\Controllers\Api\V1\EmailVerificationController as V1EmailVerification;

Route::prefix('v1')->group(function () {

    // Public
    Route::post('register', [V1Auth::class, 'register'])->middleware('throttle:10,1');
    Route::post('login',    [V1Auth::class, 'login'])->middleware('throttle:5,1');
    Route::post('auth/google', [V1SocialAuth::class, 'google'])->middleware('throttle:10,1');
    Route::post('auth/apple',  [V1SocialAuth::class, 'apple'])->middleware('throttle:10,1');
    Route::post('forgot-password', [V1Password::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password',  [V1Password::class, 'resetPassword']);
    Route::get('currencies', [V1Settings::class, 'currencies']);

    // Protected. `email.verified` lets an unverified account reach only what
    // verifying needs, once services.email_check.required_for_api is on.
    Route::middleware(['auth:sanctum', 'email.verified'])->group(function () {

        // Auth / user
        Route::post('logout',        [V1Auth::class, 'logout']);
        Route::get('me',             [V1Auth::class, 'me']);
        Route::post('me/email/code',   [V1EmailVerification::class, 'send'])->middleware('throttle:6,1');
        Route::post('me/email/verify', [V1EmailVerification::class, 'verify'])->middleware('throttle:10,1');
        Route::put('me/password',    [V1Auth::class, 'updatePassword']);
        Route::patch('me/profile',   [V1Auth::class, 'updateProfile']);
        Route::post('me/onboarded',  [V1Auth::class, 'markOnboarded']);
        Route::put('me/pin',         [V1Auth::class, 'setPin']);
        Route::delete('me/pin',      [V1Auth::class, 'removePin']);
        Route::post('me/avatar',     [V1Auth::class, 'uploadAvatar']);
        Route::delete('me/avatar',   [V1Auth::class, 'removeAvatar']);
        Route::delete('me',          [V1Auth::class, 'deleteAccount']);

        // Push notifications: this phone's Firebase token, and the inbox
        Route::post('me/devices',   [V1Device::class, 'store']);
        Route::delete('me/devices', [V1Device::class, 'destroy']);
        Route::get('notifications', [V1Notification::class, 'index']);

        // The user's exchange rates and added currencies
        Route::get('me/currencies',           [V1UserCurrency::class, 'index']);
        Route::put('me/currencies/{code}',    [V1UserCurrency::class, 'upsert']);
        Route::delete('me/currencies/{code}', [V1UserCurrency::class, 'destroy']);

        // Settings
        Route::put('settings/currency', [V1Settings::class, 'updateCurrency']);
        Route::put('settings/theme',    [V1Settings::class, 'updateTheme']);
        Route::put('settings/email-notifications', [V1Settings::class, 'updateEmailNotifications']);

        // Expenses and incomes together: search, filter, sort, paginate
        Route::get('transactions', [V1Transaction::class, 'index']);

        // Month-by-month budgets
        Route::get('budgets',             [V1Budget::class, 'index']);
        Route::post('budgets',            [V1Budget::class, 'store']);
        Route::get('budgets/{budget}',    [V1Budget::class, 'show'])->whereNumber('budget');
        Route::put('budgets/{budget}',    [V1Budget::class, 'update'])->whereNumber('budget');
        Route::delete('budgets/{budget}', [V1Budget::class, 'destroy'])->whereNumber('budget');

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
