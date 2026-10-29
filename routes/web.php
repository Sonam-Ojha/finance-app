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
Route::middleware(['auth', 'email.verified'])->group(function () {
    Route::get('/dashboard', [Tracker\HomeController::class, 'index'])->name('dashboard');
    Route::get('/transactions', [Tracker\TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/analytics', [Tracker\AnalyticsController::class, 'index'])->name('analytics.index');

    // Recurring expenses and incomes (recurring:post adds them on their day)
    Route::get('/recurring', [Tracker\RecurringController::class, 'index'])->name('recurring.index');
    Route::get('/recurring/new', [Tracker\RecurringController::class, 'create'])->name('recurring.create');
    Route::post('/recurring', [Tracker\RecurringController::class, 'store'])->name('recurring.store');
    Route::get('/recurring/{recurring}/edit', [Tracker\RecurringController::class, 'edit'])->name('recurring.edit');
    Route::put('/recurring/{recurring}', [Tracker\RecurringController::class, 'update'])->name('recurring.update');
    Route::patch('/recurring/{recurring}/toggle', [Tracker\RecurringController::class, 'toggle'])->name('recurring.toggle');
    Route::delete('/recurring/{recurring}', [Tracker\RecurringController::class, 'destroy'])->name('recurring.destroy');

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

    // ── Groups: shared expenses, Splitwise-style (App\Support\Groups) ──
    Route::get('/groups', [Tracker\GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/new', [Tracker\GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [Tracker\GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [Tracker\GroupController::class, 'show'])->whereNumber('group')->name('groups.show');
    Route::get('/groups/{group}/edit', [Tracker\GroupController::class, 'edit'])->whereNumber('group')->name('groups.edit');
    Route::put('/groups/{group}', [Tracker\GroupController::class, 'update'])->whereNumber('group')->name('groups.update');
    Route::delete('/groups/{group}', [Tracker\GroupController::class, 'destroy'])->whereNumber('group')->name('groups.destroy');
    Route::post('/groups/{group}/members', [Tracker\GroupController::class, 'addMember'])->whereNumber('group')->middleware('throttle:30,1')->name('groups.members.store');
    Route::delete('/groups/{group}/members/{user}', [Tracker\GroupController::class, 'removeMember'])->whereNumber(['group', 'user'])->name('groups.members.destroy');
    Route::post('/groups/{group}/settlements', [Tracker\GroupController::class, 'settle'])->whereNumber('group')->name('groups.settlements.store');
    Route::get('/groups/{group}/expenses/new', [Tracker\GroupExpenseController::class, 'create'])->whereNumber('group')->name('groups.expenses.create');
    Route::post('/groups/{group}/expenses', [Tracker\GroupExpenseController::class, 'store'])->whereNumber('group')->name('groups.expenses.store');
    Route::get('/groups/{group}/expenses/{expense}/edit', [Tracker\GroupExpenseController::class, 'edit'])->whereNumber(['group', 'expense'])->name('groups.expenses.edit');
    Route::put('/groups/{group}/expenses/{expense}', [Tracker\GroupExpenseController::class, 'update'])->whereNumber(['group', 'expense'])->name('groups.expenses.update');
    Route::delete('/groups/{group}/expenses/{expense}', [Tracker\GroupExpenseController::class, 'destroy'])->whereNumber(['group', 'expense'])->name('groups.expenses.destroy');
    // ── end Groups ──

    // ── Exports, receipts, savings goals ──
    // Transactions › Export: the list as CSV (same query string as the page), a month's report as PDF
    Route::get('/transactions/export.csv', [Tracker\ExportController::class, 'transactions'])->name('transactions.export');
    Route::get('/reports/monthly.pdf', [Tracker\ExportController::class, 'report'])->name('reports.pdf');
    // An expense's receipt photo: private, the owner's only (App\Support\Receipts)
    Route::get('/expenses/{expense}/receipt', [Tracker\ExpenseController::class, 'receipt'])->whereNumber('expense')->name('expenses.receipt');
    // Savings goals (App\Support\Goals)
    Route::get('/goals', [Tracker\GoalController::class, 'index'])->name('goals.index');
    Route::get('/goals/create', [Tracker\GoalController::class, 'create'])->name('goals.create');
    Route::post('/goals', [Tracker\GoalController::class, 'store'])->name('goals.store');
    Route::get('/goals/{goal}', [Tracker\GoalController::class, 'show'])->whereNumber('goal')->name('goals.show');
    Route::get('/goals/{goal}/edit', [Tracker\GoalController::class, 'edit'])->whereNumber('goal')->name('goals.edit');
    Route::put('/goals/{goal}', [Tracker\GoalController::class, 'update'])->whereNumber('goal')->name('goals.update');
    Route::patch('/goals/{goal}/archive', [Tracker\GoalController::class, 'archive'])->whereNumber('goal')->name('goals.archive');
    Route::delete('/goals/{goal}', [Tracker\GoalController::class, 'destroy'])->whereNumber('goal')->name('goals.destroy');
    Route::post('/goals/{goal}/contributions', [Tracker\GoalController::class, 'contribute'])->whereNumber('goal')->name('goals.contributions.store');
    // ── end Exports, receipts, savings goals ──

    Route::get('/notifications', [Tracker\NotificationController::class, 'index'])->name('notifications.index');

    Route::get('/settings', [Tracker\SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/appearance', [Tracker\SettingsController::class, 'appearance'])->name('settings.appearance');
    Route::put('/settings/appearance', [Tracker\SettingsController::class, 'updateTheme'])->name('settings.theme');
    Route::put('/settings/reminders', [Tracker\SettingsController::class, 'updateReminders'])->name('settings.reminders');
    Route::put('/settings/sidebar',[Tracker\SettingsController::class, 'updateSidebar'])->name('settings.sidebar');
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
Route::middleware(['auth', 'email.verified'])->group(function () {

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
Route::middleware(['auth', 'email.verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('activity', [AdminActivityController::class, 'index'])->name('activity.index');
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
    Route::get('notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications', [AdminNotificationController::class, 'store'])->name('notifications.store');
    Route::patch('notifications/scheduled/{scheduled}', [AdminNotificationController::class, 'toggle'])->name('notifications.scheduled.toggle');
    Route::delete('notifications/scheduled/{scheduled}', [AdminNotificationController::class, 'destroy'])->name('notifications.scheduled.destroy');
    Route::view('settings', 'admin.settings')->name('settings');
});

require __DIR__.'/auth.php';
