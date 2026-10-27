<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserCurrency;
use App\Support\ActivityMail;
use App\Support\Currencies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The user's exchange rates and the currencies they added. Amounts stay in
 * INR on the server; a rate only says how the app should show them.
 */
class UserCurrencyController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => $request->user()->userCurrencies()->orderBy('id')->get()->map->toApiShape(),
        ]);
    }

    /** PUT /me/currencies/{code} — create or update. */
    public function upsert(Request $request, string $code)
    {
        $user   = $request->user();
        $custom = ! Currencies::isBuiltIn($code);

        $rules = [
            // Checked here rather than in the route, so a bad code is a 422
            // with a message instead of a bare 404.
            'code' => ['required', 'regex:/^[A-Z]{2,5}$/', 'not_in:' . Currencies::BASE],
            'rate' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,9}(\.\d{1,6})?$/'],
        ];
        if ($custom) {
            $rules['label']  = 'required|string|max:30';
            $rules['symbol'] = 'required|string|max:4';
        }

        $rateMessage = "Enter how many rupees one {$code} is worth.";
        Validator::make(['code' => $code] + $request->only(['rate', 'label', 'symbol']), $rules, [
            'code.regex'      => 'Give it a code of 2 to 5 letters, like THB.',
            'code.not_in'     => 'The rupee is always worth one rupee.',
            'rate.*'          => $rateMessage,
            'label.required'  => 'Give the currency a name.',
            'label.max'       => 'Keep the name to 30 characters.',
            'symbol.required' => 'Pick a symbol, or type one — the code works too.',
            'symbol.max'      => 'Keep the symbol to 4 characters.',
        ])->validate();

        $currency = UserCurrency::updateOrCreate(
            ['user_id' => $user->id, 'code' => $code],
            [
                'rate'   => $request->rate,
                // A built-in code's name and symbol are fixed; only its rate is the user's.
                'label'  => $custom ? trim($request->label) : null,
                'symbol' => $custom ? trim($request->symbol) : null,
            ],
        );

        if ($custom && $currency->wasRecentlyCreated) {
            ActivityMail::send($user, "Currency added: {$code}", 'You added a currency.', [
                'Currency' => "{$currency->label} ({$code})",
                'Symbol'   => $currency->symbol,
                'Rate'     => "1 {$code} = ₹" . rtrim(rtrim(number_format((float) $currency->rate, 6, '.', ''), '0'), '.'),
            ]);
        }

        return response()->json(['data' => $currency->toApiShape()]);
    }

    public function destroy(Request $request, string $code)
    {
        $user = $request->user();
        $user->userCurrencies()->where('code', $code)->delete();

        // A removed currency cannot stay selected.
        if (! Currencies::isBuiltIn($code) && $user->currency === $code) {
            $user->update(['currency' => Currencies::BASE]);
        }

        return response()->noContent();
    }
}
