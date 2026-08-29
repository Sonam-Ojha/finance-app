<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cashback;
use Illuminate\Http\Request;

class CashbackController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Cashback::where('user_id', $request->user()->id)
                ->when($request->status, fn($q) => $q->where('status', $request->status))
                ->latest('date')->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'          => 'required|date',
            'platform_name' => 'required|string',
            'amount'        => 'required|numeric|min:0',
            'status'        => 'nullable|in:pending,received',
            'notes'         => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        return response()->json(Cashback::create($data), 201);
    }

    public function show(Request $request, Cashback $cashback)
    {
        $this->authorizeOwner($cashback, $request->user());
        return response()->json($cashback);
    }

    public function update(Request $request, Cashback $cashback)
    {
        $this->authorizeOwner($cashback, $request->user());
        $data = $request->validate([
            'date'          => 'sometimes|date',
            'platform_name' => 'sometimes|string',
            'amount'        => 'sometimes|numeric|min:0',
            'status'        => 'nullable|in:pending,received',
            'notes'         => 'nullable|string',
        ]);
        $cashback->update($data);
        return response()->json($cashback);
    }

    public function destroy(Request $request, Cashback $cashback)
    {
        $this->authorizeOwner($cashback, $request->user());
        $cashback->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    private function authorizeOwner(Cashback $cashback, $user)
    {
        if ($cashback->user_id !== $user->id) abort(403);
    }
}
