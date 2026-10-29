@props(['month', 'route', 'params' => []])
{{-- ‹ October 2026 ▾ › — never past the current month, as in the app. --}}
@php
    $current = \Carbon\Carbon::parse($month . '-01');
    $now     = \App\Support\Labels::now()->startOfMonth();
    $url     = fn (string $m) => route($route, array_merge($params, ['month' => $m]));
    $picker  = $url('MONTH_PLACEHOLDER');
    $atLatest = $current->gte($now);
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-2']) }}
     x-data="{ open: false, year: {{ $current->year }} }">
    <a href="{{ $url($current->copy()->subMonth()->format('Y-m')) }}" aria-label="Previous month"
       class="p-2 rounded-lg muted hover:bg-surface-page dark:hover:bg-white/5"><x-icon name="chevron-left" /></a>

    <div class="relative">
        <button type="button" @click="open = !open" class="inline-flex items-center gap-1 font-semibold strong px-2 py-1 rounded-lg hover:bg-surface-page dark:hover:bg-white/5">
            {{ $current->format('F Y') }} <x-icon name="chevron-down" class="w-4 h-4" />
        </button>
        <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity
             class="absolute z-20 left-1/2 -translate-x-1/2 mt-2 w-64 card border border-surface-line shadow-lg !p-3 dark:border-night-line">
            <div class="flex items-center justify-between mb-2">
                <button type="button" @click="year--" class="p-1.5 rounded-lg muted hover:bg-surface-page dark:hover:bg-white/5" aria-label="Previous year"><x-icon name="chevron-left" class="w-4 h-4" /></button>
                <span class="font-semibold strong" x-text="year"></span>
                <button type="button" @click="year++" :disabled="year >= {{ $now->year }}" class="p-1.5 rounded-lg muted hover:bg-surface-page dark:hover:bg-white/5 disabled:opacity-30" aria-label="Next year"><x-icon name="chevron-right" class="w-4 h-4" /></button>
            </div>
            <div class="grid grid-cols-3 gap-1">
                @foreach(range(1, 12) as $m)
                    @php $key = sprintf('%02d', $m); @endphp
                    <a :href="@js($picker).replace('MONTH_PLACEHOLDER', year + '-{{ $key }}')"
                       class="text-center text-sm py-2 rounded-lg hover:bg-surface-page dark:hover:bg-white/5"
                       :class="{
                           'pointer-events-none opacity-30': year > {{ $now->year }} || (year === {{ $now->year }} && {{ $m }} > {{ $now->month }}),
                           'bg-brand-navy text-white hover:bg-brand-navy dark:bg-brand-cyan dark:text-brand-navy dark:hover:bg-brand-cyan': year === {{ $current->year }} && {{ $m }} === {{ $current->month }},
                       }">{{ \Carbon\Carbon::create(2000, $m, 1)->format('M') }}</a>
                @endforeach
            </div>
            <a href="{{ $url($now->format('Y-m')) }}" class="link text-sm block text-center mt-2">Jump to this month</a>
        </div>
    </div>

    @if($atLatest)
        <span class="p-2 opacity-30 muted" aria-hidden="true"><x-icon name="chevron-right" /></span>
    @else
        <a href="{{ $url($current->copy()->addMonth()->format('Y-m')) }}" aria-label="Next month"
           class="p-2 rounded-lg muted hover:bg-surface-page dark:hover:bg-white/5"><x-icon name="chevron-right" /></a>
    @endif
</div>
