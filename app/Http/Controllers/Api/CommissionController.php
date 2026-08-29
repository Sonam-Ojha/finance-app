<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Commission::where('user_id', $request->user()->id)
                ->when($request->status, fn($q) => $q->where('status', $request->status))
                ->latest('date')->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'              => 'required|date',
            'source_name'       => 'required|string',
            'client_name'       => 'nullable|string',
            'product_name'      => 'nullable|string',
            'commission_amount' => 'required|numeric|min:0',
            'status'            => 'nullable|in:pending,received',
            'notes'             => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        return response()->json(Commission::create($data), 201);
    }

    public function show(Request $request, Commission $commission)
    {
        $this->authorizeOwner($commission, $request->user());
        return response()->json($commission);
    }

    public function update(Request $request, Commission $commission)
    {
        $this->authorizeOwner($commission, $request->user());
        $data = $request->validate([
            'date'              => 'sometimes|date',
            'source_name'       => 'sometimes|string',
            'client_name'       => 'nullable|string',
            'product_name'      => 'nullable|string',
            'commission_amount' => 'sometimes|numeric|min:0',
            'status'            => 'nullable|in:pending,received',
            'notes'             => 'nullable|string',
        ]);
        $commission->update($data);
        return response()->json($commission);
    }

    public function destroy(Request $request, Commission $commission)
    {
        $this->authorizeOwner($commission, $request->user());
        $commission->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    private function authorizeOwner(Commission $commission, $user)
    {
        if ($commission->user_id !== $user->id) abort(403);
    }
}
