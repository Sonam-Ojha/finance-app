<?php

namespace App\Support;

use App\Models\RecurringEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** What a recurring expense or income may be, for the website and the API alike. */
class RecurringRules
{
    /**
     * Everything but the amount, which the website and the API read
     * differently (the caller passes its amount rule in). A new first day must be today or later; a paused rule's
     * own past day may be sent back unchanged (resuming moves it on).
     */
    public static function validate(Request $request, ?RecurringEntry $existing = null, array $amountRules = [], array $amountMessages = []): void
    {
        $uid = $request->user()->id;
        $today = Labels::now()->toDateString();
        $kept = $existing?->next_on?->toDateString();
        $request->validate($amountRules + [
            'kind'           => ['required', Rule::in(RecurringEntry::KINDS)],
            'category_id'    => ['exclude_unless:kind,expense', 'required', 'integer', Rule::exists('categories', 'id')->where('user_id', $uid)],
            'source'         => ['exclude_unless:kind,income', 'required', 'string', 'max:40'],
            'note'           => 'nullable|string|max:120',
            'payment_method' => ['nullable', Rule::in(PaymentMethods::all())],
            'frequency'      => ['required', Rule::in(array_keys(RecurringEntry::FREQUENCIES))],
            'next_on'        => ['required', 'date_format:Y-m-d', function ($attribute, $value, $fail) use ($today, $kept) {
                if ($value < $today && $value !== $kept) $fail('Pick today or a later day.');
            }],
            'active'         => 'sometimes|boolean',
        ], $amountMessages + [
            'kind.*'            => 'Choose expense or income.',
            'category_id.*'     => 'Pick a category.',
            'source.required'   => 'Say where the money comes from.',
            'source.max'        => 'Keep the source to 40 characters.',
            'note.max'          => 'Keep the note to 120 characters.',
            'payment_method.in' => PaymentMethods::MESSAGE,
            'frequency.*'       => 'Choose how often it repeats.',
            'next_on.required'  => 'Pick the first day it is added.',
            'next_on.*'         => 'Pick today or a later day.',
        ]);
    }

    /** The validated fields, ready to save (`amount_minor` added by the caller). */
    public static function fields(Request $request, RecurringEntry $rule): array
    {
        $income = $request->input('kind') === 'income';
        $rule->fill([
            'kind'           => $request->input('kind'),
            'category_id'    => $income ? null : (int) $request->input('category_id'),
            'source'         => $income ? trim((string) $request->input('source')) : null,
            'note'           => trim((string) $request->input('note')) ?: null,
            'payment_method' => $request->input('payment_method') ?: null,
            'frequency'      => $request->input('frequency'),
        ]);
        if ($request->has('active')) $rule->active = $request->boolean('active');
        elseif (! $rule->exists) $rule->active = true;
        // A new first day resets the day it repeats on.
        if (! $rule->next_on || $rule->next_on->toDateString() !== $request->input('next_on')) {
            $rule->startOn(Carbon::createFromFormat('!Y-m-d', $request->input('next_on')));
        }

        return $rule->getAttributes();
    }
}
