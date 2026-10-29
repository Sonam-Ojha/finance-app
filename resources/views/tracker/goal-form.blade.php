<x-app-layout>
    <x-slot name="title">{{ $goal ? 'Edit goal' : 'New goal' }}</x-slot>
    @php
        $today = \App\Support\Labels::now()->toDateString();
        $deadline = old('deadline', $goal?->deadline?->toDateString());
    @endphp

    <div class="space-y-4"
         x-data="{
             name: @js(old('name', $goal?->name ?? '')),
             emoji: @js(old('emoji', $goal?->emoji ?? $emojis[0])),
             color: @js(old('color', $goal?->color ?? $colors[5])),
         }">
        <form method="POST" action="{{ $goal ? route('goals.update', $goal) : route('goals.store') }}" class="card space-y-5">
            @csrf
            @if($goal) @method('PUT') @endif
            <input type="hidden" name="emoji" :value="emoji">
            <input type="hidden" name="color" :value="color">

            {{-- Two columns on wide screens: what it is | how it looks --}}
            <div class="grid gap-x-10 gap-y-5 lg:grid-cols-2">
            <div class="space-y-5">
                {{-- Preview --}}
                <div class="flex flex-col items-center gap-2 py-2">
                    <span class="w-16 h-16 rounded-full flex items-center justify-center text-3xl" :style="`background-color: ${color}2e`" x-text="emoji"></span>
                    <span class="font-semibold strong" x-text="name.trim() || 'Untitled goal'"></span>
                </div>

                <div>
                    <label for="name" class="field-label flex justify-between">Name <span class="normal-case tracking-normal font-normal" x-text="name.length + '/40'"></span></label>
                    <input id="name" name="name" type="text" maxlength="40" x-model="name" placeholder="e.g. Goa trip" class="input" required @unless($goal) autofocus @endunless>
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label for="target" class="field-label">Target</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm muted">{{ $money->symbol }}</span>
                            <input id="target" name="target" type="text" inputmode="decimal" autocomplete="off" required placeholder="20,000"
                                   value="{{ old('target', $goal ? $money->toInput($goal->target_minor) : '') }}"
                                   class="input {{ mb_strlen($money->symbol) > 1 ? '!pl-12' : '!pl-8' }}">
                        </div>
                        @error('target')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="deadline" class="field-label">Deadline <span class="font-normal muted">(optional)</span></label>
                        <input id="deadline" name="deadline" type="date" value="{{ $deadline }}"
                               min="{{ $deadline && $deadline < $today ? $deadline : $today }}" class="input">
                        @error('deadline')
                            <p class="field-error">{{ $message }}</p>
                        @else
                            <p class="mt-1 text-xs muted">We'll work out how much to save each month.</p>
                        @enderror
                    </div>
                </div>

                @if($goal)
                    <p class="text-sm muted">{{ $money->format($goal->saved_minor) }} saved so far. Use Add money or Withdraw on the goal to change it.</p>
                @endif
            </div>

            <div class="space-y-5">
                <div>
                    <span class="field-label">Emoji</span>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($emojis as $option)
                            <button type="button" @click="emoji = @js($option)" :aria-pressed="emoji === @js($option)" aria-label="Emoji {{ $option }}"
                                    class="w-11 h-11 rounded-xl text-xl flex items-center justify-center border border-transparent hover:bg-surface-page dark:hover:bg-white/5"
                                    :class="emoji === @js($option) && '!border-brand-navy bg-brand-tint dark:!border-brand-cyan dark:bg-brand-cyan/15'">{{ $option }}</button>
                        @endforeach
                    </div>
                    @error('emoji')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <span class="field-label">Colour</span>
                    <div class="flex flex-wrap gap-2.5">
                        @foreach($colors as $swatch)
                            <button type="button" @click="color = @js($swatch)" aria-label="Colour {{ $swatch }}" :aria-pressed="color.toUpperCase() === @js($swatch)"
                                    class="w-9 h-9 rounded-full ring-offset-2 dark:ring-offset-night-card" style="background-color: {{ $swatch }}"
                                    :class="color.toUpperCase() === @js($swatch) && 'ring-2 ring-brand-navy dark:ring-white'"></button>
                        @endforeach
                    </div>
                    @error('color')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ $goal ? route('goals.show', $goal) : route('goals.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">{{ $goal ? 'Save' : 'Create goal' }}</button>
            </div>
        </form>

        @if($goal)
            <div class="flex flex-wrap items-center justify-center gap-2">
                <form method="POST" action="{{ route('goals.archive', $goal) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-secondary"><x-icon name="archive" class="w-4 h-4" /> {{ $goal->isArchived() ? 'Unarchive goal' : 'Archive goal' }}</button>
                </form>
                <form method="POST" action="{{ route('goals.destroy', $goal) }}"
                      data-confirm-title="Delete “{{ $goal->name }}”?" data-confirm-message="The goal and its history will be permanently removed. Your expenses and income are not affected." data-confirm-button="Delete">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-danger-ghost"><x-icon name="trash" class="w-4 h-4" /> Delete goal</button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
