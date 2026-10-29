<?php

use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
// Retired: the first "Smart Paisa" API (/api/auth/*, /api/income, /api/bank, …)
// Every one of its paths, any method, answers 410 Gone with
// {"message": "…Please update the app.", "code": "api_retired"} — no sign-in
// needed, rate-limited. The app only calls /api/v1. App\Http\Controllers\RetiredController::api.
// ─────────────────────────────────────────────────────────────────────────────
Route::any('{retired}/{rest?}', [\App\Http\Controllers\RetiredController::class, 'api'])
    ->where('retired', 'auth|dashboard|income|expense|bank|credit-card|loan|investment|commission|cashback|bad-debt|contact|reminder|report|settings')
    ->where('rest', '.*')
    ->middleware('throttle:60,1');
// ── end Retired API ──

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
    Route::post('reset-password',  [V1Password::class, 'resetPassword'])->middleware('throttle:5,1'); // Security: guessing reset codes
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
        Route::post('me/avatar',     [V1Auth::class, 'uploadAvatar'])->middleware('throttle:10,1'); // Security: upload flood
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
        Route::put('settings/reminders', [V1Settings::class, 'updateReminders']);

        // Expenses and incomes together: search, filter, sort, paginate
        Route::get('transactions', [V1Transaction::class, 'index']);

        // Recurring expenses and incomes (recurring:post adds them on their day)
        Route::get('recurring',               [\App\Http\Controllers\Api\V1\RecurringController::class, 'index']);
        Route::post('recurring',              [\App\Http\Controllers\Api\V1\RecurringController::class, 'store']);
        Route::put('recurring/{recurring}',    [\App\Http\Controllers\Api\V1\RecurringController::class, 'update'])->whereNumber('recurring');
        Route::delete('recurring/{recurring}', [\App\Http\Controllers\Api\V1\RecurringController::class, 'destroy'])->whereNumber('recurring');

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
        Route::post('import', [V1Import::class, 'store'])->middleware('throttle:5,1'); // Security: heavy request

        // ── Groups: shared expenses, Splitwise-style (App\Support\Groups) ──
        // A group the user is not in is a 404.
        Route::get('groups',           [\App\Http\Controllers\Api\V1\GroupController::class, 'index']);
        Route::post('groups',          [\App\Http\Controllers\Api\V1\GroupController::class, 'store']);
        Route::get('groups/{group}',    [\App\Http\Controllers\Api\V1\GroupController::class, 'show'])->whereNumber('group');
        Route::put('groups/{group}',    [\App\Http\Controllers\Api\V1\GroupController::class, 'update'])->whereNumber('group');
        Route::delete('groups/{group}', [\App\Http\Controllers\Api\V1\GroupController::class, 'destroy'])->whereNumber('group');
        Route::post('groups/{group}/members',          [\App\Http\Controllers\Api\V1\GroupController::class, 'addMember'])->whereNumber('group')->middleware('throttle:30,1');
        Route::delete('groups/{group}/members/{user}', [\App\Http\Controllers\Api\V1\GroupController::class, 'removeMember'])->whereNumber(['group', 'user']);
        Route::post('groups/{group}/settlements',      [\App\Http\Controllers\Api\V1\GroupController::class, 'settle'])->whereNumber('group');
        Route::delete('groups/{group}/settlements/{settlement}', [\App\Http\Controllers\Api\V1\GroupController::class, 'destroySettlement'])->whereNumber(['group', 'settlement']); // undo a payment
        Route::get('groups/{group}/expenses',                [\App\Http\Controllers\Api\V1\GroupExpenseController::class, 'index'])->whereNumber('group');
        Route::post('groups/{group}/expenses',               [\App\Http\Controllers\Api\V1\GroupExpenseController::class, 'store'])->whereNumber('group');
        Route::put('groups/{group}/expenses/{expense}',      [\App\Http\Controllers\Api\V1\GroupExpenseController::class, 'update'])->whereNumber(['group', 'expense']);
        Route::delete('groups/{group}/expenses/{expense}',   [\App\Http\Controllers\Api\V1\GroupExpenseController::class, 'destroy'])->whereNumber(['group', 'expense']);
        // ── end Groups ──

        // ── Exports, receipts, savings goals ──
        // Files to download (bearer token; the response is the file itself)
        Route::get('export/transactions.csv', [\App\Http\Controllers\Api\V1\ExportController::class, 'transactions']);
        Route::get('export/report.pdf',       [\App\Http\Controllers\Api\V1\ExportController::class, 'report']);
        // An expense's receipt photo: private, the owner's only (App\Support\Receipts)
        Route::get('expenses/{expense}/receipt',    [\App\Http\Controllers\Api\V1\ReceiptController::class, 'show'])->whereNumber('expense');
        Route::post('expenses/{expense}/receipt',   [\App\Http\Controllers\Api\V1\ReceiptController::class, 'store'])->whereNumber('expense')->middleware('throttle:20,1'); // Security: upload flood
        Route::delete('expenses/{expense}/receipt', [\App\Http\Controllers\Api\V1\ReceiptController::class, 'destroy'])->whereNumber('expense');
        // Savings goals and the money in them (App\Support\Goals)
        Route::get('goals',                       [\App\Http\Controllers\Api\V1\GoalController::class, 'index']);
        Route::post('goals',                      [\App\Http\Controllers\Api\V1\GoalController::class, 'store']);
        Route::get('goals/{goal}',                [\App\Http\Controllers\Api\V1\GoalController::class, 'show'])->whereNumber('goal');
        Route::put('goals/{goal}',                [\App\Http\Controllers\Api\V1\GoalController::class, 'update'])->whereNumber('goal');
        Route::delete('goals/{goal}',             [\App\Http\Controllers\Api\V1\GoalController::class, 'destroy'])->whereNumber('goal');
        Route::get('goals/{goal}/contributions',  [\App\Http\Controllers\Api\V1\GoalController::class, 'contributions'])->whereNumber('goal');
        Route::post('goals/{goal}/contributions', [\App\Http\Controllers\Api\V1\GoalController::class, 'contribute'])->whereNumber('goal');
        Route::delete('goals/{goal}/contributions/{contribution}', [\App\Http\Controllers\Api\V1\GoalController::class, 'destroyContribution'])->whereNumber(['goal', 'contribution']);
        // ── end Exports, receipts, savings goals ──
    });
});

