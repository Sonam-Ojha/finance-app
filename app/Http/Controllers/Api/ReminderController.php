<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Reminder::where('user_id', $request->user()->id)
                ->when($request->has('is_done'), fn($q) => $q->where('is_done', (bool) $request->is_done))
                ->orderBy('due_date')->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'    => 'required|string',
            'title'   => 'required|string',
            'due_date'=> 'required|date',
            'amount'  => 'nullable|numeric|min:0',
            'notes'   => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;
        $data['is_done'] = false;
        return response()->json(Reminder::create($data), 201);
    }

    public function show(Request $request, Reminder $reminder)
    {
        $this->authorizeOwner($reminder, $request->user());
        return response()->json($reminder);
    }

    public function update(Request $request, Reminder $reminder)
    {
        $this->authorizeOwner($reminder, $request->user());
        $data = $request->validate([
            'type'    => 'sometimes|string',
            'title'   => 'sometimes|string',
            'due_date'=> 'sometimes|date',
            'amount'  => 'nullable|numeric|min:0',
            'notes'   => 'nullable|string',
            'is_done' => 'sometimes|boolean',
        ]);
        $reminder->update($data);
        return response()->json($reminder);
    }

    public function destroy(Request $request, Reminder $reminder)
    {
        $this->authorizeOwner($reminder, $request->user());
        $reminder->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function markDone(Request $request, Reminder $reminder)
    {
        $this->authorizeOwner($reminder, $request->user());
        $reminder->update(['is_done' => true]);
        return response()->json($reminder);
    }

    private function authorizeOwner(Reminder $reminder, $user)
    {
        if ($reminder->user_id !== $user->id) abort(403);
    }
}
