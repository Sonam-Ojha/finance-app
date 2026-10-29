<x-app-layout>
    <x-slot name="title">Every-month budgets</x-slot>
    <x-slot name="subtitle">Leave a field empty for no budget. Progress against the ones you set shows up on Analytics, and Notifications flags a category once it goes over.</x-slot>
    @php $pad = mb_strlen($money->symbol) > 1 ? '!pl-12' : '!pl-8'; @endphp

    <form method="POST" action="{{ route('budgets.defaults.update') }}" class="space-y-4">
        @csrf @method('PUT')

        {{-- Two to a row on wide screens --}}
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach($categories as $category)
                @php $field = "budgets.{$category->id}"; @endphp
                <div class="card space-y-2">
                    <div class="flex items-center gap-3">
                        <x-tracker.badge :icon="$category->icon" :color="$category->color" size="w-9 h-9 text-base" />
                        <label for="budget-{{ $category->id }}" class="min-w-0 flex-1 truncate font-semibold strong">{{ $category->name }}</label>
                        <div class="relative w-36 sm:w-40">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm muted">{{ $money->symbol }}</span>
                            <input id="budget-{{ $category->id }}" name="budgets[{{ $category->id }}]" type="text" inputmode="decimal" autocomplete="off" placeholder="None"
                                   value="{{ old($field, $category->budget_minor ? $money->toInput($category->budget_minor) : '') }}" class="input text-right {{ $pad }}">
                        </div>
                    </div>
                    @error($field)<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('budgets.index') }}" class="btn-secondary">Back</a>
            <button type="submit" class="btn-primary">Save</button>
        </div>
    </form>
</x-app-layout>
