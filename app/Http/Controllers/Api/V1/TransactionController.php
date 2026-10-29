<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PaymentMethods;
use App\Support\TransactionSearch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Expenses and incomes as one list; the query is App\Support\TransactionSearch. */
class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $uid = $request->user()->id;
        $request->validate([
            'search'           => 'nullable|string|max:100',
            'type'             => 'nullable|in:expense,income',
            'category_id'      => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $uid)],
            'payment_method'   => ['nullable', Rule::in(PaymentMethods::all())],
            'date_from'        => 'nullable|date_format:Y-m-d',
            // Compared only when there is a start: with none, Laravel would
            // read "date_from" itself as a date and reject every end date.
            'date_to'          => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
            'min_amount_minor' => 'nullable|integer|min:1',
            'max_amount_minor' => 'nullable|integer|min:1',
            'sort'             => 'nullable|in:' . implode(',', array_keys(TransactionSearch::SORTS)),
            'page'             => 'nullable|integer|min:1',
            'per_page'         => 'nullable|integer|min:1|max:100',
        ], [
            'category_id.exists' => 'Pick one of your own categories.',
            'payment_method.in'  => PaymentMethods::MESSAGE,
            'date_to.after_or_equal' => 'The end date cannot be before the start date.',
        ]);

        $page = TransactionSearch::paginate(
            $uid,
            $request->only(['search', 'type', 'category_id', 'payment_method', 'date_from', 'date_to', 'min_amount_minor', 'max_amount_minor', 'sort']),
            (int) $request->input('per_page', 50),
            (int) $request->input('page', 1),
        );

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }
}
