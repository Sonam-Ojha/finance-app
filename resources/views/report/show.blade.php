<x-app-layout>
    <x-slot name="title">{{ ucfirst(str_replace('_',' ',$type)) }} Report</x-slot>
    <div class="pt-2 space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('report.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
                <h2 class="text-xl font-bold text-gray-800">{{ ucfirst(str_replace('_',' ',$type)) }} Report — {{ $month ? date('F', mktime(0,0,0,$month,1)).' ' : '' }}{{ $year }}</h2>
            </div>
            <form method="POST" action="{{ route('report.generate') }}">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="format" value="pdf">
                <button class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700">⬇ Download PDF</button>
            </form>
        </div>

        @if($type === 'profit_loss')
            <div class="grid md:grid-cols-3 gap-4">
                <div class="bg-emerald-500 text-white rounded-xl p-4">
                    <p class="text-xs opacity-80">Total Income</p>
                    <p class="text-2xl font-bold mt-1">₹{{ number_format($totalIncome, 0) }}</p>
                </div>
                <div class="bg-red-500 text-white rounded-xl p-4">
                    <p class="text-xs opacity-80">Total Expense</p>
                    <p class="text-2xl font-bold mt-1">₹{{ number_format($totalExpense, 0) }}</p>
                </div>
                <div class="{{ $net >= 0 ? 'bg-blue-500' : 'bg-orange-500' }} text-white rounded-xl p-4">
                    <p class="text-xs opacity-80">Net {{ $net >= 0 ? 'Profit' : 'Loss' }}</p>
                    <p class="text-2xl font-bold mt-1">₹{{ number_format(abs($net), 0) }}</p>
                </div>
            </div>
        @elseif($type === 'investment')
            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-indigo-500 text-white rounded-xl p-4">
                    <p class="text-xs opacity-80">Total Invested</p>
                    <p class="text-2xl font-bold mt-1">₹{{ number_format($total, 0) }}</p>
                </div>
                <div class="bg-emerald-500 text-white rounded-xl p-4">
                    <p class="text-xs opacity-80">Current Value</p>
                    <p class="text-2xl font-bold mt-1">₹{{ number_format($currentTotal, 0) }}</p>
                </div>
            </div>
        @elseif($type === 'loan')
            <div class="bg-orange-500 text-white rounded-xl p-4 inline-block">
                <p class="text-xs opacity-80">Total Pending</p>
                <p class="text-2xl font-bold mt-1">₹{{ number_format($totalPending, 0) }}</p>
            </div>
        @else
            <div class="bg-white rounded-xl p-4 shadow border-l-4 border-blue-400 inline-block">
                <p class="text-xs text-gray-500">Total</p>
                <p class="text-2xl font-bold text-blue-700 mt-1">₹{{ number_format($total ?? 0, 0) }}</p>
            </div>
        @endif

        @if(isset($records) && $records->count())
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        @if($type === 'income')
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Date</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Type</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Details</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-600">Amount</th>
                        @elseif($type === 'expense')
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Date</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Category</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Description</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-600">Amount</th>
                        @elseif($type === 'investment')
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Name</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Category</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-600">Invested</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-600">Current</th>
                        @elseif($type === 'loan')
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Loan Name</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Lender</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-600">Total</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-600">Pending</th>
                        @elseif($type === 'commission')
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Date</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Source</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Status</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-600">Amount</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($records as $r)
                        <tr>
                            @if($type === 'income')
                                <td class="px-4 py-3 text-gray-600">{{ $r->date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ \App\Models\Income::typeLabel($r->type) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $r->company_name ?? $r->person_name ?? $r->business_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-600">₹{{ number_format($r->amount, 2) }}</td>
                            @elseif($type === 'expense')
                                <td class="px-4 py-3 text-gray-600">{{ $r->date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $r->category->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ Str::limit($r->description, 30) ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-red-600">₹{{ number_format($r->amount, 2) }}</td>
                            @elseif($type === 'investment')
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $r->investment_name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ strtoupper($r->category) }}</td>
                                <td class="px-4 py-3 text-right">₹{{ number_format($r->amount_invested, 0) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-600">₹{{ number_format($r->current_value ?? 0, 0) }}</td>
                            @elseif($type === 'loan')
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $r->loan_name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $r->bank_or_person_name }}</td>
                                <td class="px-4 py-3 text-right">₹{{ number_format($r->total_amount, 0) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-red-600">₹{{ number_format($r->pending_amount, 0) }}</td>
                            @elseif($type === 'commission')
                                <td class="px-4 py-3 text-gray-600">{{ $r->date->format('d M Y') }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $r->source_name }}</td>
                                <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded {{ $r->status === 'received' ? 'bg-emerald-100 text-emerald-700' : 'bg-yellow-100 text-yellow-700' }}">{{ ucfirst($r->status) }}</span></td>
                                <td class="px-4 py-3 text-right font-semibold text-teal-700">₹{{ number_format($r->commission_amount, 2) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</x-app-layout>
