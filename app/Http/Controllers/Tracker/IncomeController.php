<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tracker\Concerns\ReadsAmounts;
use App\Models\AppIncome;
use App\Support\ActivityMail;
use App\Support\DisplayCurrency;
use App\Support\PaymentMethods;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The app's income form: new, edit and delete. Same rules as the API. */
class IncomeController extends Controller
{
    use ReadsAmounts;

    public function create(Request $request)
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, AppIncome $income)
    {
        $this->authorizeOwner($request, $income);
        return $this->form($request, $income);
    }

    public function store(Request $request)
    {
        $user   = $request->user();
        $money  = DisplayCurrency::for($user);
        $income = AppIncome::create($this->validated($request, $money, null) + ['user_id' => $user->id]);
        ActivityMail::incomeAdded($user, $income);

        return redirect($this->back($request, route('dashboard')))
            ->with('success', 'Income of ' . $money->format($income->amount_minor) . " added · {$income->source}")
            ->with('success_link', route('transactions.index', ['month' => $income->received_at->format('Y-m')]));
    }

    public function update(Request $request, AppIncome $income)
    {
        $this->authorizeOwner($request, $income);
        $income->update($this->validated($request, DisplayCurrency::for($request->user()), $income));

        return redirect($this->back($request, route('transactions.index', ['month' => $income->received_at->format('Y-m')])))
            ->with('success', 'Income updated');
    }

    public function destroy(Request $request, AppIncome $income)
    {
        $this->authorizeOwner($request, $income);
        $income->delete();

        return redirect($this->back($request, route('transactions.index')))->with('success', 'Income deleted');
    }

    private function form(Request $request, ?AppIncome $income)
    {
        return view('tracker.income-form', [
            'money'  => DisplayCurrency::for($request->user()),
            'income' => $income,
            'back'   => $this->back($request->merge(['back' => url()->previous()]), route('dashboard')),
        ]);
    }

    private function validated(Request $request, DisplayCurrency $money, ?AppIncome $income): array
    {
        $request->merge(['source' => trim((string) $request->source)]);
        $request->validate([
            'amount'         => ['required', $this->amountRule($money, $income?->amount_minor, 'Enter an amount greater than zero.')],
            'source'         => 'required|max:40',
            'received_at'    => 'required|date_format:Y-m-d|before_or_equal:' . now()->addDay()->toDateString(),
            'payment_method' => ['nullable', Rule::in(PaymentMethods::all())],
            'note'           => 'nullable|string|max:120',
        ], [
            'amount.required'   => 'Enter an amount greater than zero.',
            'source.required'   => 'Say where this income came from.',
            'source.max'        => 'Keep the source to 40 characters.',
            'received_at.*'     => 'Pick a day that is not in the future.',
            'payment_method.in' => PaymentMethods::MESSAGE,
            'note.max'          => 'Keep the note to 120 characters.',
        ]);

        return [
            'amount_minor'   => $this->amountValue($request->amount, $money, $income?->amount_minor),
            'source'         => $request->source,
            'note'           => trim((string) $request->note) ?: null,
            'payment_method' => $request->payment_method ?: null,
            'received_at'    => $request->received_at,
        ];
    }

    private function authorizeOwner(Request $request, AppIncome $income): void
    {
        if ($income->user_id !== $request->user()->id) abort(404);
    }
}
