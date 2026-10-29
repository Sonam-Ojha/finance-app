@props(['icon', 'color' => '#94A3B8', 'size' => 'w-10 h-10 text-lg'])
{{-- A category or income-source icon on a tint of its colour. --}}
<span {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-full flex-shrink-0 leading-none {$size}"]) }}
      style="background-color: {{ $color }}2e">{{ $icon }}</span>
