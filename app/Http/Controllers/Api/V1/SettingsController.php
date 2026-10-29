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
        // One on offer (Admin › Currencies), or one the user added through /me/currencies.
        $allowed = array_merge(
            array_keys(Currencies::all()),
            $user->userCurrencies()->pluck('code')->all(),
        );
        $request->validate(['currency' => ['required', Rule::in($allowed)]]);

        $previous = $user->currency;
        $user->update(['currency' => $request->currency]);

        if ($previous !== $user->currency) {
            ActivityMail::currencyChanged($user, $previous);
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

    /** Settings › Reminders, as the website saves them; any subset of the three fields. */
    public function updateReminders(Request $request)
    {
        $request->user()->update(\App\Http\Controllers\Tracker\SettingsController::reminderChanges($request));

        return response()->json(['data' => \App\Support\ApiUser::payload($request->user()->fresh())]);
    }

    public function currencies()
    {
        return response()->json(['data' => Currencies::list()]);
    }

    /**
     * GET /currencies/rates — the rates Admin › Currencies suggests (rupees
     * for one unit), for the currencies on offer that have one. A starting
     * value for the rate field; the user's own rate always wins.
     */
    public function currencyRates()
    {
        $rates = collect(Currencies::all())->keys()
            ->reject(fn ($code) => $code === Currencies::BASE)
            ->map(fn ($code) => ['code' => $code, 'rate' => Currencies::defaultRate($code)])
            ->filter(fn ($row) => $row['rate'] !== null)
            ->values();

        return response()->json(['data' => $rates]);
    }
}
