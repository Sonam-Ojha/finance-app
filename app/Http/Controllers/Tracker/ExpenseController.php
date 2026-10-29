<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tracker\Concerns\ReadsAmounts;
use App\Models\AppExpense;
use App\Models\Category;
use App\Support\ActivityMail;
use App\Support\BudgetAlertPush;
use App\Support\DisplayCurrency;
use App\Support\PaymentMethods;
use App\Support\Receipts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The app's expense form: new, edit and delete. Same rules as the API. */
class ExpenseController extends Controller
{
    use ReadsAmounts;

    public function create(Request $request)
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, AppExpense $expense)
    {
        $this->authorizeOwner($request, $expense);
        return $this->form($request, $expense);
    }

    public function store(Request $request)
    {
        $user  = $request->user();
        $money = DisplayCurrency::for($user);
        $photo = $this->takeReceipt($request);
        $data  = $this->validated($request, $money, null);
        $receipt = $photo !== null ? Receipts::decode($photo) : null;

        $expense  = AppExpense::create($data + ['user_id' => $user->id]);
        if ($receipt) Receipts::put($expense, ...$receipt);
        $category = Category::find($expense->category_id);
        ActivityMail::expenseAdded($user, $expense, $category);
        BudgetAlertPush::afterExpense($user, $expense->spent_at);

        return redirect($this->back($request, route('dashboard')))
            ->with('success', 'Expense of ' . $money->format($expense->amount_minor) . " added · {$category->name}")
            ->with('success_link', route('transactions.index', ['month' => $expense->spent_at->format('Y-m')]));
    }

    public function update(Request $request, AppExpense $expense)
    {
        $this->authorizeOwner($request, $expense);
        $money = DisplayCurrency::for($request->user());
        $photo = $this->takeReceipt($request);
        $data  = $this->validated($request, $money, $expense);
        $receipt = $photo !== null ? Receipts::decode($photo) : null;

        $expense->update($data);
        if ($receipt) {
            Receipts::put($expense, ...$receipt);
        } elseif ($request->boolean('remove_receipt')) {
            Receipts::remove($expense);
        }
        BudgetAlertPush::afterExpense($request->user(), $expense->spent_at);

        return redirect($this->back($request, route('transactions.index', ['month' => $expense->spent_at->format('Y-m')])))
            ->with('success', 'Expense updated');
    }

    public function destroy(Request $request, AppExpense $expense)
    {
        $this->authorizeOwner($request, $expense);
        $expense->delete();

        return redirect($this->back($request, route('transactions.index')))->with('success', 'Expense deleted');
    }

    /** The receipt photo, for its owner only. */
    public function receipt(Request $request, AppExpense $expense)
    {
        $this->authorizeOwner($request, $expense);

        return Receipts::response($expense);
    }

    /**
     * The receipt photo picked on the form (a data URI, shrunk in the
     * browser by receiptToDataUri), or null. Taken out of the request, so a
     * form sent back with errors never flashes megabytes into the session.
     */
    private function takeReceipt(Request $request): ?string
    {
        $photo = $request->input('receipt');
        $request->request->remove('receipt');

        return is_string($photo) && trim($photo) !== '' ? $photo : null;
    }

    private function form(Request $request, ?AppExpense $expense)
    {
        $user = $request->user();

        return view('tracker.expense-form', [
            'money'      => DisplayCurrency::for($user),
            'expense'    => $expense,
            'categories' => Category::where('user_id', $user->id)->orderBy('sort_order')->orderBy('name')->get(),
            // A category just made from this form comes back picked.
            'picked'     => (int) $request->query('category_id'),
            'back'       => $this->back($request->merge(['back' => url()->previous()]), route('dashboard')),
        ]);
    }

    private function validated(Request $request, DisplayCurrency $money, ?AppExpense $expense): array
    {
        $uid = $request->user()->id;
        $request->validate([
            'amount'         => ['required', $this->amountRule($money, $expense?->amount_minor, 'Enter an amount greater than zero.')],
            'category_id'    => ['required', 'integer', Rule::exists('categories', 'id')->where('user_id', $uid)],
            'spent_at'       => 'required|date_format:Y-m-d|before_or_equal:' . now()->addDay()->toDateString(),
            'payment_method' => ['nullable', Rule::in(PaymentMethods::all())],
            'note'           => 'nullable|string|max:120',
        ], [
            'amount.required'   => 'Enter an amount greater than zero.',
            'category_id.*'     => 'Pick a category for this expense.',
            'spent_at.*'        => 'Pick a day that is not in the future.',
            'payment_method.in' => PaymentMethods::MESSAGE,
            'note.max'          => 'Keep the note to 120 characters.',
        ]);

        return [
            'amount_minor'   => $this->amountValue($request->amount, $money, $expense?->amount_minor),
            'category_id'    => (int) $request->category_id,
            'note'           => trim((string) $request->note) ?: null,
            'payment_method' => $request->payment_method ?: null,
            'spent_at'       => $request->spent_at,
        ];
    }

    private function authorizeOwner(Request $request, AppExpense $expense): void
    {
        if ($expense->user_id !== $request->user()->id) abort(404);
    }
}
