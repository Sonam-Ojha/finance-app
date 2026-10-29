@props(['usage'])
{{-- Red once over, amber from 80% (MonthBudgets::NEAR_LIMIT), otherwise the brand colour. --}}
@php
    $color = $usage['over'] ? 'bg-status-error' : ($usage['ratio'] >= \App\Support\MonthBudgets::NEAR_LIMIT ? 'bg-status-warning' : 'bg-brand-navy dark:bg-brand-cyan');
@endphp
<div {{ $attributes->merge(['class' => 'bar-track']) }}>
    <div class="h-full rounded-full {{ $color }}" style="width: {{ round(min($usage['ratio'], 1) * 100, 1) }}%"></div>
</div>
