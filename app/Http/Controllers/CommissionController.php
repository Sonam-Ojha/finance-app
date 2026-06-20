<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Commission;

class CommissionController extends Controller
{
    public function index()
    {
        $commissions = Commission::where('user_id', Auth::id())->latest('date')->paginate(15);
        $totalReceived = Commission::where('user_id', Auth::id())->where('status', 'received')->sum('commission_amount');
        $totalPending = Commission::where('user_id', Auth::id())->where('status', 'pending')->sum('commission_amount');
        return view('commission.index', compact('commissions', 'totalReceived', 'totalPending'));
    }

    public function create()
    {
        return view('commission.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'source_name' => 'required|string|max:255',
            'client_name' => 'nullable|string|max:255',
            'product_name' => 'nullable|string|max:255',
            'commission_amount' => 'required|numeric|min:0.01',
            'status' => 'required|in:received,pending',
            'notes' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        Commission::create($data);
        return redirect()->route('commission.index')->with('success', 'Commission added!');
    }

    public function edit(Commission $commission)
    {
        abort_if($commission->user_id !== Auth::id(), 403);
        return view('commission.edit', compact('commission'));
    }

    public function update(Request $request, Commission $commission)
    {
        abort_if($commission->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'date' => 'required|date',
            'source_name' => 'required|string|max:255',
            'client_name' => 'nullable|string|max:255',
            'product_name' => 'nullable|string|max:255',
            'commission_amount' => 'required|numeric|min:0.01',
            'status' => 'required|in:received,pending',
            'notes' => 'nullable|string',
        ]);
        $commission->update($data);
        return redirect()->route('commission.index')->with('success', 'Commission updated!');
    }

    public function destroy(Commission $commission)
    {
        abort_if($commission->user_id !== Auth::id(), 403);
        $commission->delete();
        return redirect()->route('commission.index')->with('success', 'Commission deleted.');
    }

    public function show(Commission $commission)
    {
        abort_if($commission->user_id !== Auth::id(), 403);
        return view('commission.show', compact('commission'));
    }
}
