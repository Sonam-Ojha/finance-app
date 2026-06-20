<x-app-layout>
    <x-slot name="title">{{ $loan->loan_name }}</x-slot>
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('loan.index') }}" class="text-slate-500 hover:text-slate-700 text-sm">← Back</a>
            <h2 class="text-lg font-bold text-slate-800">{{ $loan->loan_name }}</h2>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-xl p-4 text-white shadow col-span-2 sm:col-span-1" style="background:linear-gradient(135deg,#f97316,#ea580c)">
                <p class="text-xs opacity-80">Pending Amount</p>
                <p class="text-2xl font-bold mt-1">₹{{ number_format($loan->pending_amount, 0) }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm border-t-4 border-slate-400">
                <p class="text-xs text-slate-500">Total Loan</p>
                <p class="text-lg font-bold text-slate-800 mt-1">₹{{ number_format($loan->total_amount, 0) }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm border-t-4 border-blue-400">
                <p class="text-xs text-slate-500">EMI / Month</p>
                <p class="text-lg font-bold text-blue-700 mt-1">₹{{ number_format($loan->emi_amount, 0) }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm border-t-4 border-emerald-400">
                <p class="text-xs text-slate-500">Paid So Far</p>
                <p class="text-lg font-bold text-emerald-700 mt-1">₹{{ number_format($loan->total_amount - $loan->pending_amount, 0) }}</p>
            </div>
        </div>

        {{-- Progress bar --}}
        @php $pct = $loan->total_amount > 0 ? round((($loan->total_amount - $loan->pending_amount) / $loan->total_amount) * 100) : 0; @endphp
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex justify-between text-xs text-slate-500 mb-1">
                <span>Repayment Progress</span><span>{{ $pct }}%</span>
            </div>
            <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-emerald-500 rounded-full transition-all" style="width:{{ $pct }}%"></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-slate-700 mb-3 text-sm">Record EMI Payment</h3>
            <form method="POST" action="{{ route('loan.payment.store', $loan) }}" class="grid grid-cols-2 sm:flex sm:flex-wrap gap-3 items-end">
                @csrf
                <div>
                    <label class="text-xs text-slate-500 block mb-1">Date</label>
                    <input type="date" name="date" value="{{ date('Y-m-d') }}" class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div>
                    <label class="text-xs text-slate-500 block mb-1">Amount (₹)</label>
                    <input type="number" name="amount" step="0.01" value="{{ $loan->emi_amount }}" class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full sm:w-36">
                </div>
                <div class="col-span-2 sm:flex-1">
                    <label class="text-xs text-slate-500 block mb-1">Note</label>
                    <input type="text" name="note" placeholder="Optional" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <button class="w-full sm:w-auto bg-orange-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-700">Record Payment</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[380px]">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Date</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Note</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600">Amount Paid</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-600">{{ $payment->date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $payment->note ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-emerald-600">₹{{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center py-8 text-slate-400">No payments recorded yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
    </div>
</x-app-layout>
