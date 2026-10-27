<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ActivityMail;
use App\Support\Currencies;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function updateCurrency(Request $request)
    {
        $user = $request->user();
        // A built-in code, or one the user added through /me/currencies.
        $allowed = array_merge(
            array_keys(Currencies::BUILT_IN),
            $user->userCurrencies()->pluck('code')->all(),
        );
        $request->validate(['currency' => ['required', Rule::in($allowed)]]);

        $previous = $user->currency;
        $user->update(['currency' => $request->currency]);

        if ($previous !== $user->currency) {
            ActivityMail::send($user, "Currency changed to {$user->currency}", 'You changed the currency the app shows amounts in.', [
                'From' => $previous,
                'To'   => $user->currency,
            ]);
        }

        return response()->noContent();
    }

    public function updateTheme(Request $request)
    {
        $request->validate(['theme_mode' => 'required|in:light,dark,system']);
        $request->user()->update(['theme_mode' => $request->theme_mode]);
        return response()->noContent();
    }

    /** The per-user switch for ActivityMail. */
    public function updateEmailNotifications(Request $request)
    {
        $request->validate(['enabled' => 'required|boolean']);
        $request->user()->update(['email_notifications' => $request->boolean('enabled')]);
        return response()->noContent();
    }

    public function currencies()
    {
        return response()->json(['data' => Currencies::list()]);
    }
}
