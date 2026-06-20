<x-app-layout>
    <x-slot name="title">Income</x-slot>

    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-slate-800">Income Records</h2>
            <a href="{{ route('income.create') }}" class="bg-emerald-600 text-white px-3 py-2 rounded-lg text-xs sm:text-sm font-medium hover:bg-emerald-700 transition whitespace-nowrap">+ Add Income</a>
        </div>

        {{-- Filters --}}
        <form method="GET" class="bg-white rounded-xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500 block mb-1">Type</label>
                <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Types</option>
                    <option value="salary" @selected(request('type')=='salary')>Salary</option>
                    <option value="lic_commission" @selected(request('type')=='lic_commission')>LIC Commission</option>
                    <option value="business" @selected(request('type')=='business')>Business</option>
                    <option value="received_from" @selected(request('type')=='received_from')>Received From</option>
                    <option value="other" @selected(request('type')=='other')>Other</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Month</label>
                <select name="month" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Months</option>
                    @foreach(range(1,12) as $m)
                        <option value="{{ $m }}" @selected(request('month')==$m)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Year</label>
                <select name="year" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach(range(date('Y'), date('Y')-5) as $y)
                        <option value="{{ $y }}" @selected(request('year')==$y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <button class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm">Filter</button>
            <a href="{{ route('income.index') }}" class="text-sm text-gray-500 hover:underline self-center">Reset</a>
        </form>

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[600px]">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Date</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Type</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Details</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Payment Mode</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Amount</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($incomes as $income)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ $income->date->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-medium">
                                    {{ \App\Models\Income::typeLabel($income->type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $income->company_name ?? $income->client_name ?? $income->business_name ?? $income->person_name ?? $income->category_name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $income->payment_mode ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-emerald-600">₹{{ number_format($income->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('income.edit', $income) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                                    <form method="POST" action="{{ route('income.destroy', $income) }}" onsubmit="return confirm('Delete this record?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-500 hover:underline text-xs">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-8 text-gray-400">No income records found</td></tr>
                    @endforelse
                </tbody>
                @if($incomes->count())
                <tfoot class="bg-gray-50 border-t">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right font-semibold text-gray-600">Total:</td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-700">₹{{ number_format($incomes->sum('amount'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div></div>

        {{ $incomes->links() }}
    </div>
</x-app-layout>