// ── App control, suggested rates, Help & support ──
// (App\Support\AppControl, Currencies, SupportTickets; App\Http\Middleware\AppGate answers 503/426 first)
Route::prefix('v1')->group(function () {
    // Public. Answered even in maintenance mode, and to an outdated app.
    Route::get('app-status', [\App\Http\Controllers\Api\V1\AppStatusController::class, 'show']);
    // Admin › Currencies' suggested rates: a starting value for the rate field.
    Route::get('currencies/rates', [\App\Http\Controllers\Api\V1\SettingsController::class, 'currencyRates']);

    Route::middleware(['auth:sanctum', 'email.verified'])->group(function () {
        Route::get('support', [\App\Http\Controllers\Api\V1\SupportController::class, 'index']);
        // 5 new requests an hour, app and website together (the same "support" counter as POST /support on the website).
        Route::post('support', [\App\Http\Controllers\Api\V1\SupportController::class, 'store'])->middleware('throttle:5,60,support');
        Route::get('support/{ticket}', [\App\Http\Controllers\Api\V1\SupportController::class, 'show'])->whereNumber('ticket');
        Route::post('support/{ticket}/messages', [\App\Http\Controllers\Api\V1\SupportController::class, 'message'])->whereNumber('ticket')->middleware('throttle:30,60,support-messages');
        Route::get('support/{ticket}/messages/{message}/screenshot', [\App\Http\Controllers\Api\V1\SupportController::class, 'screenshot'])->whereNumber(['ticket', 'message']);
    });
});
// ── end App control, suggested rates, Help & support ──
