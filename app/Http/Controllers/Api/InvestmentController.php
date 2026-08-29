<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use Illuminate\Http\Request;

class InvestmentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Investment::where('user_id', $request->user()->id)
                ->when($request->category, fn($q) => $q->where('category', $request->category))
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'investment_name' => 'required|string',
            'category'        => 'required|string',
            'date'            => 'required|date',
            'amount_invested' => 'required|numeric|min:0',
            'current_value'   => 'nullable|numeric|min:0',
            'maturity_date'   => 'nullable|date',
            'returns'         => 'nullable|numeric',
            'notes'           => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        return response()->json(Investment::create($data), 201);
    }

    public function show(Request $request, Investment $investment)
    {
        $this->authorizeOwner($investment, $request->user());
        return response()->json($investment);
    }

    public function update(Request $request, Investment $investment)
    {
        $this->authorizeOwner($investment, $request->user());
        $data = $request->validate([
            'investment_name' => 'sometimes|string',
            'category'        => 'sometimes|string',
            'date'            => 'sometimes|date',
            'amount_invested' => 'sometimes|numeric|min:0',
            'current_value'   => 'nullable|numeric|min:0',
            'maturity_date'   => 'nullable|date',
            'returns'         => 'nullable|numeric',
            'notes'           => 'nullable|string',
        ]);
        $investment->update($data);
        return response()->json($investment);
    }

    public function destroy(Request $request, Investment $investment)
    {
        $this->authorizeOwner($investment, $request->user());
        $investment->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    private function authorizeOwner(Investment $investment, $user)
    {
        if ($investment->user_id !== $user->id) abort(403);
    }
}
