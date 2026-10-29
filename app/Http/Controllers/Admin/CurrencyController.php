<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Support\AdminActivity;
use App\Support\Currencies;
use App\Support\PaymentMethods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin › Currencies: the currencies the app (GET /api/v1/currencies) and
 * the website offer. Amounts are always stored in rupees, so INR is fixed.
 * Payment methods are shown for reference; they are defined in code.
 */
class CurrencyController extends Controller
{
    public function index()
    {
        return $this->page(null);
    }

    public function edit(Currency $currency)
    {
        abort_if($currency->isBase(), 404);

        return $this->page($currency);
    }

    public function store(Request $request)
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $this->validated($request, null);
        $currency = Currency::create($data + [
            'code'       => $request->input('code'),
            'sort_order' => (Currency::max('sort_order') ?? -1) + 1,
        ]);
        AdminActivity::record('currency.added', "added the currency {$currency->code}" . ($currency->enabled ? '' : ' (switched off)'), $currency);

        return redirect()->route('admin.currencies.index')
            ->with('success', "{$currency->code} added." . ($currency->enabled ? ' The app and the website offer it now.' : ' Switch it on when it should be offered.'));
    }

    public function update(Request $request, Currency $currency)
    {
        abort_if($currency->isBase(), 404);
        $currency->update($this->validated($request, $currency));
        AdminActivity::record('currency.updated', "updated the currency {$currency->code}", $currency);

        return redirect()->route('admin.currencies.index')->with('success', "{$currency->code} saved.");
    }

    /** On offer, or not. INR always is. */
    public function toggle(Currency $currency)
    {
        if ($currency->isBase()) {
            return back()->with('error', 'Rupees cannot be switched off: every amount is stored in them.');
        }

        $currency->update(['enabled' => ! $currency->enabled]);
        AdminActivity::record($currency->enabled ? 'currency.enabled' : 'currency.disabled',
            ($currency->enabled ? 'switched on' : 'switched off') . " the currency {$currency->code}", $currency);

        return back()->with('success', $currency->enabled
            ? "{$currency->code} is on offer again."
            : "{$currency->code} is switched off. People who already set a rate for it keep it; nobody else can pick it.");
    }

    public function destroy(Currency $currency)
    {
        if ($currency->isBase()) {
            return back()->with('error', 'Rupees cannot be deleted: every amount is stored in them.');
        }

        $currency->delete();
        AdminActivity::record('currency.deleted', "deleted the currency {$currency->code}", $currency);

        return redirect()->route('admin.currencies.index')
            ->with('success', "{$currency->code} deleted. People who already set a rate for it keep it as their own currency.");
    }

    private function page(?Currency $editing)
    {
        $currencies = Currency::query()->orderBy('sort_order')->orderBy('id')->get();
        // How many people read amounts in each: a count, nothing they recorded.
        $readers = DB::table('users')
            ->selectRaw("COALESCE(currency, 'INR') as code, COUNT(*) as c")
            ->groupBy('code')
            ->pluck('c', 'code');

        return view('admin.currencies.index', [
            'currencies'     => $currencies,
            'readers'        => $readers,
            'editing'        => $editing,
            'paymentMethods' => PaymentMethods::LABELS,
        ]);
    }

    private function validated(Request $request, ?Currency $currency): array
    {
        $request->merge([
            'name'         => trim((string) $request->input('name')),
            'symbol'       => trim((string) $request->input('symbol')),
            'default_rate' => trim(str_replace(',', '', (string) $request->input('default_rate'))),
        ]);

        $rules = [
            'name'         => 'required|string|max:40',
            'symbol'       => 'required|string|max:8',
            'decimals'     => 'required|integer|between:0,3',
            'default_rate' => ['bail', 'nullable', 'regex:/^\d{1,9}(\.\d{1,6})?$/', fn ($attribute, $value, $fail) => (float) $value > 0 || $fail(self::RATE_MESSAGE)],
            'enabled'      => 'nullable|boolean',
        ];
        if (! $currency) {
            $rules['code'] = ['required', 'regex:/^[A-Z]{2,5}$/', Rule::unique('currencies', 'code')];
        }

        $request->validate($rules, [
            'code.required'   => 'Give it a code of 2 to 5 letters, like THB.',
            'code.regex'      => 'Give it a code of 2 to 5 letters, like THB.',
            'code.unique'     => 'That currency is already in the list.',
            'name.required'   => 'Give the currency a name.',
            'name.max'        => 'Keep the name to 40 characters.',
            'symbol.required' => 'Give it a symbol — the code works too.',
            'symbol.max'      => 'Keep the symbol to 8 characters.',
            'decimals.*'      => 'Pick 0 to 3 decimal places.',
            'default_rate.*'  => self::RATE_MESSAGE,
        ]);

        $rate = $request->input('default_rate');
        $data = [
            'name'         => $request->input('name'),
            'symbol'       => $request->input('symbol'),
            'decimals'     => (int) $request->input('decimals'),
            'default_rate' => $rate === null || $rate === '' ? null : $rate,
        ];

        // A new one may start switched off; after that the list's switch turns it on and off.
        return $currency ? $data : $data + ['enabled' => $request->boolean('enabled')];
    }

    private const RATE_MESSAGE = 'Enter how many rupees one unit is worth, like 83.25, or leave it empty.';
}
