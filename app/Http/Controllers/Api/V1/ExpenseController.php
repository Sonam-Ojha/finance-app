<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppExpense;
use App\Models\Category;
use App\Support\ActivityMail;
use App\Support\Money;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    private function joinedShape(AppExpense $expense): array
    {
        return [
            'id'             => $expense->id,
            'amount_minor'   => $expense->amount_minor,
            'category_id'    => $expense->category_id,
            'note'           => $expense->note,
            'payment_method' => $expense->payment_method,
            'spent_at'       => $expense->spent_at->toDateString(),
            'category_name'  => $expense->category->name,
            'category_icon'  => $expense->category->icon,
            'category_color' => $expense->category->color,
        ];
    }

    private function rules(): array
    {
        return [
            'amount_minor'   => 'required|integer|min:1',
            'category_id'    => 'required|integer',
            'note'           => 'nullable|string|max:120',
            'payment_method' => ['nullable', Rule::in(PaymentMethods::all())],
            'spent_at'       => 'required|date_format:Y-m-d|before_or_equal:' . now()->addDay()->toDateString(),
        ];
    }

    public function index(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        [$year, $month] = explode('-', $request->month);
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end   = $start->copy()->addMonth();

        $expenses = AppExpense::with('category')
            ->where('user_id', $request->user()->id)
            ->where('spent_at', '>=', $start->toDateString())
            ->where('spent_at', '<',  $end->toDateString())
            ->orderByDesc('spent_at')->orderByDesc('id')
            ->get()
            ->map(fn($e) => $this->joinedShape($e));

        return response()->json(['data' => $expenses]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $uid  = $user->id;
        $request->validate($this->rules(), [
            'amount_minor.min'       => 'Enter an amount greater than zero.',
            'amount_minor.required'  => 'Enter an amount greater than zero.',
            'category_id.required'   => 'Pick a category for this expense.',
            'payment_method.in'      => PaymentMethods::MESSAGE,
        ]);

        $category = Category::where('id', $request->category_id)->where('user_id', $uid)->first();
        if (! $category) {
            return response()->json(['message' => 'Pick a category for this expense.', 'errors' => ['category_id' => ['Pick a category for this expense.']]], 422);
        }

        $expense = AppExpense::create([
            'user_id'        => $uid,
            'amount_minor'   => $request->amount_minor,
            'category_id'    => $request->category_id,
            'note'           => $request->note ? (trim($request->note) ?: null) : null,
            'payment_method' => $request->payment_method,
            'spent_at'       => $request->spent_at,
        ]);

        ActivityMail::send($user, 'Expense added: ' . Money::inr($expense->amount_minor), 'You added an expense.', [
            'Amount'    => Money::inr($expense->amount_minor),
            'Category'  => trim("{$category->icon} {$category->name}"),
            'Date'      => $expense->spent_at->format('j M Y'),
            'Paid with' => PaymentMethods::label($expense->payment_method),
            'Note'      => $expense->note,
        ]);

        return response()->json(['data' => $this->joinedShape($expense->load('category'))], 201);
    }

    public function show(Request $request, AppExpense $expense)
    {
        $this->authorizeOwner($expense, $request->user());
        return response()->json(['data' => [
            'id'             => $expense->id,
            'amount_minor'   => $expense->amount_minor,
            'category_id'    => $expense->category_id,
            'note'           => $expense->note,
            'payment_method' => $expense->payment_method,
            'spent_at'       => $expense->spent_at->toDateString(),
        ]]);
    }

    public function update(Request $request, AppExpense $expense)
    {
        $this->authorizeOwner($expense, $request->user());
        $uid = $request->user()->id;
        $request->validate($this->rules(), [
            'amount_minor.min'     => 'Enter an amount greater than zero.',
            'category_id.required' => 'Pick a category for this expense.',
            'payment_method.in'    => PaymentMethods::MESSAGE,
        ]);

        $category = Category::where('id', $request->category_id)->where('user_id', $uid)->first();
        if (! $category) {
            return response()->json(['message' => 'Pick a category for this expense.', 'errors' => ['category_id' => ['Pick a category for this expense.']]], 422);
        }

        $changes = [
            'amount_minor' => $request->amount_minor,
            'category_id'  => $request->category_id,
            'note'         => $request->note ? (trim($request->note) ?: null) : null,
            'spent_at'     => $request->spent_at,
        ];
        // Older app versions send no payment_method at all; their edits must
        // not wipe one. An explicit null clears it.
        if ($request->has('payment_method')) {
            $changes['payment_method'] = $request->payment_method;
        }
        $expense->update($changes);

        return response()->json(['data' => $this->joinedShape($expense->load('category'))]);
    }

    public function destroy(Request $request, AppExpense $expense)
    {
        $this->authorizeOwner($expense, $request->user());
        $expense->delete();
        return response()->noContent();
    }

    private function authorizeOwner(AppExpense $expense, $user)
    {
        if ($expense->user_id !== $user->id) abort(404, 'Not found.');
    }
}
