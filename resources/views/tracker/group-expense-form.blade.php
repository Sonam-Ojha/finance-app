<x-app-layout>
    <x-slot name="title">{{ $expense ? 'Edit group expense' : 'New group expense' }}</x-slot>
    <x-slot name="subtitle">{{ $group->label() }}</x-slot>
    @php $pad = mb_strlen($money->symbol) > 1 ? '!pl-12' : '!pl-8'; @endphp

    <div class="space-y-4">
        <form method="POST" action="{{ $expense ? route('groups.expenses.update', [$group, $expense]) : route('groups.expenses.store', $group) }}"
              class="card space-y-5" x-data="groupExpenseForm(@js($init))">
            @csrf
            @if($expense) @method('PUT') @endif

            {{-- Two columns on wide screens: what and who paid | how it is split --}}
            <div class="grid gap-x-10 gap-y-5 lg:grid-cols-2">
                <div class="space-y-5">
                    <div x-data="{ description: @js(old('description', $expense?->description ?? '')) }">
                        <label for="description" class="field-label flex justify-between">What was it for? <span class="font-normal" x-text="description.length + '/80'"></span></label>
                        <input id="description" name="description" type="text" maxlength="80" required x-model="description" value="{{ old('description', $expense?->description) }}"
                               placeholder="e.g. Dinner, Cab to airport, Rent" @unless($expense) autofocus @endunless class="input">
                        @error('description')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="amount" class="field-label">Amount</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-semibold muted">{{ $money->symbol }}</span>
                            <input id="amount" name="amount" type="text" inputmode="decimal" autocomplete="off" required placeholder="0"
                                   x-model="amount" value="{{ $init['amount'] }}"
                                   class="input !py-3 !text-2xl !font-bold {{ mb_strlen($money->symbol) > 1 ? '!pl-16' : '!pl-10' }}">
                        </div>
                        @error('amount')
                            <p class="field-error">{{ $message }}</p>
                        @else
                            <p class="mt-1 text-xs muted">Amount in {{ $money->label }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="paid_by" class="field-label">Paid by</label>
                            <select id="paid_by" name="paid_by" class="input">
                                @foreach($people as $person)
                                    <option value="{{ $person['id'] }}" @selected($paidBy === $person['id'])>{{ $person['name'] }}</option>
                                @endforeach
                            </select>
                            @error('paid_by')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="spent_on" class="field-label">Date</label>
                            <input id="spent_on" name="spent_on" type="date" required max="{{ $today }}"
                                   value="{{ old('spent_on', $expense?->spent_on->toDateString() ?? $today) }}" class="input">
                            @error('spent_on')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <fieldset>
                    <legend class="field-label">Split</legend>
                    <input type="hidden" name="split" :value="split" value="{{ $init['split'] }}">
                    <div class="seg" role="group" aria-label="How to split">
                        <button type="button" class="seg-item" :class="split === 'equal' && 'seg-on'" :aria-pressed="split === 'equal'" @click="split = 'equal'">Equally</button>
                        <button type="button" class="seg-item" :class="split === 'exact' && 'seg-on'" :aria-pressed="split === 'exact'" @click="split = 'exact'">Exact amounts</button>
                    </div>
                    @error('split')<p class="field-error">{{ $message }}</p>@enderror

                    {{-- Equally, between the people ticked --}}
                    <div x-show="split === 'equal'" x-cloak class="mt-4">
                        <p class="mb-2 text-sm muted">Between</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($people as $person)
                                <label>
                                    <input type="checkbox" name="participants[]" value="{{ $person['id'] }}" class="peer sr-only"
                                           x-model="participants" :disabled="split !== 'equal'">
                                    <span class="chip">{{ $person['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-3 text-sm font-semibold strong" x-text="equalText()" aria-live="polite"></p>
                        @error('participants')<p class="field-error">{{ $message }}</p>@enderror
                        @error('participants.*')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    {{-- Exact amounts, which must add up to the total --}}
                    <div x-show="split === 'exact'" x-cloak class="mt-4 space-y-2">
                        @foreach($people as $person)
                            <div class="flex items-center gap-3">
                                <label for="share-{{ $person['id'] }}" class="min-w-0 flex-1 truncate text-sm strong">{{ $person['name'] }}</label>
                                <div class="relative w-36 sm:w-40">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm muted">{{ $money->symbol }}</span>
                                    <input id="share-{{ $person['id'] }}" name="shares[{{ $person['id'] }}]" type="text" inputmode="decimal" autocomplete="off" placeholder="0"
                                           x-model="shares[@js((string) $person['id'])]" :disabled="split !== 'exact'" class="input text-right {{ $pad }}">
                                </div>
                            </div>
                        @endforeach
                        <p class="pt-1 text-sm font-semibold" :class="left() === 0 && total() > 0 ? 'money-in' : 'money-out'" x-text="leftText()" aria-live="polite"></p>
                        @error('shares')<p class="field-error">{{ $message }}</p>@enderror
                        @error('shares.*')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </fieldset>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ route('groups.show', $group) }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary" :disabled="blocked()">{{ $expense ? 'Save' : 'Add expense' }}</button>
            </div>
        </form>

        @if($expense)
            <form method="POST" action="{{ route('groups.expenses.destroy', [$group, $expense]) }}" class="text-center"
                  data-confirm-title="Delete expense?" data-confirm-message="It is removed for everyone in {{ $group->name }}." data-confirm-button="Delete">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger-ghost"><x-icon name="trash" class="h-4 w-4" /> Delete expense</button>
            </form>
        @endif
    </div>

    <script>
        /* The split, checked as you type: "₹400.00 each", or "₹120.00 left to assign". Amounts are in the
         * currency shown (window.APP_CURRENCY), in hundredths, like the server's DisplayCurrency. */
        function groupExpenseForm(init) {
            const currency = () => window.APP_CURRENCY ?? { code: 'INR', symbol: '₹', decimals: 2 };
            const toMinor = (text) => {
                const clean = String(text ?? '').replace(/[\s,]/g, '');
                if (clean === '') return 0;
                if (!/^\d*\.?\d*$/.test(clean) || clean === '.') return NaN;
                return Math.round(parseFloat(clean) * 100);
            };
            const format = (minor) => {
                const { code, symbol, decimals } = currency();
                const text = (Math.abs(minor) / 100).toLocaleString(code === 'INR' ? 'en-IN' : 'en-US',
                    { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
                return (minor < 0 ? '-' : '') + symbol + text;
            };

            return {
                amount: init.amount,
                split: init.split,
                participants: init.participants,
                shares: init.shares,
                total() { return toMinor(this.amount); },
                left() { return this.total() - Object.values(this.shares).reduce((sum, text) => sum + toMinor(text), 0); },
                equalText() {
                    const n = this.participants.length;
                    const total = this.total();
                    if (!n) return 'Tick at least one person.';
                    if (!(total > 0)) return `Split between ${n} ${n === 1 ? 'person' : 'people'}.`;
                    const each = Math.floor(total / n);
                    return total - each * n ? `${format(each)} or ${format(each + 1)} each` : `${format(each)} each`;
                },
                leftText() {
                    const left = this.left();
                    if (Number.isNaN(left)) return 'Check the amounts — numbers like 250 or 250.50.';
                    if (!(this.total() > 0)) return 'Enter the total amount first.';
                    if (left === 0) return 'All assigned ✓';
                    return left > 0 ? `${format(left)} left to assign` : `${format(-left)} more than the total`;
                },
                blocked() {
                    const left = this.left();
                    return this.split === 'exact' && this.total() > 0 && (Number.isNaN(left) || left !== 0);
                },
            };
        }
    </script>
</x-app-layout>
