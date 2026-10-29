<?php

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
    Route::delete('/groups/{group}/settlements/{settlement}', [Tracker\GroupController::class, 'destroySettlement'])->whereNumber(['group', 'settlement'])->name('groups.settlements.destroy');
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
    Route::delete('/goals/{goal}/contributions/{contribution}', [Tracker\GoalController::class, 'destroyContribution'])->whereNumber(['goal', 'contribution'])->name('goals.contributions.destroy');
    // ── end Exports, receipts, savings goals ──

    Route::get('/notifications', [Tracker\NotificationController::class, 'index'])->name('notifications.index');

    Route::get('/settings', [Tracker\SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/appearance', [Tracker\SettingsController::class, 'appearance'])->name('settings.appearance');
    Route::put('/settings/appearance', [Tracker\SettingsController::class, 'updateTheme'])->name('settings.theme');
    Route::get('/settings/reminders', [Tracker\SettingsController::class, 'reminders'])->name('settings.reminders.edit');
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
 * ── Retired "Smart Paisa" pages ──
 * The older pages (bank accounts, cards, loans, …) are retired. Every old
 * address, and anything under it, redirects permanently (301) to the page
 * that does its job now; the rows recorded in them stay in their tables.
 * Signed-out visitors land on the sign-in page from there.
 * App\Http\Controllers\RetiredController::page.
 */
foreach ([
    // old path (and everything under it) => [route name, query]
    'income'                       => ['transactions.index', ['type' => 'income']],
    'expense'                      => ['transactions.index', ['type' => 'expense']],
    'expense-categories'           => ['categories.index', []],
    'commission'                   => ['transactions.index', ['type' => 'income']],
    'cashback'                     => ['transactions.index', ['type' => 'income']],
    'bank'                         => ['dashboard', []],
    'credit-card'                  => ['dashboard', []],
    'loan'                         => ['recurring.index', []],     // EMIs are recurring expenses now
    'investment'                   => ['goals.index', []],
    'bad-debt'                     => ['groups.index', []],        // who owes whom
    'contact'                      => ['groups.index', []],
    'reminder'                     => ['settings.index', []],      // Settings › Reminders
    'report/generate'              => ['reports.pdf', []],         // its month and year carry over
    'report'                       => ['analytics.index', []],
    'settings/masters'             => ['settings.index', []],
    'settings/expense-category'    => ['categories.index', []],
    'settings/income-category'     => ['settings.index', []],
    'settings/payment-mode'        => ['settings.index', []],
    'settings/investment-category' => ['settings.index', []],
    'settings/bank-master'         => ['settings.index', []],
] as $old => [$to, $query]) {
    Route::any("{$old}/{rest?}", [\App\Http\Controllers\RetiredController::class, 'page'])
        ->where('rest', '.*')->defaults('to', $to)->defaults('query', $query);
}
// ── end Retired "Smart Paisa" pages ──

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

// ── Security: blocking users, admins' two-factor sign-in ──
// (App\Support\SignOut, App\Support\TwoFactor; the sign-in side is in routes/auth.php)
Route::middleware(['auth', 'email.verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('users/{user}/block', [\App\Http\Controllers\Admin\UserController::class, 'block'])->whereNumber('user')->name('users.block');
    Route::delete('users/{user}/block', [\App\Http\Controllers\Admin\UserController::class, 'unblock'])->whereNumber('user')->name('users.unblock');
    Route::post('two-factor', [\App\Http\Controllers\Admin\TwoFactorController::class, 'store'])->name('two-factor.store');
    Route::post('two-factor/confirm', [\App\Http\Controllers\Admin\TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('two-factor.confirm');
    Route::post('two-factor/recovery-codes', [\App\Http\Controllers\Admin\TwoFactorController::class, 'recoveryCodes'])->middleware('throttle:10,1')->name('two-factor.recovery-codes');
    Route::delete('two-factor', [\App\Http\Controllers\Admin\TwoFactorController::class, 'destroy'])->middleware('throttle:10,1')->name('two-factor.destroy');
});
// ── end Security ──

// ── Admin: default categories, currencies, app control, support ──
// (App\Support\DefaultCategories, Currencies, AppControl, SupportTickets)
Route::middleware(['auth', 'email.verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('default-categories', [\App\Http\Controllers\Admin\DefaultCategoryController::class, 'index'])->name('default-categories.index');
    Route::post('default-categories', [\App\Http\Controllers\Admin\DefaultCategoryController::class, 'store'])->name('default-categories.store');
    Route::get('default-categories/{defaultCategory}/edit', [\App\Http\Controllers\Admin\DefaultCategoryController::class, 'edit'])->whereNumber('defaultCategory')->name('default-categories.edit');
    Route::put('default-categories/{defaultCategory}', [\App\Http\Controllers\Admin\DefaultCategoryController::class, 'update'])->whereNumber('defaultCategory')->name('default-categories.update');
    Route::patch('default-categories/{defaultCategory}/move', [\App\Http\Controllers\Admin\DefaultCategoryController::class, 'move'])->whereNumber('defaultCategory')->name('default-categories.move');
    Route::delete('default-categories/{defaultCategory}', [\App\Http\Controllers\Admin\DefaultCategoryController::class, 'destroy'])->whereNumber('defaultCategory')->name('default-categories.destroy');

    Route::get('currencies', [\App\Http\Controllers\Admin\CurrencyController::class, 'index'])->name('currencies.index');
    Route::post('currencies', [\App\Http\Controllers\Admin\CurrencyController::class, 'store'])->name('currencies.store');
    Route::get('currencies/{currency:code}/edit', [\App\Http\Controllers\Admin\CurrencyController::class, 'edit'])->name('currencies.edit');
    Route::put('currencies/{currency:code}', [\App\Http\Controllers\Admin\CurrencyController::class, 'update'])->name('currencies.update');
    Route::patch('currencies/{currency:code}/toggle', [\App\Http\Controllers\Admin\CurrencyController::class, 'toggle'])->name('currencies.toggle');
    Route::delete('currencies/{currency:code}', [\App\Http\Controllers\Admin\CurrencyController::class, 'destroy'])->name('currencies.destroy');

    Route::get('app-control', [\App\Http\Controllers\Admin\AppControlController::class, 'index'])->name('app-control');
    Route::put('app-control/maintenance', [\App\Http\Controllers\Admin\AppControlController::class, 'maintenance'])->name('app-control.maintenance');
    Route::put('app-control/versions', [\App\Http\Controllers\Admin\AppControlController::class, 'versions'])->name('app-control.versions');
    Route::put('app-control/email-verification', [\App\Http\Controllers\Admin\AppControlController::class, 'emailVerification'])->name('app-control.email-verification');

    Route::get('support', [\App\Http\Controllers\Admin\SupportController::class, 'index'])->name('support.index');
    Route::get('support/{ticket}', [\App\Http\Controllers\Admin\SupportController::class, 'show'])->whereNumber('ticket')->name('support.show');
    Route::post('support/{ticket}/reply', [\App\Http\Controllers\Admin\SupportController::class, 'reply'])->whereNumber('ticket')->name('support.reply');
    Route::patch('support/{ticket}/close', [\App\Http\Controllers\Admin\SupportController::class, 'close'])->whereNumber('ticket')->name('support.close');
    Route::patch('support/{ticket}/reopen', [\App\Http\Controllers\Admin\SupportController::class, 'reopen'])->whereNumber('ticket')->name('support.reopen');
    Route::get('support/{ticket}/messages/{message}/screenshot', [\App\Http\Controllers\Admin\SupportController::class, 'screenshot'])->whereNumber(['ticket', 'message'])->name('support.screenshot');
});

// My account › Help & support (the same tickets as the app's /api/v1/support)
Route::middleware(['auth', 'email.verified'])->group(function () {
    Route::get('/support', [Tracker\SupportController::class, 'index'])->name('support.index');
    // 5 new requests an hour, website and app together (the same "support" counter as /api/v1/support).
    Route::post('/support', [Tracker\SupportController::class, 'store'])->middleware('throttle:5,60,support')->name('support.store');
    Route::get('/support/{ticket}', [Tracker\SupportController::class, 'show'])->whereNumber('ticket')->name('support.show');
    Route::post('/support/{ticket}/messages', [Tracker\SupportController::class, 'message'])->whereNumber('ticket')->middleware('throttle:30,60,support-messages')->name('support.messages.store');
    Route::get('/support/{ticket}/messages/{message}/screenshot', [Tracker\SupportController::class, 'screenshot'])->whereNumber(['ticket', 'message'])->name('support.screenshot');
});
// ── end Admin: default categories, currencies, app control, support ──

require __DIR__.'/auth.php';
