<x-app-layout>
    <x-slot name="title">{{ $goal->name }}</x-slot>
    <x-slot name="heading">{{ $goal->emoji }} {{ $goal->name }}</x-slot>
    <x-slot name="subtitle">Savings goal · started {{ $goal->created_at->timezone(config('app.display_timezone'))->format('j M Y') }}</x-slot>
    <x-slot name="actions">
        <a href="{{ route('goals.index') }}" class="btn-secondary"><x-icon name="chevron-left" class="w-4 h-4" /> All goals</a>
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
        @include('tracker.partials.goal-card', ['goal' => $goal, 'onPage' => true])

        {{-- Every time money went in or came out --}}
        <div class="card">
            <h3 class="card-title">History</h3>
            @if($history->isEmpty())
                <div class="text-center py-8">
                    <p class="font-semibold strong">Nothing added yet</p>
                    <p class="text-sm muted mt-1">Use Add money whenever you put some aside for this goal.</p>
                </div>
            @else
                <div class="divide-soft mt-2">
                    @foreach($history as $entry)
                        @php $in = $entry->amount_minor > 0; @endphp
                        <div class="flex items-center gap-3 py-2.5">
                            <span class="icon-tile !h-9 !w-9 {{ $in ? '' : '!bg-red-50 !text-status-error dark:!bg-red-500/10 dark:!text-red-400' }}">
                                <x-icon :name="$in ? 'plus' : 'minus'" class="w-4 h-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium strong truncate">{{ $entry->note ?: ($in ? 'Added' : 'Withdrawn') }}</p>
                                <p class="text-xs muted">{{ \App\Support\Labels::dayHeading($entry->on_date->toDateString()) }}</p>
                            </div>
                            <span class="text-sm font-semibold {{ $in ? 'money-in' : 'money-out' }}">{{ $in ? '+' : '−' }}{{ $money->format(abs($entry->amount_minor)) }}</span>
                            {{-- Remove an entry made by mistake (refused if the goal would go below zero) --}}
                            <form method="POST" action="{{ route('goals.contributions.destroy', [$goal, $entry->id]) }}"
                                  data-confirm-title="Delete this entry?"
                                  data-confirm-message="{{ $in ? 'Added' : 'Withdrawn' }} {{ $money->format(abs($entry->amount_minor)) }} on {{ $entry->on_date->format('j M Y') }} is removed, and the goal's saved amount changes to match."
                                  data-confirm-button="Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600 dark:text-night-muted dark:hover:bg-red-500/10" aria-label="Delete this entry of {{ $money->format(abs($entry->amount_minor)) }}">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
                @if($history->hasPages())
                    <div class="mt-4">{{ $history->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
