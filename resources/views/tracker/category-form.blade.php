<x-app-layout>
    <x-slot name="title">{{ $category ? 'Edit category' : 'New category' }}</x-slot>

    <div class="space-y-4"
         x-data="{
             name: @js(old('name', $category?->name ?? '')),
             icon: @js(old('icon', $category?->icon ?? $icons[0])),
             color: @js(old('color', $category?->color ?? $colors[0])),
         }">
        <form method="POST" action="{{ $category ? route('categories.update', $category) : route('categories.store') }}" class="card space-y-5">
            @csrf
            @if($category) @method('PUT') @endif
            @if($for)<input type="hidden" name="for" value="{{ $for }}">@endif
            <input type="hidden" name="icon" :value="icon">
            <input type="hidden" name="color" :value="color">

            {{-- Two columns on wide screens: what it is | how it looks --}}
            <div class="grid gap-x-10 gap-y-5 lg:grid-cols-2">
            <div class="space-y-5">
            {{-- Preview --}}
            <div class="flex flex-col items-center gap-2 py-2">
                <span class="w-16 h-16 rounded-full flex items-center justify-center text-3xl" :style="`background-color: ${color}2e`" x-text="icon"></span>
                <span class="font-semibold strong" x-text="name.trim() || 'Untitled category'"></span>
            </div>

            <div>
                <label for="name" class="field-label flex justify-between">Name <span class="normal-case tracking-normal font-normal" x-text="name.length + '/40'"></span></label>
                <input id="name" name="name" type="text" maxlength="40" x-model="name" placeholder="e.g. Coffee" class="input" required @unless($category) autofocus @endunless>
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="budget" class="field-label">Monthly budget (optional)</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm muted">{{ $money->symbol }}</span>
                    <input id="budget" name="budget" type="text" inputmode="decimal" autocomplete="off"
                           value="{{ old('budget', $category?->budget_minor ? $money->toInput($category->budget_minor) : '') }}"
                           placeholder="Leave empty for no budget" class="input {{ mb_strlen($money->symbol) > 1 ? '!pl-12' : '!pl-8' }}">
                </div>
                @error('budget')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            </div>

            <div class="space-y-5">
            <div>
                <span class="field-label">Icon</span>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($icons as $emoji)
                        <button type="button" @click="icon = @js($emoji)" :aria-pressed="icon === @js($emoji)" aria-label="Icon {{ $emoji }}"
                                class="w-11 h-11 rounded-xl text-xl flex items-center justify-center border border-transparent hover:bg-surface-page dark:hover:bg-white/5"
                                :class="icon === @js($emoji) && '!border-brand-navy bg-brand-tint dark:!border-brand-cyan dark:bg-brand-cyan/15'">{{ $emoji }}</button>
                    @endforeach
                </div>
                @error('icon')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <span class="field-label">Colour</span>
                <div class="flex flex-wrap gap-2.5">
                    @foreach($colors as $swatch)
                        <button type="button" @click="color = @js($swatch)" aria-label="Colour {{ $swatch }}" :aria-pressed="color === @js($swatch)"
                                class="w-9 h-9 rounded-full ring-offset-2 dark:ring-offset-night-card" style="background-color: {{ $swatch }}"
                                :class="color.toUpperCase() === @js($swatch) && 'ring-2 ring-brand-navy dark:ring-white'"></button>
                    @endforeach
                </div>
                @error('color')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ $for === 'expense' ? route('expenses.create') : route('categories.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>

        @if($category)
            @if($fallback)
                <form method="POST" action="{{ route('categories.destroy', $category) }}" class="text-center"
                      data-confirm-title="Delete “{{ $category->name }}”?" data-confirm-message="{{ $deleteMessage }}" data-confirm-button="Delete">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-danger-ghost"><x-icon name="trash" class="w-4 h-4" /> Delete category</button>
                </form>
            @else
                <p class="text-center text-sm muted">You need at least one category — create another before deleting this one.</p>
            @endif
        @endif
    </div>
</x-app-layout>
