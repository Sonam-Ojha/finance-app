<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Models\UserCurrency;
use App\Support\ActivityMail;
use App\Support\Currencies;
use App\Support\DisplayCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The app's Currency screen and currency form. Amounts stay in rupees; a
 * currency only changes how they are shown, at the rate the user sets.
 */
class CurrencyController extends Controller
{
    /** The app's symbol picker. */
    public const SYMBOLS = ['$', '€', '£', '¥', '₹', '₩', '₽', '₺', '฿', '₫', '₱', '₦', '₪', '₴', '৳', '₸', '₵', '₭', '₮', '₼', '₾', '﷼', 'Rs', 'Rp', 'RM', 'R$', 'R', 'kr', 'zł', 'Kč', 'Fr', 'S/'];

    public function index(Request $request)
    {
        $user = $request->user();

        return view('tracker.currency', [
            'currencies' => DisplayCurrency::allFor($user),
            'selected'   => DisplayCurrency::for($user)->code,
        ]);
    }

    /** Picks one — one without a rate goes to its form first. */
    public function select(Request $request)
    {
        $user = $request->user();
        $currency = collect(DisplayCurrency::allFor($user))->firstWhere('code', $request->input('currency'));
        abort_unless($currency, 404);

        if (! $currency->rate) {
            return redirect()->route('currency.edit', ['code' => $currency->code, 'select' => 1]);
        }

        $this->choose($user, $currency->code);

        return redirect()->route('currency.index')->with('success', "Amounts now show in {$currency->label}");
    }

    public function create()
    {
        return view('tracker.currency-form', ['currency' => null, 'symbols' => self::SYMBOLS, 'select' => false]);
    }

    public function edit(Request $request, string $code)
    {
        $currency = collect(DisplayCurrency::allFor($request->user()))->firstWhere('code', $code);
        abort_unless($currency && $code !== Currencies::BASE, 404);

        return view('tracker.currency-form', [
            'currency' => $currency,
            'symbols'  => self::SYMBOLS,
            'select'   => $request->boolean('select'),
        ]);
    }

    /** Creates or updates — the same rules as PUT /me/currencies/{code}. */
    public function save(Request $request, ?string $code = null)
    {
        $user  = $request->user();
        $isNew = $code === null;
        $code  = $isNew ? strtoupper(trim((string) $request->input('code'))) : $code;
        $custom = ! Currencies::isBuiltIn($code);

        $rules = [
            'code' => ['required', 'regex:/^[A-Z]{2,5}$/', 'not_in:' . Currencies::BASE],
            'rate' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,9}(\.\d{1,6})?$/'],
        ];
        if ($isNew) {
            $rules['code'][] = function ($attr, $value, $fail) use ($user) {
                if (Currencies::isBuiltIn($value) || $user->userCurrencies()->where('code', $value)->exists()) {
                    $fail("{$value} is already in the list.");
                }
            };
        }
        if ($custom) {
            $rules['label']  = 'required|string|max:30';
            $rules['symbol'] = 'required|string|max:4';
        }

        Validator::make(['code' => $code] + $request->only(['rate', 'label', 'symbol']), $rules, [
            'code.required'   => 'Give it a code of 2 to 5 letters, like THB.',
            'code.regex'      => 'Give it a code of 2 to 5 letters, like THB.',
            'code.not_in'     => 'The rupee is always worth one rupee.',
            'rate.*'          => 'Enter how many rupees one ' . ($code ?: 'unit') . ' is worth.',
            'label.required'  => 'Give the currency a name.',
            'label.max'       => 'Keep the name to 30 characters.',
            'symbol.required' => 'Pick a symbol, or type one — the code works too.',
            'symbol.max'      => 'Keep the symbol to 4 characters.',
        ])->validate();

        $currency = UserCurrency::updateOrCreate(
            ['user_id' => $user->id, 'code' => $code],
            [
                'rate'   => $request->rate,
                'label'  => $custom ? trim($request->label) : null,
                'symbol' => $custom ? trim($request->symbol) : null,
            ],
        );
        if ($custom && $currency->wasRecentlyCreated) ActivityMail::currencyAdded($user, $currency);

        if ($request->boolean('select')) $this->choose($user, $code);

        return redirect()->route('currency.index')->with('success', $isNew ? "{$code} added" : "{$code} saved");
    }

    public function destroy(Request $request, string $code)
    {
        $user = $request->user();
        $row  = $user->userCurrencies()->where('code', $code)->first();
        abort_unless($row && ! Currencies::isBuiltIn($code), 404);

        // Amounts are kept in rupees, so a removed currency just stops being picked.
        if ($user->currency === $code) $this->choose($user, Currencies::BASE);
        $row->delete();

        return redirect()->route('currency.index')->with('success', "{$row->label} removed");
    }

    private function choose($user, string $code): void
    {
        $previous = $user->currency;
        $user->update(['currency' => $code]);
        if ($previous !== $code) ActivityMail::currencyChanged($user, $previous);
    }
}
