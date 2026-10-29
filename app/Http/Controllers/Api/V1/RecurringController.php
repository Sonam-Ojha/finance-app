<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RecurringEntry;
use App\Support\Labels;
use App\Support\RecurringRules;
use Illuminate\Http\Request;

/** The app's Recurring screen: expenses and incomes `recurring:post` adds on their day. */
class RecurringController extends Controller
{
    public function index(Request $request)
    {
        $rules = RecurringEntry::with('category')->where('user_id', $request->user()->id)
            ->orderByDesc('active')->orderBy('next_on')->get();

        return response()->json(['data' => $rules->map(fn (RecurringEntry $rule) => $this->shape($rule))]);
    }

    public function store(Request $request)
    {
        $rule = new RecurringEntry(['user_id' => $request->user()->id]);
        $this->save($request, $rule);

        return response()->json(['data' => $this->shape($rule->load('category'))], 201);
    }

    /** Resuming (`active: true`) carries on from today: paused days are not added afterwards. */
    public function update(Request $request, RecurringEntry $recurring)
    {
        $this->authorizeOwner($request, $recurring);
        $resuming = ! $recurring->active && $request->boolean('active');
        $this->save($request, $recurring);
        if ($resuming) {
            $today = Labels::now()->toDateString();
            while ($recurring->next_on->toDateString() < $today) $recurring->next_on = $recurring->after($recurring->next_on);
            $recurring->save();
        }

        return response()->json(['data' => $this->shape($recurring->load('category'))]);
    }

    public function destroy(Request $request, RecurringEntry $recurring)
    {
        $this->authorizeOwner($request, $recurring);
        $recurring->delete();

        return response()->noContent();
    }

    private function save(Request $request, RecurringEntry $rule): void
    {
        RecurringRules::validate($request, $rule->exists ? $rule : null,
            ['amount_minor' => 'required|integer|min:1|max:100000000000'],
            ['amount_minor.*' => 'Enter an amount greater than zero.']);
        RecurringRules::fields($request, $rule);
        $rule->amount_minor = (int) $request->input('amount_minor');
        $rule->save();
    }

    private function shape(RecurringEntry $rule): array
    {
        return [
            'id'             => $rule->id,
            'kind'           => $rule->kind,
            'amount_minor'   => $rule->amount_minor,
            'category_id'    => $rule->category_id,
            'category_name'  => $rule->category?->name,
            'category_icon'  => $rule->category?->icon,
            'category_color' => $rule->category?->color,
            'source'         => $rule->source,
            'note'           => $rule->note,
            'payment_method' => $rule->payment_method,
            'frequency'      => $rule->frequency,
            'day'            => $rule->day,
            'next_on'        => $rule->next_on->toDateString(),
            'last_on'        => $rule->last_on?->toDateString(),
            'active'         => $rule->active,
            'description'    => $rule->describe(),
        ];
    }

    private function authorizeOwner(Request $request, RecurringEntry $rule): void
    {
        if ($rule->user_id !== $request->user()->id) abort(404);
    }
}
