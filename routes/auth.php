<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailCodeController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    // The 6-digit code page (App\Support\EmailVerification). The signed link
    // route below is Breeze's, kept for links already in inboxes.
    Route::get('verify-email', [EmailCodeController::class, 'show'])
        ->name('verification.notice');
    Route::post('verify-email', [EmailCodeController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('verification.code');
    Route::patch('verify-email/email', [EmailCodeController::class, 'changeEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.email');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailCodeController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

// ── Security: Google sign-in on the website, the two-factor step, date of birth ──
Route::middleware('guest')->group(function () {
    // Google Identity Services posts its ID token here (App\Support\SocialSignIn).
    Route::post('auth/google', [\App\Http\Controllers\Auth\GoogleSignInController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('auth.google');

    // After a right password or Google, for an account with two-factor on.
    Route::get('two-factor-challenge', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.challenge');
    Route::post('two-factor-challenge', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('two-factor.challenge.store');
});

Route::middleware('auth')->group(function () {
    // An account made by Google or Apple gives its date of birth first (App\Http\Middleware\RequireBirthday).
    Route::get('complete-profile', [\App\Http\Controllers\Auth\CompleteProfileController::class, 'create'])
        ->name('profile.complete');
    Route::put('complete-profile', [\App\Http\Controllers\Auth\CompleteProfileController::class, 'update'])
        ->name('profile.complete.update');
});
// ── end Security ──
