<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The website's last step of signing in, after a right password or a
 * checked Google token: in straight away, or first to the two-factor code
 * page when the account has it on (App\Support\TwoFactor).
 */
class WebSignIn
{
    public static function proceed(Request $request, User $user, bool $remember = false): RedirectResponse
    {
        if ($user->hasTwoFactor()) {
            TwoFactor::holdLogin($request, $user, $remember);

            return redirect()->route('two-factor.challenge');
        }

        return self::complete($request, $user, $remember);
    }

    public static function complete(Request $request, User $user, bool $remember = false): RedirectResponse
    {
        TwoFactor::releaseLogin($request);
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
