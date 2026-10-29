<x-admin-layout>
    <x-slot name="title">Currencies</x-slot>
    @php
        $on = $currencies->where('enabled', true)->count();
    @endphp
    <x-slot name="subtitle">
        {{ $on }} of {{ $currencies->count() }} on offer in the app and on the website.
    </x-slot>

    <div class="adm-strip">
        <x-icon name="info" class="mt-0.5 h-5 w-5 flex-shrink-0 text-brand-navy" />
        <p><span class="font-semibold text-brand-navy">Amounts are always stored in rupees.</span>
            A currency only changes how amounts are shown, at each person's own rate. Changes reach the app's currency list and the website straight away.</p>
    </div>

    <div class="flex flex-wrap items-start gap-4">
        <section class="adm-card min-w-0 flex-[2_1_560px]">
            <h2 class="adm-h2">On offer</h2>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full min-w-[620px] text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="adm-th">Currency</th>
                            <th scope="col" class="adm-th">Decimals</th>
                            <th scope="col" class="adm-th">Suggested rate</th>
                            <th scope="col" class="adm-th">People using it</th>
                            <th scope="col" class="adm-th">On offer</th>
                            <th scope="col" class="adm-th"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($currencies as $currency)
                            @php $people = (int) ($readers[$currency->code] ?? 0); @endphp
                            <tr class="{{ $editing?->is($currency) ? 'bg-brand-tint/60' : '' }}">
                                <td class="adm-td">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-surface-page text-sm font-semibold text-brand-navy">{{ $currency->symbol }}</span>
                                        <div class="min-w-0">
                                            <p class="font-semibold">{{ $currency->name }}</p>
                                            <p class="adm-caption font-mono">{{ $currency->code }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="adm-td">{{ $currency->decimals }}</td>
                                <td class="adm-td whitespace-nowrap">
                                    @if($currency->isBase())
                                        <span class="adm-caption">Base currency</span>
                                    @elseif($currency->rateText())
                                        1 {{ $currency->code }} = ₹{{ $currency->rateText() }}
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                                <td class="adm-td">{{ \App\Support\Counts::indian($people) }}</td>
                                <td class="adm-td">
                                    @if($currency->isBase())
                                        <span class="adm-badge bg-brand-navy text-white">Always</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.currencies.toggle', $currency) }}"
                                              @if($currency->enabled)
                                                  data-confirm-title="Switch off {{ $currency->code }}?"
                                                  data-confirm-message="Nobody new can pick it. {{ $people ? \App\Support\Counts::indian($people) . ' ' . \Illuminate\Support\Str::plural('person', $people) . ' reading amounts in it keep it if they set a rate; the rest see rupees.' : 'Nobody reads amounts in it now.' }}"
                                                  data-confirm-button="Switch off"
                                              @endif>
                                            @csrf @method('PATCH')
                                            <button type="submit" role="switch" aria-checked="{{ $currency->enabled ? 'true' : 'false' }}" aria-label="Offer {{ $currency->name }}" class="adm-switch"><span></span></button>
                                        </form>
                                    @endif
                                </td>
                                <td class="adm-td whitespace-nowrap text-right">
                                    @unless($currency->isBase())
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('admin.currencies.edit', $currency) }}" class="adm-btn-outline !h-10 !px-4 !text-sm">Edit<span class="sr-only"> {{ $currency->code }}</span></a>
                                            <form method="POST" action="{{ route('admin.currencies.destroy', $currency) }}"
                                                  data-confirm-title="Delete {{ $currency->code }}?"
                                                  data-confirm-message="It leaves the app's and the website's list. {{ $people ? \App\Support\Counts::indian($people) . ' ' . \Illuminate\Support\Str::plural('person', $people) . ' reading amounts in it keep it as their own currency if they set a rate; the rest see rupees.' : 'Nobody reads amounts in it now.' }} Switching it off instead keeps its details."
                                                  data-confirm-button="Delete">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="min-h-10 px-2 text-sm font-semibold text-status-error hover:underline">Delete<span class="sr-only"> {{ $currency->code }}</span></button>
                                            </form>
                                        </div>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="adm-caption mt-3">People pick a currency in Settings › Currency and set their own rate. The suggested rate fills in that rate the first time; it never changes a rate someone already set.</p>
        </section>

        {{-- Add or edit --}}
        <section class="adm-card flex-[1_1_320px]"
                 x-data="{
                     code: @js(old('code', $editing?->code ?? '')),
                     symbol: @js(old('symbol', $editing?->symbol ?? '')),
                     name: @js(old('name', $editing?->name ?? '')),
                     decimals: @js((string) old('decimals', $editing?->decimals ?? 2)),
                     rate: @js((string) old('default_rate', $editing?->rateText() ?? '')),
                     sample() {
                         const d = Number(this.decimals) || 0;
                         return (this.symbol || this.code || '') + (1234.5).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
                     },
                 }">
            <div class="flex items-center justify-between gap-2">
                <h2 class="adm-h2">{{ $editing ? "Edit {$editing->code}" : 'Add a currency' }}</h2>
                @if($editing)
                    <a href="{{ route('admin.currencies.index') }}" class="adm-link">Cancel</a>
                @endif
            </div>

            <form method="POST" action="{{ $editing ? route('admin.currencies.update', $editing) : route('admin.currencies.store') }}" class="mt-4 flex flex-col gap-4">
                @csrf
                @if($editing) @method('PUT') @endif

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="code" class="adm-label">Code</label>
                        @if($editing)
                            <input id="code" type="text" value="{{ $editing->code }}" disabled class="adm-input w-full bg-surface-page font-mono">
                        @else
                            <input id="code" name="code" type="text" maxlength="5" x-model="code" @input="code = code.replace(/[^a-z]/gi, '').toUpperCase()"
                                   required placeholder="THB" class="adm-input w-full font-mono uppercase">
                        @endif
                        @error('code')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="symbol" class="adm-label">Symbol</label>
                        <input id="symbol" name="symbol" type="text" maxlength="8" x-model="symbol" required placeholder="฿" class="adm-input w-full">
                        @error('symbol')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="name" class="adm-label">Name</label>
                    <input id="name" name="name" type="text" maxlength="40" x-model="name" required placeholder="Thai Baht" class="adm-input w-full">
                    @error('name')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="decimals" class="adm-label">Decimals</label>
                        <select id="decimals" name="decimals" x-model="decimals" class="adm-input w-full">
                            @foreach([0, 1, 2, 3] as $d)
                                <option value="{{ $d }}">{{ $d }}</option>
                            @endforeach
                        </select>
                        @error('decimals')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="default_rate" class="adm-label">Rate in ₹ <span class="font-normal text-ink-muted">(optional)</span></label>
                        <input id="default_rate" name="default_rate" type="text" inputmode="decimal" x-model="rate" placeholder="e.g. 2.45" class="adm-input w-full">
                    </div>
                </div>
                @error('default_rate')<p class="-mt-2 text-[13px] text-status-error">{{ $message }}</p>@enderror

                <p class="adm-caption -mt-1">
                    Shows like <span class="font-semibold text-ink" x-text="sample()"></span><span x-show="Number(rate) > 0">, with 1 <span x-text="code || 'unit'"></span> = ₹<span x-text="rate"></span> suggested</span>.
                </p>

                @unless($editing)
                    <label class="flex items-center gap-3 text-sm">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', '1') === '1') class="h-5 w-5 rounded border-surface-field text-brand-navy focus:ring-brand-navy">
                        Offer it straight away
                    </label>
                @endunless

                <button type="submit" class="adm-btn-primary w-full">
                    <x-icon :name="$editing ? 'check' : 'plus'" class="h-5 w-5" /> {{ $editing ? 'Save changes' : 'Add currency' }}
                </button>
            </form>
        </section>
    </div>

    {{-- For reference: defined in code, the same in the app --}}
    <section class="adm-card">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="adm-h2">Payment methods</h2>
            <p class="adm-caption">Read only · set in code, so the app and the website always agree</p>
        </div>
        <ul class="mt-4 flex flex-wrap gap-2">
            @foreach($paymentMethods as $key => $label)
                <li class="adm-chip gap-2">{{ $label }} <span class="font-mono text-[12px] text-ink-muted">{{ $key }}</span></li>
            @endforeach
        </ul>
        <p class="adm-caption mt-3">Every expense and income can name one of these, or none. Changing the list needs an app update too.</p>
    </section>
</x-admin-layout>
