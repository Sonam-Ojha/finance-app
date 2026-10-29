@props(['change', 'goodUp' => true, 'suffix' => null])
{{-- This month against the last, as App\Support\MonthChange worked it out. --}}
@if($change['kind'] === 'percent')
    @php
        $value = $change['value'];
        $tone = $value === 0 ? 'text-ink-muted dark:text-night-muted' : (($value > 0) === $goodUp ? 'text-status-successText dark:text-emerald-400' : 'text-status-error dark:text-red-400');
    @endphp
    <span {{ $attributes->merge(['class' => "inline-flex items-center gap-0.5 text-xs font-semibold {$tone}"]) }}>
        <x-icon :name="$value > 0 ? 'arrow-up' : ($value < 0 ? 'arrow-down' : 'minus')" class="w-3.5 h-3.5" />
        {{ $value > 0 ? '+' : ($value < 0 ? '−' : '') }}{{ abs($value) }}%
        @if($suffix)<span class="font-normal muted">&nbsp;{{ $suffix }}</span>@endif
    </span>
@elseif($change['kind'] === 'new')
    <span {{ $attributes->merge(['class' => 'text-xs font-semibold text-brand-navy dark:text-brand-cyan']) }}>New{{ $suffix ? ' since last month' : '' }}</span>
@else
    <span {{ $attributes->merge(['class' => 'text-xs muted']) }}>—</span>
@endif
