<x-app-layout>
    <x-slot name="title">Home</x-slot>
    @php
        $hour = \App\Support\Labels::now()->hour;
        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
        $firstName = strtok(trim($user->name ?? ''), ' ');
        $mask = '••••••';
    @endphp
    <x-slot name="heading">{{ $greeting }}{{ $firstName ? ", {$firstName}" : '' }} 👋</x-slot>
    <x-slot name="subtitle">{{ \App\Support\Labels::now()->format('l, j F') }} · your money this month</x-slot>

    <div class="space-y-4"
         x-data="{
             hide: (() => { try { return localStorage.getItem('home_hide_balance') === '1' } catch (e) { return false } })(),
             toggle() { this.hide = !this.hide; try { localStorage.setItem('home_hide_balance', this.hide ? '1' : '0') } catch (e) {} },
         }">

        <div class="grid gap-4 lg:grid-cols-5">
            {{-- Balance --}}
            <div class="lg:col-span-3 rounded-card bg-brand-navy p-6 text-white">
                <div class="flex items-center gap-2 text-sm text-white/80">
                    Total Balance
                    <button type="button" @click="toggle()" class="flex h-9 w-9 items-center justify-center rounded-chip hover:bg-white/10" :aria-label="hide ? 'Show amounts' : 'Hide amounts'">
                        <x-icon name="eye" class="w-4 h-4" x-show="!hide" />
                        <x-icon name="eye-off" class="w-4 h-4" x-show="hide" x-cloak />
                    </button>
                </div>
                <p class="mt-1 font-display text-[32px] font-semibold leading-[1.1] sm:text-[40px]" x-text="hide ? @js($mask) : @js($money->format($balance))">{{ $money->format($balance) }}</p>
                <div class="mt-2 text-sm">
                    @if($noIncome)
                        <a href="{{ route('incomes.create') }}" class="font-semibold text-white underline underline-offset-2">Add your income to see what is left →</a>
                    @else
                        <span x-show="!hide" class="[&_*]:!text-white/90 [&_.muted]:!text-white/80">
                            <x-tracker.change :change="$balanceChange" suffix="from last month" />
                        </span>
                    @endif
                </div>
            </div>

            {{-- Quick actions --}}
            <div class="lg:col-span-2 card grid grid-cols-4 gap-2 content-center">
                @foreach([
                    [route('expenses.create'), 'Add Expense', 'plus', 'bg-red-50 text-status-error dark:bg-red-500/15 dark:text-red-300'],
                    [route('incomes.create'), 'Add Income', 'plus', 'bg-emerald-50 text-status-successText dark:bg-emerald-500/15 dark:text-emerald-300'],
                    [route('budgets.index'), 'Budgets', 'wallet', ''],
                    [route('categories.index'), 'Categories', 'tag', ''],
                ] as [$href, $label, $icon, $tone])
                    <a href="{{ $href }}" class="flex flex-col items-center gap-2 rounded-card py-2 hover:bg-surface-page dark:hover:bg-white/5">
                        <span class="icon-tile !h-12 !w-12 {{ $tone }}"><x-icon :name="$icon" /></span>
                        <span class="text-[13px] font-semibold strong text-center">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- This month --}}
        <div class="grid grid-cols-3 gap-3">
            @foreach($tiles as [$label, $amount, $change, $goodUp, $tone])
                <div class="card !px-3 !py-4 sm:!px-6 sm:!py-5 min-w-0">
                    <p class="text-[13px] sm:text-sm muted">{{ $label }}</p>
                    <p class="mt-1 font-display text-[13px] sm:text-2xl font-semibold leading-tight break-all {{ $tone }}" x-text="hide ? @js($mask) : @js($money->format($amount))">{{ $money->format($amount) }}</p>
                    <div class="mt-1">
                        <span x-show="!hide"><x-tracker.change :change="$change" :good-up="$goodUp" /></span>
                        <span x-show="hide" x-cloak class="text-xs muted">—</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Spending Overview --}}
            <div class="card" x-data="{
                     period: 'this',
                     data: @js($overview),
                     chart: null,
                     draw() { this.chart?.destroy(); this.chart = barChart(this.$refs.canvas, this.data[this.period]); },
                 }" x-init="draw()">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h3 class="card-title">Spending Overview</h3>
                    <select x-model="period" @change="draw()" class="input !w-auto !py-1.5 !text-xs" aria-label="Period">
                        <option value="this">This Month</option>
                        <option value="last">Last Month</option>
                    </select>
                </div>
                <div class="h-56"><canvas x-ref="canvas"></canvas></div>
                <p class="mt-2 text-[11px] muted text-center" x-text="data[period].captions.join(' · ')"></p>
            </div>

            {{-- Expense by Category --}}
            <div class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="card-title">Expense by Category</h3>
                    <a href="{{ route('transactions.index') }}" class="link text-xs">View All</a>
                </div>
                <x-tracker.donut :breakdown="$breakdown" :money="$money" hideable
                                 empty="Nothing spent this month yet. Each category shows up here as you spend in it." />
            </div>
        </div>

        {{-- Recent Transactions --}}
        <div class="card">
            <div class="flex items-center justify-between mb-1">
                <h3 class="card-title">Recent Transactions</h3>
                <a href="{{ route('transactions.index') }}" class="link text-xs">View All</a>
            </div>
            @if($recent->isEmpty())
                <div class="text-center py-8">
                    <p class="font-semibold strong">Nothing logged yet</p>
                    <p class="text-sm muted mt-1">Use the + button to record your first expense or income this month.</p>
                </div>
            @else
                <div class="divide-soft">
                    @foreach($recent as $row)
                        <x-tracker.txn-row :row="$row" :money="$money" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
