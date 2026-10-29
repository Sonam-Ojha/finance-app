<x-app-layout>
    <x-slot name="title">Goals</x-slot>
    <x-slot name="subtitle">Money set aside for what matters — a trip, a gadget, a rainy day.</x-slot>
    <x-slot name="actions">
        <a href="{{ route('goals.create') }}" class="btn-primary"><x-icon name="plus" class="w-5 h-5" /> New goal</a>
    </x-slot>

    <div class="space-y-6">
        @if($active->isEmpty())
            <div class="card text-center py-10">
                <p class="text-4xl" aria-hidden="true">🎯</p>
                <p class="mt-3 font-semibold strong">{{ $archived->isEmpty() ? 'No goals yet' : 'No active goals' }}</p>
                <p class="text-sm muted mt-1">Pick something to save for, set a target, and add money as you go.</p>
                <a href="{{ route('goals.create') }}" class="btn-primary mt-5"><x-icon name="plus" class="w-5 h-5" /> New goal</a>
            </div>
        @else
            @php
                $saved  = $active->sum('saved_minor');
                $target = $active->sum('target_minor');
            @endphp
            <p class="text-sm muted">
                <span class="font-semibold strong">{{ $money->format($saved) }}</span> saved across {{ $active->count() }} {{ \Illuminate\Support\Str::plural('goal', $active->count()) }},
                of {{ $money->format($target) }} in all.
            </p>
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach($active as $goal)
                    @include('tracker.partials.goal-card', ['goal' => $goal])
                @endforeach
            </div>
        @endif

        @if($archived->isNotEmpty())
            <section class="space-y-3" x-data="{ show: false }">
                <button type="button" class="section-label inline-flex items-center gap-1" @click="show = !show" :aria-expanded="show">
                    Archived ({{ $archived->count() }})
                    <x-icon name="chevron-down" class="w-4 h-4 transition-transform" ::class="show && 'rotate-180'" />
                </button>
                <div class="grid gap-4 lg:grid-cols-2" x-show="show" x-cloak>
                    @foreach($archived as $goal)
                        @include('tracker.partials.goal-card', ['goal' => $goal])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
