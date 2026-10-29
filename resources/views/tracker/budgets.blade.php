<x-app-layout>
    <x-slot name="title">Budgets</x-slot>
    @php $pad = mb_strlen($money->symbol) > 1 ? '!pl-12' : '!pl-8'; @endphp

    <form method="POST" action="{{ route('budgets.update') }}" class="space-y-4">
        @csrf @method('PUT')
        <input type="hidden" name="month" value="{{ $month }}">

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="card flex items-center">
                <div class="w-full"><x-tracker.month-switcher :month="$month" route="budgets.index" /></div>
            </div>

            {{-- Whole month --}}
            <div class="card space-y-3">
                <div class="flex items-center gap-3">
                    <x-tracker.badge icon="👛" color="#6366F1" />
                    <label for="whole" class="flex-1 font-semibold strong">Whole month</label>
                    <div class="relative w-36 sm:w-40">
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
        </div>

        {{-- Each category: two to a row on wide screens --}}
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach($rows as $row)
                @php $field = "budgets.{$row['category']->id}"; @endphp
                <div class="card space-y-2">
                    <div class="flex items-center gap-3">
                        <x-tracker.badge :icon="$row['category']->icon" :color="$row['category']->color" size="w-9 h-9 text-base" />
                        <label for="budget-{{ $row['category']->id }}" class="min-w-0 flex-1 truncate font-semibold strong">{{ $row['category']->name }}</label>
                        <div class="relative w-36 sm:w-40">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm muted">{{ $money->symbol }}</span>
                            <input id="budget-{{ $row['category']->id }}" name="budgets[{{ $row['category']->id }}]" type="text" inputmode="decimal" autocomplete="off"
                                   placeholder="{{ $row['standing'] ? ltrim(str_replace($money->symbol, '', $row['standing'])) : 'None' }}"
                                   value="{{ old($field, $row['value']) }}" class="input text-right {{ $pad }}">
                        </div>
                    </div>
                    @error($field)<p class="field-error">{{ $message }}</p>@enderror
                    @if($row['usage'])
                        <x-tracker.budget-bar :usage="$row['usage']" />
                        <div class="flex justify-between gap-2 text-xs muted">
                            <span>{{ $row['usage']['progress'] }}{{ $row['everyMonth'] ? ' · every month' : '' }}</span>
                            <span class="{{ $row['usage']['over'] ? 'money-out font-semibold' : '' }}">{{ $row['usage']['remainder'] }}</span>
                        </div>
                    @else
                        <p class="text-xs muted">{{ $money->format($row['spent']) }} spent</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs muted">Budgets here apply to {{ $monthLabel }} only. An empty category field uses its every-month budget, shown in grey.</p>
            <div class="flex items-center gap-2">
                <a href="{{ route('budgets.defaults') }}" class="btn-secondary">Every-month budgets <x-icon name="chevron-right" class="w-4 h-4" /></a>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </div>
    </form>
</x-app-layout>
