<x-app-layout>
    <x-slot name="title">Analytics</x-slot>
    @php
        // A range picker that keeps the other chart's range.
        $rangeSelect = function (string $name, string $value) use ($ranges) {
            $keep = \Illuminate\Support\Arr::except(request()->query(), [$name]);
            $html = '<form method="GET" action="' . e(route('analytics.index')) . '">';
            foreach ($keep as $k => $v) if (is_string($v)) $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
            $html .= '<select name="' . e($name) . '" onchange="this.form.submit()" class="input !w-auto !py-1.5 !text-xs" aria-label="Range">';
            foreach ($ranges as $k => $label) $html .= '<option value="' . $k . '"' . ($k === $value ? ' selected' : '') . '>' . $label . '</option>';
            return $html . '</select></form>';
        };
    @endphp

    <div class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="card">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h3 class="card-title">Income vs Expense</h3>
                    {!! $rangeSelect('range', $range) !!}
                </div>
                <div class="h-64"><canvas x-data x-init="barChart($el, @js($chart))"></canvas></div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <h3 class="card-title">Trend</h3>
                    {!! $rangeSelect('trend', $trendRange) !!}
                </div>
                @if($trendEmpty)
                    <p class="text-sm muted text-center py-24">Nothing recorded in this period yet</p>
                @else
                    <div x-data x-init="trendChart($refs.canvas, @js($trend), $refs.readout)">
                        <div x-ref="readout" class="mb-2">
                            <p class="text-xs muted" data-caption></p>
                            <p class="text-sm"><span class="muted">Income</span> <span class="font-semibold money-in" data-income></span>
                               <span class="muted ml-3">Expense</span> <span class="font-semibold money-out" data-expense></span></p>
                        </div>
                        <div class="h-52"><canvas x-ref="canvas" class="cursor-pointer"></canvas></div>
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            @foreach([['Expenses', $spent, 'money-out', route('expenses.create')], ['Income', $received, 'money-in', route('incomes.create')]] as [$label, $amount, $tone, $add])
                <div class="card !p-4 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs muted">{{ $label }}</p>
                        <p class="text-lg sm:text-xl font-bold truncate {{ $tone }}">{{ $money->format($amount) }}</p>
                    </div>
                    <a href="{{ $add }}" class="w-9 h-9 rounded-full bg-surface-page dark:bg-night-line flex items-center justify-center strong hover:bg-surface-field dark:hover:bg-white/10" aria-label="Add {{ strtolower($label) === 'expenses' ? 'expense' : 'income' }}">
                        <x-icon name="plus" class="w-4 h-4" />
                    </a>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Monthly Budgets --}}
            <div class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="card-title">Monthly Budgets</h3>
                    <a href="{{ route('budgets.index') }}" class="link text-xs">{{ $budgets ? 'Edit' : 'Set up' }}</a>
                </div>
                @forelse($budgets as $row)
                    <div class="py-2.5">
                        <div class="flex items-center gap-3">
                            <x-tracker.badge :icon="$row['icon']" :color="$row['color']" size="w-8 h-8 text-base" />
                            <span class="flex-1 text-sm font-medium strong truncate">{{ $row['name'] }}</span>
                            <span class="text-sm font-semibold {{ $row['usage']['over'] ? 'money-out' : 'muted' }}">{{ $row['percent'] }}%</span>
                        </div>
                        <x-tracker.budget-bar :usage="$row['usage']" class="mt-2" />
                        <div class="mt-1 flex justify-between text-xs muted">
                            <span>{{ $row['usage']['progress'] }}</span>
                            <span class="{{ $row['usage']['over'] ? 'money-out font-semibold' : '' }}">{{ $row['usage']['remainder'] }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm muted py-6 text-center">No budget for this month yet. Set one and its progress shows up here.</p>
                @endforelse
            </div>

            {{-- History --}}
            <div class="card">
                <h3 class="card-title mb-2">History</h3>
                @forelse($history as $day)
                    <div class="flex items-center justify-between py-2.5 border-b last:border-0 border-surface-page dark:border-night-line">
                        <span class="text-sm strong">{{ $day['label'] }}</span>
                        <span class="text-sm font-semibold space-x-2">
                            @if($day['income_minor'])<span class="money-in">+{{ $money->format($day['income_minor']) }}</span>@endif
                            @if($day['expense_minor'])<span class="money-out">−{{ $money->format($day['expense_minor']) }}</span>@endif
                        </span>
                    </div>
                @empty
                    <div class="text-center py-6">
                        <p class="font-semibold strong">No history yet</p>
                        <p class="text-sm muted mt-1">Days with money in or out show up here as you record them.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <p class="text-xs muted text-center">Totals above cover the {{ strtolower($ranges[$range]) }} range in the chart.</p>
    </div>
</x-app-layout>
