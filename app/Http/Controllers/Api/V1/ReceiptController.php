<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppExpense;
use App\Support\Receipts;
use Illuminate\Http\Request;

/** An expense's receipt photo: attach (or replace), read and remove. Files are private (App\Support\Receipts). */
class ReceiptController extends Controller
{
    /** The image bytes, with an image/* Content-Type; 404 when there is none. */
    public function show(Request $request, AppExpense $expense)
    {
        $this->authorizeOwner($expense, $request->user());

        return Receipts::response($expense);
    }

    /** Body: { "receipt": "data:image/jpeg;base64,…" } — JPEG, PNG or WebP, at most 5 MB. */
    public function store(Request $request, AppExpense $expense)
    {
        $this->authorizeOwner($expense, $request->user());
        $request->validate(['receipt' => 'required|string'], ['receipt.*' => Receipts::MESSAGE]);

        Receipts::store($expense, $request->receipt);

        return response()->json(['data' => ['has_receipt' => true]]);
    }

    public function destroy(Request $request, AppExpense $expense)
    {
        $this->authorizeOwner($expense, $request->user());
        Receipts::remove($expense);

        return response()->noContent();
    }

    private function authorizeOwner(AppExpense $expense, $user): void
    {
        if ($expense->user_id !== $user->id) abort(404, 'Not found.');
    }
}
