@props(['breakdown', 'money', 'caption' => 'Total', 'empty' => '', 'link' => null, 'hideable' => false])
{{--
    Spending by category from Reports::slices(): the ring, the total in its
    middle and one row per slice. `link` turns a named slice's row into a
    link: a closure taking the category id. `hideable` masks the total while
    the surrounding x-data's `hide` is on (Home's eye toggle).
--}}
@php
    $total = $money->format($breakdown['total']);
    $centre = mb_strlen($total) > 10 ? $money->compact($breakdown['total']) : $total;
    $chart = collect($breakdown['slices'])->map(fn ($s) => ['name' => $s['name'], 'total' => $s['total_minor'], 'color' => $s['color']])->all();
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row items-center gap-5']) }}>
    <div class="relative w-40 h-40 flex-shrink-0">
        <canvas x-data x-init="donutChart($el, @js($chart))" aria-label="Spending by category"></canvas>
        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
            <span class="font-bold strong text-lg"
                  @if($hideable) x-text="hide ? '••••••' : @js($centre)" @endif>{{ $centre }}</span>
            <span class="text-xs muted">{{ $caption }}</span>
        </div>
    </div>

    @if(empty($breakdown['slices']))
        <p class="flex-1 text-sm muted text-center sm:text-left">{{ $empty }}</p>
    @else
        <ul class="flex-1 w-full space-y-1">
            @foreach($breakdown['slices'] as $slice)
                <li>
                    @if($link && $slice['category_id'])
                        <a href="{{ $link($slice['category_id']) }}" class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-surface-page dark:hover:bg-white/5">
                            <x-tracker.badge :icon="$slice['icon']" :color="$slice['color']" size="w-8 h-8 text-base" />
                            <span class="flex-1 text-sm strong truncate">{{ $slice['name'] }}</span>
                            <span class="text-sm font-semibold muted">{{ $slice['share'] }}%</span>
                        </a>
                    @else
                        <div class="flex items-center gap-3 px-2 py-1.5">
                            <x-tracker.badge :icon="$slice['icon']" :color="$slice['color']" size="w-8 h-8 text-base" />
                            <span class="flex-1 text-sm strong truncate">{{ $slice['name'] }}</span>
                            <span class="text-sm font-semibold muted">{{ $slice['share'] }}%</span>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
