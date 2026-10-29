{{--
    One savings goal: progress, the deadline's monthly amount, and its
    actions. Expects $goal (App\Models\SavingsGoal) and $money; $onPage is
    true on the goal's own page (no History link there).
--}}
@php
    $onPage   = $onPage ?? false;
    $reached  = $goal->isReached();
    $archived = $goal->isArchived();
    $percent  = (int) floor($goal->progress() * 100);
    $monthly  = $goal->monthlyNeededMinor();
    // An inline form left open with an error comes back open.
    $openWith = (int) old('goal_id') === $goal->id ? old('direction') : null;
@endphp
<div class="card space-y-3 {{ $archived ? 'opacity-80' : '' }}" x-data="{ mode: @js($openWith) }">
    <div class="flex items-center gap-3">
        <x-tracker.badge :icon="$goal->emoji" :color="$goal->color" size="w-11 h-11 text-xl" />
        <div class="min-w-0 flex-1">
            @if($onPage)
                <p class="truncate font-semibold strong">{{ $goal->name }}</p>
            @else
                <a href="{{ route('goals.show', $goal) }}" class="block truncate font-semibold strong hover:underline">{{ $goal->name }}</a>
            @endif
            <p class="text-xs muted truncate">
                @if($goal->deadline)
                    {{ $goal->isOverdue() ? 'Deadline was' : 'By' }} {{ $goal->deadline->format('j M Y') }}
                @else
                    No deadline
                @endif
                @if($archived) · Archived @endif
            </p>
        </div>
        @if($reached)
            <span class="flex-shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-status-successText dark:bg-emerald-500/15 dark:text-emerald-400">🎉 Reached</span>
        @endif
    </div>

    <div class="bar-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="{{ $goal->name }}: {{ $percent }}% saved">
        <div class="h-full rounded-full" style="width: {{ $goal->saved_minor > 0 ? max(2, min(100, $goal->progress() * 100)) : 0 }}%; background-color: {{ $reached ? '#16A34A' : $goal->color }}"></div>
    </div>
    <div class="flex flex-wrap justify-between gap-x-3 gap-y-1 text-xs muted">
        <span><span class="font-semibold strong">{{ $money->format($goal->saved_minor) }}</span> of {{ $money->format($goal->target_minor) }} · {{ $percent }}%</span>
        @unless($reached)<span>{{ $money->format($goal->remainingMinor()) }} to go</span>@endunless
    </div>
    @if($monthly !== null)
        <p class="text-sm {{ $goal->isOverdue() ? 'money-out' : 'strong' }}">
            @if($goal->isOverdue())
                The deadline has passed — {{ $money->format($monthly) }} still to go.
            @else
                <span class="font-semibold">{{ $money->format($monthly) }} per month</span> to reach it by {{ $goal->deadline->format('j M Y') }}
            @endif
        </p>
    @endif

    <div class="flex flex-wrap items-center gap-2 pt-1">
        @unless($archived)
            <button type="button" class="chip" :class="mode === 'add' && 'chip-on'" @click="mode = mode === 'add' ? null : 'add'; $nextTick(() => mode && $refs.amount.focus())">
                <x-icon name="plus" class="w-4 h-4" /> Add money
            </button>
            <button type="button" class="chip" :class="mode === 'withdraw' && 'chip-on'" @click="mode = mode === 'withdraw' ? null : 'withdraw'; $nextTick(() => mode && $refs.amount.focus())"
                    @if($goal->saved_minor <= 0) disabled aria-disabled="true" title="Nothing saved yet" style="opacity:.5;cursor:not-allowed" @endif>
                <x-icon name="minus" class="w-4 h-4" /> Withdraw
            </button>
        @endunless
        <div class="ml-auto flex items-center gap-1">
            @unless($onPage)
                <a href="{{ route('goals.show', $goal) }}" class="hdr-btn !h-9 !w-9" aria-label="History of {{ $goal->name }}" title="History"><x-icon name="list" class="w-4 h-4" /></a>
            @endunless
            <a href="{{ route('goals.edit', $goal) }}" class="hdr-btn !h-9 !w-9" aria-label="Edit {{ $goal->name }}" title="Edit"><x-icon name="pencil" class="w-4 h-4" /></a>
            <form method="POST" action="{{ route('goals.archive', $goal) }}">
                @csrf @method('PATCH')
                <button type="submit" class="hdr-btn !h-9 !w-9" aria-label="{{ $archived ? 'Unarchive' : 'Archive' }} {{ $goal->name }}" title="{{ $archived ? 'Unarchive' : 'Archive' }}">
                    <x-icon name="archive" class="w-4 h-4" />
                </button>
            </form>
            @if($archived)
                <form method="POST" action="{{ route('goals.destroy', $goal) }}"
                      data-confirm-title="Delete “{{ $goal->name }}”?" data-confirm-message="The goal and its history will be permanently removed. Your expenses and income are not affected." data-confirm-button="Delete">
                    @csrf @method('DELETE')
                    <button type="submit" class="hdr-btn !h-9 !w-9 hover:!text-red-600" aria-label="Delete {{ $goal->name }}" title="Delete"><x-icon name="trash" class="w-4 h-4" /></button>
                </form>
            @endif
        </div>
    </div>

    @unless($archived)
        {{-- Add money / Withdraw --}}
        <form method="POST" action="{{ route('goals.contributions.store', $goal) }}" x-show="mode" x-cloak class="space-y-3 border-t border-surface-page pt-3 dark:border-night-line">
            @csrf
            <input type="hidden" name="goal_id" value="{{ $goal->id }}">
            <input type="hidden" name="direction" :value="mode">
            <input type="hidden" name="back" value="{{ request()->fullUrl() }}">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="goal-amount-{{ $goal->id }}" class="field-label" x-text="mode === 'withdraw' ? 'Amount to withdraw' : 'Amount to add'">Amount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm muted">{{ $money->symbol }}</span>
                        <input id="goal-amount-{{ $goal->id }}" name="amount" type="text" inputmode="decimal" autocomplete="off" required x-ref="amount"
                               value="{{ $openWith ? old('amount') : '' }}" placeholder="0" class="input {{ mb_strlen($money->symbol) > 1 ? '!pl-12' : '!pl-8' }}">
                    </div>
                </div>
                <div>
                    <label for="goal-on-{{ $goal->id }}" class="field-label">Date</label>
                    <input id="goal-on-{{ $goal->id }}" name="on" type="date" max="{{ \App\Support\Labels::now()->toDateString() }}"
                           value="{{ $openWith ? old('on', \App\Support\Labels::now()->toDateString()) : \App\Support\Labels::now()->toDateString() }}" class="input">
                </div>
            </div>
            <div>
                <label for="goal-note-{{ $goal->id }}" class="field-label">Note <span class="font-normal muted">(optional)</span></label>
                <input id="goal-note-{{ $goal->id }}" name="note" type="text" maxlength="120" value="{{ $openWith ? old('note') : '' }}" placeholder="e.g. Bonus" class="input">
            </div>
            @if($openWith)
                @foreach(['amount', 'note', 'on', 'direction'] as $field)
                    @error($field)<p class="field-error">{{ $message }}</p>@enderror
                @endforeach
            @endif
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary !min-h-11" @click="mode = null">Cancel</button>
                <button type="submit" class="btn-primary !min-h-11" x-text="mode === 'withdraw' ? 'Withdraw' : 'Add money'">Save</button>
            </div>
        </form>
    @endunless
</div>
