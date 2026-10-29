<x-app-layout>
    <x-slot name="title">Transactions</x-slot>
    @php
        // Links that change one thing and keep the rest of the query.
        $link = fn (array $changes, array $drop = []) => route('transactions.index', array_filter(
            array_merge(\Illuminate\Support\Arr::except(request()->query(), array_merge(['page'], $drop)), $changes),
            fn ($v) => $v !== null && $v !== '',
        ));
        $advanced = ['category', 'payment', 'min', 'max', 'from', 'to', 'sort'];
        $filtering = $filters['search'] !== '' || $filters['type'] || $filters['count'] > 0;
        $shortMonth = \Carbon\Carbon::parse($month . '-01')->format('M');
        // The report's month picker: this month and the two years before it.
        $reportMonths = collect(range(0, 23))->mapWithKeys(function ($i) {
            $m = \App\Support\Labels::now()->startOfMonth()->subMonths($i);
            return [$m->format('Y-m') => $m->format('F Y')];
        });
    @endphp

    <x-slot name="actions">
        {{-- Export: the list as CSV (with the filters below), or a month's report as PDF --}}
        <div class="relative" x-data="{ exportOpen: false }" @click.outside="exportOpen = false" @keydown.escape="exportOpen = false">
            <button type="button" class="btn-secondary" @click="exportOpen = !exportOpen" :aria-expanded="exportOpen" aria-haspopup="true" aria-controls="export-menu">
                <x-icon name="download" class="w-5 h-5" /> Export <x-icon name="chevron-down" class="w-4 h-4" />
            </button>
            <div id="export-menu" x-show="exportOpen" x-cloak x-transition.opacity
                 class="card !p-3 absolute left-0 sm:left-auto sm:right-0 z-20 mt-2 w-[min(20rem,calc(100vw-2rem))] space-y-3 border border-surface-line dark:border-night-line">
                <a href="{{ route('transactions.export', request()->query()) }}" class="row-link !px-2 !py-2" @click="exportOpen = false">
                    <span class="icon-tile"><x-icon name="list" /></span>
                    <span class="min-w-0">
                        <span class="block font-semibold strong">CSV (current filters)</span>
                        <span class="block text-xs muted">{{ $filters['custom_dates'] || $filtering ? 'The list below, every page of it' : 'Everything in ' . $monthLabel }}</span>
                    </span>
                </a>
                <form method="GET" action="{{ route('reports.pdf') }}" class="border-t border-surface-line pt-3 dark:border-night-line">
                    <label for="report-month" class="field-label px-2">Monthly report PDF</label>
                    <div class="flex items-center gap-2 px-2 pb-1">
                        <select id="report-month" name="month" class="input !min-h-10 !py-1.5">
                            @foreach($reportMonths as $key => $label)
                                <option value="{{ $key }}" @selected($key === $month)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-primary !min-h-10 !px-4" @click="exportOpen = false">PDF</button>
                    </div>
                    <p class="px-2 text-xs muted">Totals, categories, budgets and every entry.</p>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4" x-data="{ filtersOpen: @js(! empty($filterErrors)) }">

        {{-- Header: search and the filter sheet --}}
        <div class="flex items-center gap-2">
            <form method="GET" action="{{ route('transactions.index') }}" class="flex-1 relative">
                @foreach(\Illuminate\Support\Arr::except(request()->query(), ['search', 'page', 'find']) as $key => $value)
                    @if(is_string($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted dark:text-night-muted" />
                <input type="search" name="search" value="{{ $filters['search'] }}" maxlength="100"
                       placeholder="Search transactions (e.g. Swiggy, Groceries)" @if($filters['find']) autofocus @endif
                       class="input !pl-9" aria-label="Search transactions">
            </form>
            <button type="button" @click="filtersOpen = !filtersOpen" class="relative btn-secondary !px-3" aria-label="Filter and sort">
                <x-icon name="funnel" class="w-4 h-4" />
                <span class="hidden sm:inline">Filter & sort</span>
                @if($filters['count'])
                    <span class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-brand-navy text-white dark:bg-brand-cyan dark:text-brand-navy text-[11px] font-bold flex items-center justify-center">{{ $filters['count'] }}</span>
                @endif
            </button>
        </div>

        {{-- Filter & sort --}}
        <form method="GET" action="{{ route('transactions.index') }}" x-show="filtersOpen" x-cloak x-transition.opacity class="card space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="card-title">Filter & sort</h3>
                <button type="button" @click="filtersOpen = false" class="p-1 muted" aria-label="Close"><x-icon name="x" /></button>
            </div>
            <input type="hidden" name="month" value="{{ $month }}">
            @if($filters['search'] !== '')<input type="hidden" name="search" value="{{ $filters['search'] }}">@endif
            @if($filters['type'] && ! $filters['category'])<input type="hidden" name="type" value="{{ $filters['type'] }}">@endif

            <fieldset>
                <legend class="field-label">Sort by</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach(\App\Http\Controllers\Tracker\TransactionController::SORT_LABELS as $key => $label)
                        <label><input type="radio" name="sort" value="{{ $key }}" class="peer sr-only" @checked($filters['sort'] === $key)><span class="chip">{{ $label }}</span></label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="field-label">Category</legend>
                <div class="flex flex-wrap gap-2">
                    <label><input type="radio" name="category" value="" class="peer sr-only" @checked(! $filters['category'])><span class="chip">Any</span></label>
                    @foreach($categories as $category)
                        <label><input type="radio" name="category" value="{{ $category->id }}" class="peer sr-only" @checked($filters['category']?->id === $category->id)><span class="chip">{{ $category->icon }} {{ $category->name }}</span></label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="field-label">Payment method</legend>
                <div class="flex flex-wrap gap-2">
                    <label><input type="radio" name="payment" value="" class="peer sr-only" @checked(! $filters['payment'])><span class="chip">Any</span></label>
                    @foreach(['upi', 'cash', 'debit_card', 'credit_card', 'bank_transfer', 'other'] as $method)
                        <label><input type="radio" name="payment" value="{{ $method }}" class="peer sr-only" @checked($filters['payment'] === $method)><span class="chip">{{ \App\Support\PaymentMethods::label($method) }}</span></label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid sm:grid-cols-2 gap-4">
                <fieldset>
                    <legend class="field-label">Amount</legend>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm muted">{{ $money->symbol }}</span>
                            <input type="text" inputmode="decimal" name="min" value="{{ $filters['min'] }}" placeholder="Min" class="input !pl-9" aria-label="Minimum amount">
                        </div>
                        <span class="text-sm muted">to</span>
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm muted">{{ $money->symbol }}</span>
                            <input type="text" inputmode="decimal" name="max" value="{{ $filters['max'] }}" placeholder="Max" class="input !pl-9" aria-label="Maximum amount">
                        </div>
                    </div>
                </fieldset>
                <fieldset x-data="{ from: @js($filters['from'] ?? $monthStart), to: @js($filters['to'] ?? $monthEnd) }">
                    <legend class="field-label flex items-center justify-between w-full">Dates
                        <button type="button" class="link normal-case tracking-normal font-medium" @click="from = @js($monthStart); to = @js($monthEnd)">Whole month</button>
                    </legend>
                    <div class="flex items-center gap-2">
                        <input type="date" name="from" x-model="from" :max="to || @js(\App\Support\Labels::now()->toDateString())" class="input" aria-label="From">
                        <span class="text-sm muted">to</span>
                        <input type="date" name="to" x-model="to" :min="from" max="{{ \App\Support\Labels::now()->toDateString() }}" class="input" aria-label="To">
                    </div>
                </fieldset>
            </div>

            @foreach($filterErrors as $error)
                <p class="text-sm text-status-error dark:text-red-400">{{ $error }}</p>
            @endforeach

            <div class="flex justify-end gap-2">
                <a href="{{ $link([], $advanced) }}" class="btn-secondary">Reset</a>
                {{-- The month's own window is not a custom range, so it is not sent as one. --}}
                <button type="submit" class="btn-primary"
                        @click="const f = $el.form.elements; if (f.from.value === @js($monthStart) && f.to.value === @js($monthEnd)) { f.from.disabled = true; f.to.disabled = true; }">Apply</button>
            </div>
        </form>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- The month at a glance --}}
            <div class="card space-y-4">
                <x-tracker.month-switcher :month="$month" route="transactions.index"
                                          :params="\Illuminate\Support\Arr::except(request()->query(), ['month', 'page', 'from', 'to'])" />
                <div class="grid grid-cols-3 gap-2 text-center text-[13px] sm:text-base leading-tight tracking-tight">
                    <div class="min-w-0"><p class="text-xs muted tracking-normal">Income</p><p class="font-bold money-in break-all">+{{ $money->format($received) }}</p></div>
                    <div class="min-w-0"><p class="text-xs muted tracking-normal">Expenses</p><p class="font-bold money-out break-all">−{{ $money->format($spent) }}</p></div>
                    <div class="min-w-0"><p class="text-xs muted tracking-normal">Net</p><p class="font-bold strong break-all">{{ $money->format($received - $spent) }}</p></div>
                </div>
                <x-tracker.donut :breakdown="$breakdown" :money="$money" :caption="'Spent in ' . $shortMonth"
                                 :link="fn ($id) => $link(['category' => $id, 'type' => 'expense']) . '#list'"
                                 :empty="$isCurrent ? 'Nothing spent this month yet.' : 'Nothing was spent in ' . $monthLabel . '.'" />
            </div>

            {{-- Income vs Expense --}}
            <div class="card">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h3 class="card-title">Income vs Expense</h3>
                    <form method="GET" action="{{ route('transactions.index') }}">
                        @foreach(\Illuminate\Support\Arr::except(request()->query(), ['range', 'page']) as $key => $value)
                            @if(is_string($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                        @endforeach
                        <select name="range" onchange="this.form.submit()" class="input !w-auto !py-1.5 !text-xs" aria-label="Range">
                            @foreach(\App\Http\Controllers\Tracker\TransactionController::RANGES as $key => $label)
                                <option value="{{ $key }}" @selected($range === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="h-64"><canvas x-data x-init="barChart($el, @js($chart))"></canvas></div>
            </div>
        </div>

        {{-- All Transactions --}}
        <div class="card" id="list">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="card-title">All Transactions</h3>
                <div class="seg">
                    <a href="{{ $link(['type' => null], ['category']) }}" class="seg-item {{ ! $filters['type'] ? 'seg-on' : '' }}">All</a>
                    <a href="{{ $link(['type' => 'income'], ['category']) }}" class="seg-item {{ $filters['type'] === 'income' ? 'seg-on' : '' }}">Income</a>
                    <a href="{{ $link(['type' => 'expense']) }}" class="seg-item {{ $filters['type'] === 'expense' ? 'seg-on' : '' }}">Expenses</a>
                </div>
            </div>

            @if($filters['chips'])
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    @foreach($filters['chips'] as $key => $label)
                        @php $remove = match ($key) { 'dates' => ['from', 'to'], 'amount' => ['min', 'max'], default => [$key] }; @endphp
                        <a href="{{ $link([], $remove) }}" class="chip chip-on !py-1 text-xs">{{ $label }} <x-icon name="x" class="w-3.5 h-3.5" /></a>
                    @endforeach
                    @if(count($filters['chips']) > 1)
                        <a href="{{ $link([], $advanced) }}" class="link text-xs">Clear all</a>
                    @endif
                </div>
            @endif

            @if($groups->isEmpty())
                <div class="text-center py-10">
                    @if($filtering)
                        <p class="font-semibold strong">No matches</p>
                        <p class="text-sm muted mt-1">{{ $filters['custom_dates'] ? 'Nothing in this date range matches the search and filters you picked.' : 'Nothing in ' . $monthLabel . ' matches the search and filters you picked.' }}</p>
                    @elseif($isCurrent)
                        <p class="font-semibold strong">Nothing logged yet</p>
                        <p class="text-sm muted mt-1">Use the + button to record an expense or income for this month.</p>
                    @else
                        <p class="font-semibold strong">Nothing recorded</p>
                        <p class="text-sm muted mt-1">No expense or income was recorded in {{ $monthLabel }}.</p>
                    @endif
                </div>
            @else
                <div class="mt-2 space-y-4">
                    @foreach($groups as $group)
                        <section>
                            <div class="flex items-center justify-between gap-2 pt-2 pb-1 border-b border-surface-page dark:border-night-line">
                                <h4 class="section-label">{{ $group['label'] }}</h4>
                                @if($byDay)
                                    <p class="text-xs font-semibold space-x-2">
                                        @if($group['received'])<span class="money-in">+{{ $money->format($group['received']) }}</span>@endif
                                        @if($group['spent'])<span class="money-out">−{{ $money->format($group['spent']) }}</span>@endif
                                    </p>
                                @endif
                            </div>
                            <div class="divide-soft">
                                @foreach($group['rows'] as $row)
                                    <x-tracker.txn-row :row="$row" :money="$money" :show-day="! $byDay" deletable />
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
                @if($page->hasPages())
                    <div class="mt-4">{{ $page->fragment('list')->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
