<x-app-layout>
    <x-slot name="title">Budgets</x-slot>
    @php $pad = mb_strlen($money->symbol) > 1 ? '!pl-12' : '!pl-8'; @endphp

    <form method="POST" action="{{ route('budgets.update') }}" class="max-w-2xl mx-auto space-y-4">
        @csrf @method('PUT')
        <input type="hidden" name="month" value="{{ $month }}">

        <div class="card">
            <x-tracker.month-switcher :month="$month" route="budgets.index" />
        </div>

        {{-- Whole month --}}
        <div class="card space-y-3">
            <div class="flex items-center gap-3">
                <x-tracker.badge icon="👛" color="#6366F1" />
                <label for="whole" class="flex-1 font-semibold strong">Whole month</label>
                <div class="relative w-40">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm muted">{{ $money->symbol }}</span>
                    <input id="whole" name="whole" type="text" inputmode="decimal" autocomplete="off" placeholder="None"
                           value="{{ old('whole', $wholeValue) }}" class="input text-right {{ $pad }}">
                </div>
            </div>
            @error('whole')<p class="field-error">{{ $message }}</p>@enderror
            @if($wholeUsage)
                <x-tracker.budget-bar :usage="$wholeUsage" />
                <div class="flex justify-between text-xs muted">
                    <span>{{ $wholeUsage['progress'] }}</span>
                    <span class="{{ $wholeUsage['over'] ? 'money-out font-semibold' : '' }}">{{ $wholeUsage['remainder'] }}</span>
                </div>
            @else
                <p class="text-xs muted">{{ $money->format($spentThisMonth) }} spent</p>
            @endif
        </div>

        {{-- Each category --}}
        <div class="card divide-soft !py-1">
            @foreach($rows as $row)
                @php $field = "budgets.{$row['category']->id}"; @endphp
                <div class="py-3 space-y-2">
                    <div class="flex items-center gap-3">
                        <x-tracker.badge :icon="$row['category']->icon" :color="$row['category']->color" size="w-9 h-9 text-base" />
                        <label for="budget-{{ $row['category']->id }}" class="flex-1 text-sm font-medium strong truncate">{{ $row['category']->name }}</label>
                        <div class="relative w-40">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm muted">{{ $money->symbol }}</span>
                            <input id="budget-{{ $row['category']->id }}" name="budgets[{{ $row['category']->id }}]" type="text" inputmode="decimal" autocomplete="off"
                                   placeholder="{{ $row['standing'] ? ltrim(str_replace($money->symbol, '', $row['standing'])) : 'None' }}"
                                   value="{{ old($field, $row['value']) }}" class="input text-right {{ $pad }}">
                        </div>
                    </div>
                    @error($field)<p class="field-error">{{ $message }}</p>@enderror
                    @if($row['usage'])
                        <x-tracker.budget-bar :usage="$row['usage']" />
                        <div class="flex justify-between text-xs muted">
                            <span>{{ $row['usage']['progress'] }}{{ $row['everyMonth'] ? ' · every month' : '' }}</span>
                            <span class="{{ $row['usage']['over'] ? 'money-out font-semibold' : '' }}">{{ $row['usage']['remainder'] }}</span>
                        </div>
                    @else
                        <p class="text-xs muted">{{ $money->format($row['spent']) }} spent</p>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="text-xs muted">Budgets here apply to {{ $monthLabel }} only. An empty category field uses its every-month budget, shown in grey.</p>

        <div class="flex items-center justify-between gap-2">
            <a href="{{ route('budgets.defaults') }}" class="card !p-3 flex-1 flex items-center justify-between hover:shadow-md">
                <span class="text-sm font-medium strong">Every-month budgets</span><x-icon name="chevron-right" class="w-4 h-4 muted" />
            </a>
            <button type="submit" class="btn-primary !py-3">Save</button>
        </div>
    </form>
</x-app-layout>
