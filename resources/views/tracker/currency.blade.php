<x-app-layout>
    <x-slot name="title">Currency</x-slot>

    <x-slot name="subtitle">Amounts are kept in rupees. Pick a currency to see them converted at your rate — the pencil sets it.</x-slot>
    <x-slot name="actions">
        <a href="{{ route('currency.create') }}" class="btn-primary"><x-icon name="plus" class="w-5 h-5" /> Add currency</a>
    </x-slot>

    <div class="max-w-2xl space-y-4">

        <div class="card !p-0 divide-soft overflow-hidden">
            @foreach($currencies as $currency)
                @php
                    $rate = $currency->rate ? rtrim(rtrim(number_format($currency->rate, 6, '.', ''), '0'), '.') : null;
                @endphp
                <div class="flex items-center gap-2 pr-2">
                    <form method="POST" action="{{ route('currency.select') }}" class="flex-1 min-w-0">
                        @csrf @method('PUT')
                        <input type="hidden" name="currency" value="{{ $currency->code }}">
                        <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-surface-page dark:hover:bg-white/5">
                            <span class="w-10 h-10 rounded-full bg-surface-page dark:bg-night-line flex items-center justify-center font-semibold strong text-sm flex-shrink-0">{{ $currency->symbol }}</span>
                            <span class="flex-1 min-w-0">
                                <span class="block font-medium strong truncate">{{ $currency->label }}</span>
                                @if($currency->code === 'INR')
                                    <span class="block text-xs muted">INR · amounts are kept in this</span>
                                @elseif($rate)
                                    <span class="block text-xs muted">{{ $currency->code }} · 1 {{ $currency->code }} = ₹{{ $rate }}</span>
                                @else
                                    <span class="block text-xs text-brand-navy dark:text-brand-cyan">{{ $currency->code }} · set a rate to use it</span>
                                @endif
                            </span>
                            @if($currency->code === $selected)
                                <x-icon name="check" class="w-5 h-5 text-brand-navy dark:text-brand-cyan" />
                            @endif
                        </button>
                    </form>
                    @if($currency->code !== 'INR')
                        <a href="{{ route('currency.edit', $currency->code) }}" class="p-2 rounded-lg muted hover:bg-surface-page dark:hover:bg-white/5" aria-label="Set the rate for {{ $currency->code }}">
                            <x-icon name="pencil" class="w-4 h-4" />
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
