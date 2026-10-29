<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Support\ActivityMail;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    private const MONTH_RULE = ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'];
    private const AMOUNT_RULE = 'required|integer|min:1';
    private const AMOUNT_MESSAGES = [
        'amount_minor.required' => 'Budget must be a number greater than zero.',
        'amount_minor.integer'  => 'Budget must be a number greater than zero.',
        'amount_minor.min'      => 'Budget must be a number greater than zero.',
    ];
    private const DUPLICATE = 'This month already has that budget.';

    public function index(Request $request)
    {
        $request->validate(['month' => self::MONTH_RULE]);
        [$year, $month] = array_map('intval', explode('-', $request->month));

        $budgets = Budget::where('user_id', $request->user()->id)
            ->where('year', $year)->where('month', $month)
            ->orderBy('category_key')
            ->get()
            ->map(fn (Budget $budget) => $budget->toApiShape());

        return response()->json(['data' => $budgets]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'month'        => self::MONTH_RULE,
            'category_id'  => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $user->id)],
            'amount_minor' => self::AMOUNT_RULE,
        ], self::AMOUNT_MESSAGES + [
            'category_id.exists' => 'Pick one of your own categories.',
        ]);
        [$year, $month] = array_map('intval', explode('-', $request->month));
        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;

        $taken = Budget::where('user_id', $user->id)
            ->where('category_key', $categoryId ?? 0)
            ->where('year', $year)->where('month', $month)
            ->exists();
        if ($taken) return $this->duplicate();

        try {
            $budget = Budget::create([
                'user_id'      => $user->id,
                'category_id'  => $categoryId,
                'year'         => $year,
                'month'        => $month,
                'amount_minor' => $request->amount_minor,
            ]);
        } catch (QueryException $e) {
            // Two requests racing past the check above meet the unique index.
            if ($e->getCode() === '23000') return $this->duplicate();
            throw $e;
        }

        ActivityMail::budgetSet($user, $budget);

        return response()->json(['data' => $budget->toApiShape()], 201);
    }

    public function show(Request $request, Budget $budget)
    {
        $this->authorizeOwner($budget, $request->user());
        return response()->json(['data' => $budget->toApiShape()]);
    }

    public function update(Request $request, Budget $budget)
    {
        $this->authorizeOwner($budget, $request->user());
        $request->validate(['amount_minor' => self::AMOUNT_RULE], self::AMOUNT_MESSAGES);

        $budget->update(['amount_minor' => $request->amount_minor]);

        return response()->json(['data' => $budget->toApiShape()]);
    }

    public function destroy(Request $request, Budget $budget)
    {
        $this->authorizeOwner($budget, $request->user());
        $budget->delete();
        return response()->noContent();
    }

    private function duplicate()
    {
        return response()->json(['message' => self::DUPLICATE, 'errors' => ['month' => [self::DUPLICATE]]], 422);
    }

    private function authorizeOwner(Budget $budget, $user)
    {
        if ($budget->user_id !== $user->id) abort(404, 'Not found.');
    }
}
