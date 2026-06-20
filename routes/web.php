<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
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
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
