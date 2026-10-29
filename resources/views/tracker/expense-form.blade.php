<x-app-layout>
    <x-slot name="title">{{ $expense ? 'Edit expense' : 'New expense' }}</x-slot>
    @php
        $selected = (int) old('category_id', $expense?->category_id ?? ($categories->firstWhere('id', $picked)?->id ?? $categories->first()?->id));
    @endphp

    <div class="max-w-2xl mx-auto space-y-4">
        @unless($expense)
            <div class="seg !bg-white dark:!bg-slate-800 shadow-sm">
                <span class="seg-item seg-on !bg-indigo-600 !text-white">Expenses</span>
                <a href="{{ route('incomes.create') }}" class="seg-item">Income</a>
            </div>
        @endunless

        <form method="POST" action="{{ $expense ? route('expenses.update', $expense) : route('expenses.store') }}" class="card space-y-5">
            @csrf
            @if($expense) @method('PUT') @endif
            <input type="hidden" name="back" value="{{ old('back', $back) }}">

            <h2 class="page-title">{{ $expense ? 'Edit expense' : 'New expense' }}</h2>

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
