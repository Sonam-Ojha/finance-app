<x-app-layout>
    <x-slot name="title">{{ $rule->exists ? 'Edit recurring' : 'New recurring' }} {{ $rule->kind === 'income' ? 'income' : 'expense' }}</x-slot>
    @php
        $kind = old('kind', $rule->kind);
        $selected = (int) old('category_id', $rule->category_id ?? $categories->first()?->id);
        $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    @endphp

    <div class="space-y-4"
         x-data="{
             kind: @js($kind),
             frequency: @js(old('frequency', $rule->frequency)),
             first: @js(old('next_on', $rule->next_on?->toDateString())),
             days: @js($weekdays),
             repeats() {
                 if (! this.first) return '';
                 const d = new Date(this.first + 'T00:00');
                 const n = d.getDate();
                 const th = [11, 12, 13].includes(n % 100) ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] || 'th');
                 if (this.frequency === 'weekly') return `Every week on ${this.days[d.getDay()]}`;
                 if (this.frequency === 'yearly') return `Every year on ${n} ${d.toLocaleDateString('en-IN', { month: 'long' })}`;
                 return `Every month on the ${n}${th}` + (n > 28 ? ' (the last day in shorter months)' : '');
             },
         }">
        @unless($rule->exists)
            <div class="seg">
                <a href="{{ route('recurring.create') }}" class="seg-item {{ $kind === 'expense' ? 'seg-on' : '' }}">Expense</a>
                <a href="{{ route('recurring.create', ['kind' => 'income']) }}" class="seg-item {{ $kind === 'income' ? 'seg-on' : '' }}">Income</a>
            </div>
        @endunless

        <form method="POST" action="{{ $rule->exists ? route('recurring.update', $rule) : route('recurring.store') }}" class="card space-y-5">
            @csrf
            @if($rule->exists) @method('PUT') @endif
            <input type="hidden" name="kind" value="{{ $kind }}">

            {{-- Two columns on wide screens: what and how much | when --}}
            <div class="grid gap-x-10 gap-y-5 lg:grid-cols-2">
                <div class="space-y-5">
                    <div>
                        <label for="amount" class="field-label">Amount</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-semibold muted">{{ $money->symbol }}</span>
                            <input id="amount" name="amount" type="text" inputmode="decimal" autocomplete="off" required
                                   value="{{ old('amount', $rule->amount_minor ? $money->toInput($rule->amount_minor) : '') }}" placeholder="0" @unless($rule->exists) autofocus @endunless
                                   class="input !text-2xl !font-bold !py-3 {{ mb_strlen($money->symbol) > 1 ? '!pl-16' : '!pl-10' }}">
                        </div>
                        @error('amount')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    @if($kind === 'expense')
                        <fieldset>
                            <legend class="field-label">Category</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach($categories as $category)
                                    <label>
                                        <input type="radio" name="category_id" value="{{ $category->id }}" class="peer sr-only" @checked($selected === $category->id) required>
                                        <span class="chip">{{ $category->icon }} {{ $category->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('category_id')<p class="field-error">{{ $message }}</p>@enderror
                        </fieldset>
                    @else
                        <div x-data="{ source: @js(old('source', $rule->source ?? 'Salary')) }">
                            <label for="source" class="field-label">Source</label>
                            <div class="mb-2 flex flex-wrap gap-2">
                                @foreach(\App\Support\Labels::INCOME_SOURCES as $name => $icon)
                                    <button type="button" class="chip" :class="source.toLowerCase() === @js(strtolower($name)) && 'chip-on'" @click="source = @js($name)">{{ $icon }} {{ $name }}</button>
                                @endforeach
                            </div>
                            <input id="source" name="source" type="text" maxlength="40" x-model="source" placeholder="Or type a source" class="input">
                            @error('source')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <div x-data="{ note: @js(old('note', $rule->note ?? '')) }">
                        <label for="note" class="field-label flex justify-between">Name or note <span class="font-normal normal-case tracking-normal" x-text="note.length + '/120'"></span></label>
                        <input id="note" name="note" type="text" maxlength="120" x-model="note" placeholder="e.g. Rent, Car EMI, Netflix" class="input">
                        @error('note')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="space-y-5">
                    <fieldset>
                        <legend class="field-label">Repeats</legend>
                        <div class="seg w-full">
                            @foreach(\App\Models\RecurringEntry::FREQUENCIES as $key => $label)
                                <label class="seg-item flex-1 cursor-pointer" :class="frequency === @js($key) && 'seg-on'">
                                    <input type="radio" name="frequency" value="{{ $key }}" x-model="frequency" class="sr-only"> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('frequency')<p class="field-error">{{ $message }}</p>@enderror
                    </fieldset>

                    <div>
                        <label for="next_on" class="field-label">{{ $rule->exists ? 'Next one on' : 'First one on' }}</label>
                        <input id="next_on" name="next_on" type="date" required min="{{ $today }}" x-model="first" class="input">
                        @error('next_on')
                            <p class="field-error">{{ $message }}</p>
                        @else
                            <p class="mt-1.5 text-sm font-semibold text-brand-navy dark:text-brand-cyan" x-text="repeats()"></p>
                        @enderror
                    </div>

                    <div x-data="{ payment: @js(old('payment_method', $rule->payment_method ?? '')) }">
                        <span class="field-label">{{ $kind === 'income' ? 'Received via' : 'Paid with' }}</span>
                        <input type="hidden" name="payment_method" :value="payment">
                        <div class="flex flex-wrap gap-2">
                            @foreach(['upi', 'cash', 'debit_card', 'credit_card', 'bank_transfer', 'other'] as $method)
                                <button type="button" class="chip" :class="payment === @js($method) && 'chip-on'" :aria-pressed="payment === @js($method)"
                                        @click="payment = payment === @js($method) ? '' : @js($method)">{{ \App\Support\PaymentMethods::label($method) }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ route('recurring.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">{{ $rule->exists ? 'Save' : 'Add' }}</button>
            </div>
        </form>

        @if($rule->exists)
            <form method="POST" action="{{ route('recurring.destroy', $rule) }}" class="text-center"
                  data-confirm-title="Delete this recurring {{ $rule->kind }}?" data-confirm-message="Nothing more is added from it. Entries it already added stay." data-confirm-button="Delete">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger-ghost"><x-icon name="trash" class="w-4 h-4" /> Delete</button>
            </form>
        @endif
    </div>
</x-app-layout>
