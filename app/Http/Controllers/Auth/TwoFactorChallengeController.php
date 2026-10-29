<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RejectBlockedUsers;
use App\Support\TwoFactor;
use App\Support\WebSignIn;
use Illuminate\Http\Request;

/**
 * The page between a right password (or Google) and being signed in, for an
 * account with two-factor on: a code from the authenticator app, or a
 * recovery code. Five wrong codes, or ten minutes, and it starts over.
 */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request)
    {
        if (! TwoFactor::heldUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request)
    {
        $user = TwoFactor::heldUser($request);
        if (! $user || ! $user->hasTwoFactor()) {
            TwoFactor::releaseLogin($request);

            return redirect()->route('login')->withErrors(['email' => 'That took too long. Sign in again.']);
        }
        if ($user->isBlocked()) {
            TwoFactor::releaseLogin($request);

            return redirect()->route('login')->withErrors(['email' => RejectBlockedUsers::MESSAGE]);
        }

        $request->validate(['code' => 'required|string|max:32'], [
            'code.required' => 'Enter the 6-digit code from your authenticator app.',
            'code.max'      => 'That code is not right.',
        ]);

        $kind = TwoFactor::check($user, $request->input('code'));
        if ($kind === null) {
            if (TwoFactor::failedTry($request)) {
                TwoFactor::releaseLogin($request);

                return redirect()->route('login')->withErrors(['email' => 'Too many wrong codes. Sign in again.']);
            }

            return back()->withErrors(['code' => 'That code is not right. Try the newest one in your app.']);
        }

        $remember = TwoFactor::heldRemember($request);
        $response = WebSignIn::complete($request, $user, $remember);

        if ($kind === 'recovery') {
            $left = TwoFactor::recoveryCodesLeft($user);
            $request->session()->flash('error', "You used a recovery code; {$left} left. Make new ones in Admin › Settings if you are running low.");
        }

        return $response;
    }
}
