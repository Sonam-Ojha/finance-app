<?php

use App\Http\Controllers\IncomeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CashbackController;
use App\Http\Controllers\BadDebtController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Tracker;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

/*
 * The expense tracker — the same features, and the same data, as the app.
 */
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [Tracker\HomeController::class, 'index'])->name('dashboard');
    Route::get('/transactions', [Tracker\TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/analytics', [Tracker\AnalyticsController::class, 'index'])->name('analytics.index');

    Route::get('/expenses/create', [Tracker\ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/expenses', [Tracker\ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('/expenses/{expense}/edit', [Tracker\ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('/expenses/{expense}', [Tracker\ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{expense}', [Tracker\ExpenseController::class, 'destroy'])->name('expenses.destroy');

    Route::get('/incomes/create', [Tracker\IncomeController::class, 'create'])->name('incomes.create');
    Route::post('/incomes', [Tracker\IncomeController::class, 'store'])->name('incomes.store');
    Route::get('/incomes/{income}/edit', [Tracker\IncomeController::class, 'edit'])->name('incomes.edit');
    Route::put('/incomes/{income}', [Tracker\IncomeController::class, 'update'])->name('incomes.update');
    Route::delete('/incomes/{income}', [Tracker\IncomeController::class, 'destroy'])->name('incomes.destroy');

    Route::resource('categories', Tracker\CategoryController::class)->except(['show']);

    Route::get('/budgets', [Tracker\BudgetController::class, 'index'])->name('budgets.index');
    Route::put('/budgets', [Tracker\BudgetController::class, 'update'])->name('budgets.update');
    Route::get('/budgets/every-month', [Tracker\BudgetController::class, 'defaults'])->name('budgets.defaults');
    Route::put('/budgets/every-month', [Tracker\BudgetController::class, 'updateDefaults'])->name('budgets.defaults.update');

    Route::get('/notifications', [Tracker\NotificationController::class, 'index'])->name('notifications.index');

    Route::get('/settings', [Tracker\SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/appearance', [Tracker\SettingsController::class, 'appearance'])->name('settings.appearance');
    Route::put('/settings/appearance', [Tracker\SettingsController::class, 'updateTheme'])->name('settings.theme');
    Route::put('/settings/email-notifications', [Tracker\SettingsController::class, 'updateEmailNotifications'])->name('settings.email');
    Route::get('/about', [Tracker\SettingsController::class, 'about'])->name('about');

    Route::get('/settings/currency', [Tracker\CurrencyController::class, 'index'])->name('currency.index');
    Route::put('/settings/currency', [Tracker\CurrencyController::class, 'select'])->name('currency.select');
    Route::get('/settings/currency/new', [Tracker\CurrencyController::class, 'create'])->name('currency.create');
    Route::put('/settings/currency/new', [Tracker\CurrencyController::class, 'save'])->name('currency.store');
    Route::get('/settings/currency/{code}', [Tracker\CurrencyController::class, 'edit'])->where('code', '[A-Z]{2,5}')->name('currency.edit');
    Route::put('/settings/currency/{code}', [Tracker\CurrencyController::class, 'save'])->where('code', '[A-Z]{2,5}')->name('currency.save');
    Route::delete('/settings/currency/{code}', [Tracker\CurrencyController::class, 'destroy'])->where('code', '[A-Z]{2,5}')->name('currency.destroy');

    Route::get('/profile', [Tracker\AccountController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [Tracker\AccountController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [Tracker\AccountController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/photo', [Tracker\AccountController::class, 'storePhoto'])->name('profile.photo');
    Route::delete('/profile/photo', [Tracker\AccountController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::get('/settings/password', [Tracker\AccountController::class, 'password'])->name('account.password');
    Route::put('/settings/password', [Tracker\AccountController::class, 'updatePassword'])->name('account.password.update');
});

/*
 * The older "Smart Paisa" pages (bank accounts, cards, loans, …). They are
 * off the menu — the website now offers what the app offers — but stay
 * reachable so nothing recorded in them is lost.
 */
Route::middleware(['auth'])->group(function () {

    // Income
    Route::resource('income', IncomeController::class)->except(['show']);
    Route::get('income/{income}', [IncomeController::class, 'show'])->name('income.show');

    // Expense
    Route::resource('expense', ExpenseController::class)->except(['show']);
    Route::get('expense/{expense}', [ExpenseController::class, 'show'])->name('expense.show');
    Route::get('expense-categories', [ExpenseController::class, 'categories'])->name('expense.categories');
    Route::post('expense-categories', [ExpenseController::class, 'storeCategory'])->name('expense.categories.store');
    Route::delete('expense-categories/{expenseCategory}', [ExpenseController::class, 'destroyCategory'])->name('expense.categories.destroy');

    // Bank Accounts
    Route::resource('bank', BankAccountController::class)->except(['show']);
    Route::get('bank/{bank}', [BankAccountController::class, 'show'])->name('bank.show');
    Route::post('bank/{bank}/transaction', [BankAccountController::class, 'storeTransaction'])->name('bank.transaction.store');

    // Credit Cards
    Route::resource('credit-card', CreditCardController::class)->except(['show']);
    Route::get('credit-card/{creditCard}', [CreditCardController::class, 'show'])->name('credit-card.show');
    Route::post('credit-card/{creditCard}/transaction', [CreditCardController::class, 'storeTransaction'])->name('credit-card.transaction.store');

    // Loans
    Route::resource('loan', LoanController::class)->except(['show']);
    Route::get('loan/{loan}', [LoanController::class, 'show'])->name('loan.show');
    Route::post('loan/{loan}/payment', [LoanController::class, 'storePayment'])->name('loan.payment.store');

    // Investments
    Route::resource('investment', InvestmentController::class);

    // Commissions
    Route::resource('commission', CommissionController::class);

    // Cashbacks
    Route::resource('cashback', CashbackController::class);

    // Bad Debt / Pending Money
    Route::resource('bad-debt', BadDebtController::class)->except(['show']);
    Route::get('bad-debt/{badDebt}', [BadDebtController::class, 'show'])->name('bad-debt.show');

    // Contacts / Ledger
    Route::resource('contact', ContactController::class)->except(['show']);
    Route::get('contact/{contact}', [ContactController::class, 'show'])->name('contact.show');
    Route::post('contact/{contact}/transaction', [ContactController::class, 'storeTransaction'])->name('contact.transaction.store');

    // Reminders
    Route::resource('reminder', ReminderController::class)->except(['show']);
    Route::get('reminder/{reminder}', [ReminderController::class, 'show'])->name('reminder.show');
    Route::patch('reminder/{reminder}/done', [ReminderController::class, 'markDone'])->name('reminder.done');

    // Reports
    Route::get('report', [ReportController::class, 'index'])->name('report.index');
    Route::post('report/generate', [ReportController::class, 'generate'])->name('report.generate');

    // Settings / Masters (the page itself is now the tracker's Settings)
    Route::get('settings/masters', [SettingsController::class, 'index'])->name('settings.masters');
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

// Admin — users with role "admin" only
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('activity', [AdminActivityController::class, 'index'])->name('activity.index');
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
    Route::get('notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications', [AdminNotificationController::class, 'store'])->name('notifications.store');
});

require __DIR__.'/auth.php';
