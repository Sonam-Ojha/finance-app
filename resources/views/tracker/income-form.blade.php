<x-app-layout>
    <x-slot name="title">{{ $income ? 'Edit income' : 'New income' }}</x-slot>

    <div class="space-y-4">
        @unless($income)
            <div class="seg">
                <a href="{{ route('expenses.create') }}" class="seg-item">Expenses</a>
                <span class="seg-item seg-on">Income</span>
            </div>
        @endunless

        <form method="POST" action="{{ $income ? route('incomes.update', $income) : route('incomes.store') }}" class="card space-y-5">
            @csrf
            @if($income) @method('PUT') @endif
            <input type="hidden" name="back" value="{{ old('back', $back) }}">

            {{-- Two columns on wide screens: amount | date and note, then how it came in | source --}}
            <div class="grid gap-x-10 gap-y-5 lg:grid-cols-2">
            @include('tracker.partials.entry-fields', [
                'amountLabel'  => 'Amount received',
                'amountValue'  => $income ? $money->toInput($income->amount_minor) : '',
                'dateField'    => 'received_at',
                'dateValue'    => $income?->received_at->toDateString() ?? \App\Support\Labels::now()->toDateString(),
                'paymentLabel' => 'Received via',
                'paymentValue' => $income?->payment_method,
                'noteValue'    => $income?->note,
            ])

            <div x-data="{ source: @js(old('source', $income?->source ?? 'Salary')) }">
                <label for="source" class="field-label">Source</label>
                <div class="flex flex-wrap gap-2 mb-2">
                    @foreach(\App\Support\Labels::INCOME_SOURCES as $name => $icon)
                        <button type="button" class="chip" :class="source.toLowerCase() === @js(strtolower($name)) && 'chip-on'"
                                @click="source = @js($name)">{{ $icon }} {{ $name }}</button>
                    @endforeach
                </div>
                <input id="source" name="source" type="text" maxlength="40" x-model="source" placeholder="Or type a source" class="input">
                @error('source')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ old('back', $back) }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">{{ $income ? 'Save' : 'Add income' }}</button>
            </div>
        </form>

        @if($income)
            <form method="POST" action="{{ route('incomes.destroy', $income) }}" class="text-center"
                  data-confirm-title="Delete income?" data-confirm-message="This income will be permanently removed." data-confirm-button="Delete">
                @csrf @method('DELETE')
                <input type="hidden" name="back" value="{{ old('back', $back) }}">
                <button type="submit" class="btn-danger-ghost"><x-icon name="trash" class="w-4 h-4" /> Delete income</button>
            </form>
        @endif
    </div>
</x-app-layout>
