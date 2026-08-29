<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Loan::where('user_id', $request->user()->id)
                ->when($request->status, fn($q) => $q->where('status', $request->status))
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'loan_name'          => 'required|string',
            'bank_or_person_name'=> 'required|string',
            'total_amount'       => 'required|numeric|min:0',
            'interest_rate'      => 'nullable|numeric|min:0',
            'emi_amount'         => 'nullable|numeric|min:0',
            'start_date'         => 'required|date',
            'end_date'           => 'nullable|date',
            'pending_amount'     => 'nullable|numeric|min:0',
            'status'             => 'nullable|in:active,closed',
            'notes'              => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        if (! isset($data['pending_amount'])) $data['pending_amount'] = $data['total_amount'];
        return response()->json(Loan::create($data), 201);
    }

    public function show(Request $request, Loan $loan)
    {
        $this->authorizeOwner($loan, $request->user());
        return response()->json($loan->load('payments'));
    }

    public function update(Request $request, Loan $loan)
    {
        $this->authorizeOwner($loan, $request->user());
        $data = $request->validate([
            'loan_name'          => 'sometimes|string',
            'bank_or_person_name'=> 'sometimes|string',
            'total_amount'       => 'sometimes|numeric|min:0',
            'interest_rate'      => 'nullable|numeric|min:0',
            'emi_amount'         => 'nullable|numeric|min:0',
            'start_date'         => 'sometimes|date',
            'end_date'           => 'nullable|date',
            'pending_amount'     => 'nullable|numeric|min:0',
            'status'             => 'nullable|in:active,closed',
            'notes'              => 'nullable|string',
        ]);
        $loan->update($data);
        return response()->json($loan);
    }

    public function destroy(Request $request, Loan $loan)
    {
        $this->authorizeOwner($loan, $request->user());
        $loan->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function storePayment(Request $request, Loan $loan)
    {
        $this->authorizeOwner($loan, $request->user());
        $data = $request->validate([
            'amount'             => 'required|numeric|min:0',
            'payment_date'       => 'required|date',
            'payment_mode'       => 'nullable|string',
            'note'               => 'nullable|string',
        ]);
        $payment = $loan->payments()->create($data);
        $loan->decrement('pending_amount', $data['amount']);
        if ($loan->fresh()->pending_amount <= 0) {
            $loan->update(['status' => 'closed', 'pending_amount' => 0]);
        }
        return response()->json($payment, 201);
    }

    private function authorizeOwner(Loan $loan, $user)
    {
        if ($loan->user_id !== $user->id) abort(403);
    }
}
