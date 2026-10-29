<x-app-layout>
    <x-slot name="title">{{ $currency ? $currency->label : 'Add currency' }}</x-slot>
    @php
        $custom = ! $currency || $currency->custom;
        // No rate of their own yet: start from the one Admin › Currencies suggests, if any.
        $suggested = $currency && ! $currency->custom && ! $currency->rate ? \App\Support\Currencies::defaultRate($currency->code) : null;
        $rateText = fn (?float $rate) => $rate ? rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.') : '';
        $rateValue = old('rate', $rateText($currency?->rate ?: $suggested));
        $selected = $currency && auth()->user()->currency === $currency->code;
    @endphp

    <div class="space-y-4"
         x-data="{
             code: @js(old('code', $currency?->code ?? '')),
             symbol: @js(old('symbol', $currency?->symbol ?? '')),
             rate: @js((string) $rateValue),
             decimals: @js($currency?->decimals ?? 2),
             preview() {
                 const r = Number(this.rate);
                 if (! (this.rate.trim() !== '' && r > 0)) return null;
                 const units = Math.round(100000 / r * 100) / 100;
                 return (this.symbol || this.code || '') + units.toLocaleString('en-US', { minimumFractionDigits: this.decimals, maximumFractionDigits: this.decimals });
             },
         }">
        <form method="POST" action="{{ $currency ? route('currency.save', $currency->code) : route('currency.store') }}" class="card space-y-5">
            @csrf @method('PUT')
            @if($select)<input type="hidden" name="select" value="1">@endif

            {{-- Two to a row on wide screens --}}
            <div class="grid items-start gap-5 lg:grid-cols-2">
            @if($custom)
                <div>
                    <label for="label" class="field-label">Name</label>
                    <input id="label" name="label" type="text" maxlength="30" value="{{ old('label', $currency?->label) }}" placeholder="e.g. Thai Baht" class="input" required>
                    @error('label')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                @unless($currency)
                    <div>
                        <label for="code" class="field-label">Code</label>
                        <input id="code" name="code" type="text" maxlength="5" x-model="code" @input="code = code.replace(/[^a-z]/gi, '').toUpperCase()"
                               placeholder="e.g. THB" class="input uppercase" required>
                        @error('code')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endunless
                <div>
                    <label for="symbol" class="field-label">Symbol</label>
                    <input id="symbol" name="symbol" type="text" maxlength="4" x-model="symbol" placeholder="Pick one below, or type it" class="input" required>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @foreach($symbols as $s)
                            <button type="button" class="chip !px-2.5 !py-1 text-sm" :class="symbol === @js($s) && 'chip-on'" @click="symbol = @js($s)">{{ $s }}</button>
                        @endforeach
                    </div>
                    @error('symbol')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endif

            <div>
                <label for="rate" class="field-label">Rate — 1 <span x-text="code || 'unit'"></span> in rupees</label>
                <input id="rate" name="rate" type="text" inputmode="decimal" x-model="rate"
                       @input="rate = rate.replace(',', '.').replace(/[^\d.]/g, '').replace(/(\..*)\./g, '$1')"
                       placeholder="e.g. 83.25" class="input" required>
                @error('rate')<p class="field-error">{{ $message }}</p>@enderror
                @if($suggested)
                    <p class="mt-2 text-xs muted">We filled in a recent rate. Check it against today's before you save.</p>
                @endif
                <p class="mt-2 text-xs muted">
                    <span x-show="preview()">₹1,000.00 will show as <span class="font-semibold strong" x-text="preview()"></span>.</span>
                    <span x-show="! preview()">Amounts stay in rupees and are converted with this when <span x-text="code || 'it'"></span> is picked.</span>
                    It does not update by itself — change it when the rate moves.
                </p>
            </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ route('currency.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>

        @if($currency?->custom)
            <form method="POST" action="{{ route('currency.destroy', $currency->code) }}" class="text-center"
                  data-confirm-title="Remove {{ $currency->label }}?"
                  data-confirm-message="{{ $selected ? 'Amounts are kept in rupees, so nothing you entered changes — they will show in rupees again.' : 'Amounts are kept in rupees, so nothing you entered changes.' }}"
                  data-confirm-button="Remove">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger-ghost"><x-icon name="trash" class="w-4 h-4" /> Remove currency</button>
            </form>
        @endif
    </div>
</x-app-layout>
