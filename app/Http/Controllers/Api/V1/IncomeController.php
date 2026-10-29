<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppIncome;
use App\Support\ActivityMail;
use App\Support\ClientIds;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncomeController extends Controller
{
    private function rules(): array
    {
        return [
            'amount_minor'   => 'required|integer|min:1',
            'source'         => 'required|max:40',
            'note'           => 'nullable|string|max:120',
            'payment_method' => ['nullable', Rule::in(PaymentMethods::all())],
            'received_at'    => 'required|date_format:Y-m-d|before_or_equal:' . now()->addDay()->toDateString(),
            'client_id'      => 'nullable|string|max:64',
        ];
    }

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
        $user = $request->user();
        $request->validate($this->rules(), [
            'amount_minor.min'    => 'Enter an amount greater than zero.',
            'amount_minor.required' => 'Enter an amount greater than zero.',
            'source.required'     => 'Say where this income came from.',
            'payment_method.in'   => PaymentMethods::MESSAGE,
        ]);

        // An entry the app queued offline carries a client_id, the same on
        // every resend: one already saved is answered again, not saved twice.
        $clientId = ClientIds::from($request);
        if ($saved = ClientIds::find(AppIncome::class, $user->id, $clientId)) {
            return response()->json(['data' => $saved]);
        }

        try {
            $income = AppIncome::create([
                'user_id'        => $user->id,
                'client_id'      => $clientId,
                'amount_minor'   => $request->amount_minor,
                'source'         => trim($request->source),
                'note'           => $request->note ? (trim($request->note) ?: null) : null,
                'payment_method' => $request->payment_method,
                'received_at'    => $request->received_at,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // The same entry sent twice at once: the other request saved it.
            return response()->json(['data' => ClientIds::find(AppIncome::class, $user->id, $clientId) ?? throw $e]);
        }

        ActivityMail::incomeAdded($user, $income);

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
        $request->validate($this->rules(), [
            'amount_minor.min'  => 'Enter an amount greater than zero.',
            'source.required'   => 'Say where this income came from.',
            'payment_method.in' => PaymentMethods::MESSAGE,
        ]);

        $changes = [
            'amount_minor' => $request->amount_minor,
            'source'       => trim($request->source),
            'note'         => $request->note ? (trim($request->note) ?: null) : null,
            'received_at'  => $request->received_at,
        ];
        // Absent keeps the stored value (older app versions); null clears it.
        if ($request->has('payment_method')) {
            $changes['payment_method'] = $request->payment_method;
        }
        $income->update($changes);

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
