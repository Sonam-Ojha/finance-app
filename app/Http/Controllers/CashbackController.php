<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Cashback;

class CashbackController extends Controller
{
    public function index()
    {
        $cashbacks = Cashback::where('user_id', Auth::id())->latest('date')->paginate(15);
        $totalReceived = Cashback::where('user_id', Auth::id())->where('status', 'received')->sum('amount');
        $totalPending = Cashback::where('user_id', Auth::id())->where('status', 'pending')->sum('amount');
        return view('cashback.index', compact('cashbacks', 'totalReceived', 'totalPending'));
    }

    public function create()
    {
        return view('cashback.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'platform_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'status' => 'required|in:received,pending',
            'notes' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        Cashback::create($data);
        return redirect()->route('cashback.index')->with('success', 'Cashback added!');
    }

    public function edit(Cashback $cashback)
    {
        abort_if($cashback->user_id !== Auth::id(), 403);
        return view('cashback.edit', compact('cashback'));
    }

    public function update(Request $request, Cashback $cashback)
    {
        abort_if($cashback->user_id !== Auth::id(), 403);
        $data = $request->validate([
            'date' => 'required|date',
            'platform_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'status' => 'required|in:received,pending',
            'notes' => 'nullable|string',
        ]);
        $cashback->update($data);
        return redirect()->route('cashback.index')->with('success', 'Cashback updated!');
    }

    public function destroy(Cashback $cashback)
    {
        abort_if($cashback->user_id !== Auth::id(), 403);
        $cashback->delete();
        return redirect()->route('cashback.index')->with('success', 'Cashback deleted.');
    }

    public function show(Cashback $cashback)
    {
        abort_if($cashback->user_id !== Auth::id(), 403);
        return view('cashback.show', compact('cashback'));
    }
}
