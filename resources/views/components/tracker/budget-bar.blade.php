@props(['usage'])
{{-- Red once over, amber from 80% (MonthBudgets::NEAR_LIMIT), otherwise the brand colour. --}}
@php
    $color = $usage['over'] ? 'bg-red-500' : ($usage['ratio'] >= \App\Support\MonthBudgets::NEAR_LIMIT ? 'bg-amber-500' : 'bg-indigo-600 dark:bg-indigo-400');
@endphp
<div {{ $attributes->merge(['class' => 'bar-track']) }}>
    <div class="h-full rounded-full {{ $color }}" style="width: {{ round(min($usage['ratio'], 1) * 100, 1) }}%"></div>
</div>
