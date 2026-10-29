<x-admin-layout>
    <x-slot name="title">Default categories</x-slot>
    <x-slot name="subtitle">
        The {{ $categories->count() }} {{ \Illuminate\Support\Str::plural('category', $categories->count()) }} every new account starts with, in this order.
    </x-slot>
    @php
        $rupees = new \App\Support\DisplayCurrency('INR', '₹', 2, 'Indian Rupee', 1.0);
        $budgetInput = fn (?int $minor) => $minor ? rtrim(rtrim(number_format($minor / 100, 2, '.', ''), '0'), '.') : '';
    @endphp

    <div class="adm-strip">
        <x-icon name="info" class="mt-0.5 h-5 w-5 flex-shrink-0 text-brand-navy" />
        <p><span class="font-semibold text-brand-navy">New sign-ups only.</span>
            Changes here apply to accounts made from now on, on the app and the website. Nobody's existing categories, budgets or expenses change.</p>
    </div>

    <div class="flex flex-wrap items-start gap-4">
        {{-- The list --}}
        <section class="adm-card min-w-0 flex-[2_1_520px]">
            <h2 class="adm-h2">The list</h2>
            <ol class="mt-3">
                @foreach($categories as $i => $category)
                    <li class="flex flex-wrap items-center gap-3 border-b border-surface-page py-3 last:border-0 {{ $editing?->is($category) ? 'bg-brand-tint/60 -mx-3 rounded-card px-3' : '' }}">
                        <span class="w-6 text-right font-mono text-[13px] text-ink-muted">{{ $i + 1 }}</span>
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-xl" style="background-color: {{ $category->color }}2e" aria-hidden="true">{{ $category->icon }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $category->name }}</p>
                            <p class="adm-caption flex flex-wrap items-center gap-x-3">
                                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background-color: {{ $category->color }}" aria-hidden="true"></span><span class="font-mono">{{ $category->color }}</span></span>
                                <span>{{ $category->budget_minor ? $rupees->format($category->budget_minor) . ' monthly budget' : 'No budget' }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            @foreach(['up' => ['arrow-up', 'Move up', $i === 0], 'down' => ['arrow-down', 'Move down', $i === $categories->count() - 1]] as $direction => [$icon, $label, $disabled])
                                <form method="POST" action="{{ route('admin.default-categories.move', $category) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="direction" value="{{ $direction }}">
                                    <button type="submit" @disabled($disabled) aria-label="{{ $label }}: {{ $category->name }}"
                                            class="flex h-10 w-10 items-center justify-center rounded-chip text-brand-navy hover:bg-brand-tint disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent">
                                        <x-icon :name="$icon" class="h-4 w-4" />
                                    </button>
                                </form>
                            @endforeach
                            <a href="{{ route('admin.default-categories.edit', $category) }}" class="adm-btn-outline !h-10 !px-4 !text-sm">Edit<span class="sr-only"> {{ $category->name }}</span></a>
                            @if($categories->count() > 1)
                                <form method="POST" action="{{ route('admin.default-categories.destroy', $category) }}"
                                      data-confirm-title="Remove “{{ $category->name }}”?"
                                      data-confirm-message="New accounts will no longer start with it. Accounts that already have it keep it, with their expenses."
                                      data-confirm-button="Remove">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="min-h-10 px-2 text-sm font-semibold text-status-error hover:underline">Delete<span class="sr-only"> {{ $category->name }}</span></button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Add or edit, with a live preview --}}
        <section class="adm-card flex-[1_1_340px]" id="form"
                 x-data="{
                     name: @js(old('name', $editing?->name ?? '')),
                     icon: @js(old('icon', $editing?->icon ?? $icons[0])),
                     color: @js(old('color', $editing?->color ?? $colors[3])),
                     budget: @js((string) old('budget', $budgetInput($editing?->budget_minor))),
                     validColor() { return /^#[0-9a-f]{6}$/i.test(this.color) ? this.color : '#737373' },
                     budgetText() {
                         const n = Number(String(this.budget).replace(/[,₹\s]/g, ''));
                         return n > 0 ? '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: 2 }) + ' a month' : 'No budget';
                     },
                 }">
            <div class="flex items-center justify-between gap-2">
                <h2 class="adm-h2">{{ $editing ? 'Edit category' : 'Add a category' }}</h2>
                @if($editing)
                    <a href="{{ route('admin.default-categories.index') }}" class="adm-link">Cancel</a>
                @endif
            </div>

            {{-- Preview: as a new user sees it --}}
            <div class="mt-4 flex flex-col items-center gap-3 rounded-card bg-surface-page/70 px-4 py-5" aria-live="polite">
                <p class="adm-eyebrow text-ink-muted">Preview</p>
                <span class="inline-flex max-w-full items-center gap-2 rounded-full py-1.5 pl-1.5 pr-4 text-sm font-semibold text-ink"
                      :style="`background-color: ${validColor()}2e`">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-white text-lg" x-text="icon || '?'"></span>
                    <span class="truncate" x-text="name.trim() || 'Category name'"></span>
                </span>
                <p class="adm-caption" x-text="budgetText()"></p>
            </div>

            <form method="POST" action="{{ $editing ? route('admin.default-categories.update', $editing) : route('admin.default-categories.store') }}" class="mt-4 flex flex-col gap-4">
                @csrf
                @if($editing) @method('PUT') @endif

                <div>
                    <label for="name" class="adm-label flex justify-between">Name <span class="font-normal text-ink-muted" x-text="name.length + '/40'"></span></label>
                    <input id="name" name="name" type="text" maxlength="40" x-model="name" required placeholder="e.g. Rent" class="adm-input w-full">
                    @error('name')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </div>

                <fieldset>
                    <legend class="adm-label">Emoji</legend>
                    <div class="flex flex-wrap gap-1">
                        @foreach($icons as $option)
                            <button type="button" @click="icon = @js($option)" :aria-pressed="icon === @js($option)" aria-label="Emoji {{ $option }}"
                                    class="flex h-10 w-10 items-center justify-center rounded-chip border border-transparent text-lg hover:bg-surface-page"
                                    :class="icon === @js($option) && '!border-brand-navy bg-brand-tint'">{{ $option }}</button>
                        @endforeach
                    </div>
                    <label for="icon" class="adm-caption mt-2 block">Or type one</label>
                    <input id="icon" name="icon" type="text" maxlength="16" x-model="icon" required class="adm-input mt-1 w-24 text-center text-lg">
                    @error('icon')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </fieldset>

                <fieldset>
                    <legend class="adm-label">Colour</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach($colors as $option)
                            <button type="button" @click="color = @js($option)" :aria-pressed="color.toUpperCase() === @js($option)" aria-label="Colour {{ $option }}"
                                    class="h-9 w-9 rounded-full ring-offset-2" style="background-color: {{ $option }}"
                                    :class="color.toUpperCase() === @js($option) && 'ring-2 ring-brand-navy'"></button>
                        @endforeach
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <input type="color" :value="validColor()" @input="color = $event.target.value.toUpperCase()" aria-label="Pick any colour" class="h-11 w-11 cursor-pointer rounded-card border border-surface-field bg-white p-1">
                        <label for="color" class="sr-only">Colour code</label>
                        <input id="color" name="color" type="text" maxlength="7" x-model="color" required class="adm-input w-28 font-mono uppercase">
                    </div>
                    @error('color')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </fieldset>

                <div>
                    <label for="budget" class="adm-label">Monthly budget <span class="font-normal text-ink-muted">(optional)</span></label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-muted">₹</span>
                        <input id="budget" name="budget" type="text" inputmode="decimal" autocomplete="off" x-model="budget" placeholder="No budget" class="adm-input w-full pl-7">
                    </div>
                    @error('budget')
                        <p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>
                    @else
                        <p class="adm-caption mt-1">A starting budget each new user can change. Leave it empty for none.</p>
                    @enderror
                </div>

                <button type="submit" class="adm-btn-primary w-full">
                    <x-icon :name="$editing ? 'check' : 'plus'" class="h-5 w-5" /> {{ $editing ? 'Save changes' : 'Add category' }}
                </button>
            </form>
        </section>
    </div>
</x-admin-layout>
