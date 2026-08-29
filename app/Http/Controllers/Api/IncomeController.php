<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $incomes = Income::where('user_id', $request->user()->id)
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->month, fn($q) => $q->whereMonth('date', $request->month))
            ->when($request->year, fn($q) => $q->whereYear('date', $request->year))
            ->latest('date')->paginate(20);
        return response()->json($incomes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'            => 'required|in:salary,lic_commission,business,received_from,other',
            'date'            => 'required|date',
            'amount'          => 'required|numeric|min:0',
            'payment_mode'    => 'nullable|string',
            'note'            => 'nullable|string',
            'company_name'    => 'nullable|string',
            'salary_month'    => 'nullable|string',
            'client_name'     => 'nullable|string',
            'policy_number'   => 'nullable|string',
            'plan_name'       => 'nullable|string',
            'commission_type' => 'nullable|string',
            'business_name'   => 'nullable|string',
            'person_name'     => 'nullable|string',
            'mobile_number'   => 'nullable|string',
            'reason'          => 'nullable|string',
            'category_name'   => 'nullable|string',
            'description'     => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        $income = Income::create($data);
        return response()->json($income, 201);
    }

    public function show(Request $request, Income $income)
    {
        $this->authorizeOwner($income, $request->user());
        return response()->json($income);
    }

    public function update(Request $request, Income $income)
    {
        $this->authorizeOwner($income, $request->user());
        $data = $request->validate([
            'type'            => 'sometimes|in:salary,lic_commission,business,received_from,other',
            'date'            => 'sometimes|date',
            'amount'          => 'sometimes|numeric|min:0',
            'payment_mode'    => 'nullable|string',
            'note'            => 'nullable|string',
            'company_name'    => 'nullable|string',
            'salary_month'    => 'nullable|string',
            'client_name'     => 'nullable|string',
            'policy_number'   => 'nullable|string',
            'plan_name'       => 'nullable|string',
            'commission_type' => 'nullable|string',
            'business_name'   => 'nullable|string',
            'person_name'     => 'nullable|string',
            'mobile_number'   => 'nullable|string',
            'reason'          => 'nullable|string',
            'category_name'   => 'nullable|string',
            'description'     => 'nullable|string',
        ]);
        $income->update($data);
        return response()->json($income);
    }

    public function destroy(Request $request, Income $income)
    {
        $this->authorizeOwner($income, $request->user());
        $income->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    private function authorizeOwner(Income $income, $user)
    {
        if ($income->user_id !== $user->id) abort(403);
    }
}
