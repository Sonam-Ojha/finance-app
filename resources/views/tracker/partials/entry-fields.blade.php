{{--
    The fields an expense and an income share: amount, date, how it was paid
    and a note. Expects $money, $amountValue, $dateField, $dateValue,
    $paymentLabel ("Paid with" / "Received via"), $paymentValue, $noteValue.
--}}
<div>
    <label for="amount" class="field-label">{{ $amountLabel ?? 'Amount' }}</label>
    <div class="relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-semibold muted">{{ $money->symbol }}</span>
        <input id="amount" name="amount" type="text" inputmode="decimal" autocomplete="off" required
               value="{{ old('amount', $amountValue) }}" placeholder="0" @if(! $amountValue) autofocus @endif
               class="input !text-2xl !font-bold !py-3 {{ mb_strlen($money->symbol) > 1 ? '!pl-16' : '!pl-10' }}">
    </div>
    @error('amount')
        <p class="field-error">{{ $message }}</p>
    @else
        <p class="mt-1 text-xs muted">Amount in {{ $money->label }}</p>
    @enderror
</div>

<div class="grid sm:grid-cols-2 gap-5">
    <div>
        <label for="{{ $dateField }}" class="field-label">Date</label>
        <input id="{{ $dateField }}" name="{{ $dateField }}" type="date" required max="{{ \App\Support\Labels::now()->toDateString() }}"
               value="{{ old($dateField, $dateValue) }}" class="input">
        @error($dateField)<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div x-data="{ note: @js(old('note', $noteValue ?? '')) }">
        <label for="note" class="field-label flex justify-between">Note <span class="normal-case tracking-normal font-normal" x-text="note.length + '/120'"></span></label>
        <input id="note" name="note" type="text" maxlength="120" x-model="note" placeholder="What was this for?" class="input">
        @error('note')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

{{-- Optional; picking the chosen one again clears it, as in the app. --}}
<div x-data="{ payment: @js(old('payment_method', $paymentValue ?? '')) }">
    <span class="field-label">{{ $paymentLabel }}</span>
    <input type="hidden" name="payment_method" :value="payment">
    <div class="flex flex-wrap gap-2">
        @foreach(['upi', 'cash', 'debit_card', 'credit_card', 'bank_transfer', 'other'] as $method)
            <button type="button" class="chip" :class="payment === @js($method) && 'chip-on'" :aria-pressed="payment === @js($method)"
                    @click="payment = payment === @js($method) ? '' : @js($method)">{{ \App\Support\PaymentMethods::label($method) }}</button>
        @endforeach
    </div>
    @error('payment_method')<p class="field-error">{{ $message }}</p>@enderror
</div>
