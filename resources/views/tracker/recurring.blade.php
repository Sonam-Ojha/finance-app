<x-app-layout>
    <x-slot name="title">Recurring</x-slot>
    <x-slot name="subtitle">Rent, EMIs, subscriptions, salary — added for you on their day, so the month adds up without typing them again.</x-slot>
    <x-slot name="actions">
        <a href="{{ route('recurring.create', ['kind' => 'income']) }}" class="btn-secondary"><x-icon name="plus" class="w-5 h-5" /> Recurring income</a>
        <a href="{{ route('recurring.create') }}" class="btn-primary"><x-icon name="plus" class="w-5 h-5" /> Recurring expense</a>
    </x-slot>

    @if($rules->isEmpty())
        <div class="card py-12 text-center">
            <p class="text-3xl" aria-hidden="true">🔁</p>
            <p class="mt-2 font-semibold strong">Nothing repeats yet</p>
            <p class="mx-auto mt-1 max-w-md text-sm muted">Add your rent, an EMI or Netflix once, and it goes into your expenses by itself every month.</p>
        </div>
    @else
        {{-- Two to a row on wide screens --}}
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach($rules as $rule)
                @php $income = $rule->kind === 'income'; @endphp
                <div class="card flex flex-col gap-3 {{ $rule->active ? '' : 'opacity-70' }}">
                    <div class="flex items-start gap-3">
                        @if($income)
                            <x-tracker.badge icon="💰" color="#10B981" />
                        @else
                            <x-tracker.badge :icon="$rule->category?->icon ?? '🔁'" :color="$rule->category?->color ?? '#94A3B8'" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold strong">{{ $rule->note ?: ($income ? $rule->source : ($rule->category?->name ?? 'Expense')) }}</p>
                            <p class="text-[13px] muted">{{ $income ? $rule->source : $rule->category?->name }} · {{ $rule->describe() }}</p>
                        </div>
                        <p class="font-display text-lg font-semibold {{ $income ? 'money-in' : 'money-out' }}">{{ $income ? '+' : '−' }}{{ $money->format($rule->amount_minor) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-surface-page pt-3 text-[13px] dark:border-night-line">
                        <span class="muted">
                            @if($rule->active)
                                Next: <span class="font-semibold strong">{{ $rule->next_on->format('D j M Y') }}</span>
                            @else
                                <span class="adm-badge-medium">Paused</span>
                            @endif
                            @if($rule->last_on) · last added {{ $rule->last_on->format('j M') }} @endif
                        </span>
                        <span class="flex items-center gap-1">
                            <form method="POST" action="{{ route('recurring.toggle', $rule) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="link px-2 py-1">{{ $rule->active ? 'Pause' : 'Resume' }}</button>
                            </form>
                            <a href="{{ route('recurring.edit', $rule) }}" class="link px-2 py-1">Edit</a>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
