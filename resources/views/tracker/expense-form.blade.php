<x-app-layout>
    <x-slot name="title">{{ $expense ? 'Edit expense' : 'New expense' }}</x-slot>
    @php
        $selected = (int) old('category_id', $expense?->category_id ?? ($categories->firstWhere('id', $picked)?->id ?? $categories->first()?->id));
    @endphp

    <div class="space-y-4">
        @unless($expense)
            <div class="seg">
                <span class="seg-item seg-on">Expenses</span>
                <a href="{{ route('incomes.create') }}" class="seg-item">Income</a>
            </div>
        @endunless

        <form method="POST" action="{{ $expense ? route('expenses.update', $expense) : route('expenses.store') }}" class="card space-y-5">
            @csrf
            @if($expense) @method('PUT') @endif
            <input type="hidden" name="back" value="{{ old('back', $back) }}">

            {{-- Two columns on wide screens: amount | date and note, then how it was paid | category --}}
            <div class="grid gap-x-10 gap-y-5 lg:grid-cols-2">
            @include('tracker.partials.entry-fields', [
                'amountValue'  => $expense ? $money->toInput($expense->amount_minor) : '',
                'dateField'    => 'spent_at',
                'dateValue'    => $expense?->spent_at->toDateString() ?? \App\Support\Labels::now()->toDateString(),
                'paymentLabel' => 'Paid with',
                'paymentValue' => $expense?->payment_method,
                'noteValue'    => $expense?->note,
            ])

            <fieldset>
                <legend class="field-label flex justify-between w-full">Category
                    <a href="{{ route('categories.create', ['for' => 'expense']) }}" class="link normal-case tracking-normal font-medium">+ Add new</a>
                </legend>
                @if($categories->isEmpty())
                    <p class="text-sm muted">No categories yet — <a href="{{ route('categories.create', ['for' => 'expense']) }}" class="link">add one</a> first.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach($categories as $category)
                            <label>
                                <input type="radio" name="category_id" value="{{ $category->id }}" class="peer sr-only" @checked($selected === $category->id) required>
                                <span class="chip">{{ $category->icon }} {{ $category->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('category_id')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>

            {{-- Receipt photo: shrunk in the browser (receiptToDataUri), saved with the form; kept private on the server. --}}
            <div x-data="{
                    preview: @js($expense ? \App\Support\Receipts::url($expense) : null),
                    saved: @js((bool) $expense?->has_receipt),
                    busy: false,
                    failed: false,
                    pick(file) {
                        if (! file) return;
                        this.busy = true; this.failed = false;
                        receiptToDataUri(file)
                            .then((uri) => { this.$refs.receipt.value = uri; this.$refs.remove.value = ''; this.preview = uri; })
                            .catch(() => { this.failed = true; })
                            .finally(() => { this.busy = false; this.$refs.file.value = ''; });
                    },
                    clear() {
                        this.$refs.receipt.value = '';
                        this.$refs.remove.value = this.saved ? '1' : '';
                        this.preview = null;
                    },
                 }">
                <span class="field-label">Receipt <span class="font-normal muted">(optional)</span></span>
                <input type="hidden" name="receipt" x-ref="receipt">
                <input type="hidden" name="remove_receipt" x-ref="remove">
                <div class="flex items-center gap-4">
                    <button type="button" x-show="preview" x-cloak @click="$dispatch('open-photo', { src: preview, name: 'Receipt', contain: true })"
                            class="flex-shrink-0 rounded-card" aria-label="View the receipt photo">
                        <img :src="preview" alt="" class="h-20 w-20 rounded-card border border-surface-line object-cover dark:border-night-line">
                    </button>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="btn-secondary !min-h-11 cursor-pointer" :class="busy && 'opacity-50 pointer-events-none'">
                            <x-icon name="camera" class="w-4 h-4" />
                            <span x-text="busy ? 'Preparing…' : (preview ? 'Change photo' : '🧾 Add photo')">🧾 Add photo</span>
                            <input type="file" accept="image/*" class="sr-only" x-ref="file" @change="pick($event.target.files[0])">
                        </label>
                        <button type="button" x-show="preview" x-cloak @click="clear()" class="btn-danger-ghost !min-h-11">
                            <x-icon name="trash" class="w-4 h-4" /> Remove
                        </button>
                    </div>
                </div>
                <p class="field-error" x-show="failed" x-cloak>Could not use that photo. Try a different one.</p>
                @error('receipt')
                    <p class="field-error" x-show="! failed">{{ $message }}</p>
                @else
                    <p class="mt-1 text-xs muted" x-show="! failed">A photo of the bill, if you want to keep one. Only you can see it.</p>
                @enderror
            </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ old('back', $back) }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">{{ $expense ? 'Save' : 'Add expense' }}</button>
            </div>
        </form>

        @if($expense)
            <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="text-center"
                  data-confirm-title="Delete expense?" data-confirm-message="This expense will be permanently removed." data-confirm-button="Delete">
                @csrf @method('DELETE')
                <input type="hidden" name="back" value="{{ old('back', $back) }}">
                <button type="submit" class="btn-danger-ghost"><x-icon name="trash" class="w-4 h-4" /> Delete expense</button>
            </form>
        @endif
    </div>
</x-app-layout>
