<x-app-layout>
    <x-slot name="title">Currency</x-slot>

    <div class="max-w-2xl mx-auto space-y-4">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm muted">Amounts are kept in rupees. Pick a currency to see them converted at your rate — the pencil sets it.</p>
            <a href="{{ route('currency.create') }}" class="btn-primary !rounded-full flex-shrink-0"><x-icon name="plus" class="w-4 h-4" /> Add</a>
        </div>

        <div class="card !p-0 divide-soft overflow-hidden">
            @foreach($currencies as $currency)
                @php
                    $rate = $currency->rate ? rtrim(rtrim(number_format($currency->rate, 6, '.', ''), '0'), '.') : null;
                @endphp
                <div class="flex items-center gap-2 pr-2">
                    <form method="POST" action="{{ route('currency.select') }}" class="flex-1 min-w-0">
                        @csrf @method('PUT')
                        <input type="hidden" name="currency" value="{{ $currency->code }}">
                        <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-slate-50 dark:hover:bg-slate-700/40">
                            <span class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center font-semibold strong text-sm flex-shrink-0">{{ $currency->symbol }}</span>
                            <span class="flex-1 min-w-0">
                                <span class="block font-medium strong truncate">{{ $currency->label }}</span>
                                @if($currency->code === 'INR')
                                    <span class="block text-xs muted">INR · amounts are kept in this</span>
                                @elseif($rate)
                                    <span class="block text-xs muted">{{ $currency->code }} · 1 {{ $currency->code }} = ₹{{ $rate }}</span>
                                @else
                                    <span class="block text-xs text-indigo-600 dark:text-indigo-400">{{ $currency->code }} · set a rate to use it</span>
                                @endif
                            </span>
                            @if($currency->code === $selected)
                                <x-icon name="check" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                            @endif
                        </button>
                    </form>
                    @if($currency->code !== 'INR')
                        <a href="{{ route('currency.edit', $currency->code) }}" class="p-2 rounded-lg muted hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Set the rate for {{ $currency->code }}">
                            <x-icon name="pencil" class="w-4 h-4" />
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
