<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivity;
use App\Support\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Admin › Settings › Two-factor authentication: turn on (QR code, then a
 * code to confirm), new recovery codes, turn off. The card is
 * resources/views/admin/partials/two-factor.blade.php; errors go in the
 * `twoFactor` bag so they show on that card only.
 */
class TwoFactorController extends Controller
{
    private const BAG = 'twoFactor';

    /** Turn on, step one: a new secret and its QR code. */
    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            return redirect()->route('admin.settings')->with('success', 'Two-factor authentication is already on.');
        }

        TwoFactor::start($user);

        return redirect()->to(route('admin.settings') . '#two-factor');
    }

    /** Turn on, step two: a code from the app proves the setup worked. */
    public function confirm(Request $request)
    {
        $user = $request->user();
        $request->validateWithBag(self::BAG, ['code' => 'required|string|max:16'], [
            'code.required' => 'Enter the 6-digit code your authenticator app shows.',
        ]);

        $codes = TwoFactor::confirm($user, $request->input('code'));
        if ($codes === null) {
            return redirect()->to(route('admin.settings') . '#two-factor')
                ->withErrors(['code' => 'That code is not right. Check the app shows this account, and try the newest code.'], self::BAG);
        }

        AdminActivity::record('two_factor.enabled', 'turned on two-factor authentication', $user);

        return redirect()->to(route('admin.settings') . '#two-factor')
            ->with('two_factor_recovery_codes', $codes)
            ->with('success', 'Two-factor authentication is on.');
    }

    /** A fresh set of recovery codes; the old ones stop working. Needs a current code. */
    public function recoveryCodes(Request $request)
    {
        $user = $request->user();
        if (! $user->hasTwoFactor()) {
            return redirect()->route('admin.settings');
        }
        $request->validateWithBag(self::BAG, ['recovery_code_check' => 'required|string|max:32'], [
            'recovery_code_check.required' => 'Enter a code from your authenticator app.',
        ]);

        if (TwoFactor::check($user, $request->input('recovery_code_check')) === null) {
            return redirect()->to(route('admin.settings') . '#two-factor')
                ->withErrors(['recovery_code_check' => 'That code is not right.'], self::BAG);
        }

        $codes = TwoFactor::regenerateRecoveryCodes($user);
        AdminActivity::record('two_factor.recovery_codes', 'made new two-factor recovery codes', $user);

        return redirect()->to(route('admin.settings') . '#two-factor')
            ->with('two_factor_recovery_codes', $codes)
            ->with('success', 'New recovery codes made. The old ones no longer work.');
    }

    /**
     * Turn off — or cancel a setup that was never confirmed. Turning it off
     * takes the current password (when the account has one) and a code.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        if (! $user->hasTwoFactor()) {
            TwoFactor::disable($user);

            return redirect()->route('admin.settings');
        }

        $request->validateWithBag(self::BAG, [
            'password'     => $user->has_password ? 'required|string' : 'nullable',
            'disable_code' => 'required|string|max:32',
        ], [
            'password.required'     => 'Enter your current password.',
            'disable_code.required' => 'Enter a code from your authenticator app, or a recovery code.',
        ]);

        if ($user->has_password && ! Hash::check($request->input('password'), $user->password)) {
            return redirect()->to(route('admin.settings') . '#two-factor')
                ->withErrors(['password' => 'Your current password is not right.'], self::BAG);
        }
        if (TwoFactor::check($user, $request->input('disable_code')) === null) {
            return redirect()->to(route('admin.settings') . '#two-factor')
                ->withErrors(['disable_code' => 'That code is not right.'], self::BAG);
        }

        TwoFactor::disable($user);
        AdminActivity::record('two_factor.disabled', 'turned off two-factor authentication', $user);

        return redirect()->route('admin.settings')->with('success', 'Two-factor authentication is off.');
    }
}
