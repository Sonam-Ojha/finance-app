<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BadDebt;
use Illuminate\Http\Request;

class BadDebtController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            BadDebt::where('user_id', $request->user()->id)
                ->when($request->status, fn($q) => $q->where('status', $request->status))
                ->latest('date_given')->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'person_name'         => 'required|string',
            'mobile_number'       => 'nullable|string',
            'amount'              => 'required|numeric|min:0',
            'date_given'          => 'required|date',
            'reason'              => 'nullable|string',
            'expected_return_date'=> 'nullable|date',
            'status'              => 'nullable|in:pending,partial_received,received,written_off',
            'received_amount'     => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        if (! isset($data['received_amount'])) $data['received_amount'] = 0;
        return response()->json(BadDebt::create($data), 201);
    }

    public function show(Request $request, BadDebt $badDebt)
    {
        $this->authorizeOwner($badDebt, $request->user());
        return response()->json($badDebt);
    }

    public function update(Request $request, BadDebt $badDebt)
    {
        $this->authorizeOwner($badDebt, $request->user());
        $data = $request->validate([
            'person_name'         => 'sometimes|string',
            'mobile_number'       => 'nullable|string',
            'amount'              => 'sometimes|numeric|min:0',
            'date_given'          => 'sometimes|date',
            'reason'              => 'nullable|string',
            'expected_return_date'=> 'nullable|date',
            'status'              => 'nullable|in:pending,partial_received,received,written_off',
            'received_amount'     => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
        ]);
        $badDebt->update($data);
        return response()->json($badDebt);
    }

    public function destroy(Request $request, BadDebt $badDebt)
    {
        $this->authorizeOwner($badDebt, $request->user());
        $badDebt->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    private function authorizeOwner(BadDebt $badDebt, $user)
    {
        if ($badDebt->user_id !== $user->id) abort(403);
    }
}
