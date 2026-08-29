<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppIncome;
use Carbon\Carbon;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        [$year, $month] = explode('-', $request->month);
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end   = $start->copy()->addMonth();

        $incomes = AppIncome::where('user_id', $request->user()->id)
            ->where('received_at', '>=', $start->toDateString())
            ->where('received_at', '<',  $end->toDateString())
            ->orderByDesc('received_at')->orderByDesc('id')
            ->get();

        return response()->json(['data' => $incomes]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'amount_minor' => 'required|integer|min:1',
            'source'       => 'required|max:40',
            'note'         => 'nullable|string|max:120',
            'received_at'  => 'required|date_format:Y-m-d|before_or_equal:' . now()->addDay()->toDateString(),
        ], [
            'amount_minor.min'    => 'Enter an amount greater than zero.',
            'amount_minor.required' => 'Enter an amount greater than zero.',
            'source.required'     => 'Say where this income came from.',
        ]);

        $income = AppIncome::create([
            'user_id'      => $request->user()->id,
            'amount_minor' => $request->amount_minor,
            'source'       => trim($request->source),
            'note'         => $request->note ? (trim($request->note) ?: null) : null,
            'received_at'  => $request->received_at,
        ]);

        return response()->json(['data' => $income], 201);
    }

    public function show(Request $request, AppIncome $income)
    {
        $this->authorizeOwner($income, $request->user());
        return response()->json(['data' => $income]);
    }

    public function update(Request $request, AppIncome $income)
    {
        $this->authorizeOwner($income, $request->user());
        $request->validate([
            'amount_minor' => 'required|integer|min:1',
            'source'       => 'required|max:40',
            'note'         => 'nullable|string|max:120',
            'received_at'  => 'required|date_format:Y-m-d|before_or_equal:' . now()->addDay()->toDateString(),
        ], [
            'amount_minor.min' => 'Enter an amount greater than zero.',
            'source.required'  => 'Say where this income came from.',
        ]);

        $income->update([
            'amount_minor' => $request->amount_minor,
            'source'       => trim($request->source),
            'note'         => $request->note ? (trim($request->note) ?: null) : null,
            'received_at'  => $request->received_at,
        ]);

        return response()->json(['data' => $income->fresh()]);
    }

    public function destroy(Request $request, AppIncome $income)
    {
        $this->authorizeOwner($income, $request->user());
        $income->delete();
        return response()->noContent();
    }

    private function authorizeOwner(AppIncome $income, $user)
    {
        if ($income->user_id !== $user->id) abort(404, 'Not found.');
    }
}
