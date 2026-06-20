<x-app-layout>
    <x-slot name="title">{{ $contact->name }} Ledger</x-slot>
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('contact.index') }}" class="text-slate-500 hover:text-slate-700 text-sm">← Back</a>
            <h2 class="text-lg font-bold text-slate-800">{{ $contact->name }}</h2>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div class="col-span-2 sm:col-span-1 rounded-xl p-4 text-white shadow {{ $balance >= 0 ? '' : '' }}"
                 style="background:{{ $balance >= 0 ? 'linear-gradient(135deg,#10b981,#059669)' : 'linear-gradient(135deg,#ef4444,#dc2626)' }}">
                <p class="text-xs opacity-80">{{ $balance >= 0 ? 'They owe you' : 'You owe them' }}</p>
                <p class="text-2xl font-bold mt-1">₹{{ number_format(abs($balance), 0) }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm border-t-4 border-emerald-400">
                <p class="text-xs text-slate-500">Total Lent</p>
                <p class="text-lg font-bold text-emerald-700 mt-1">₹{{ number_format($transactions->where('type','lent')->sum('amount'), 0) }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm border-t-4 border-red-400">
                <p class="text-xs text-slate-500">Total Borrowed</p>
                <p class="text-lg font-bold text-red-700 mt-1">₹{{ number_format($transactions->where('type','borrowed')->sum('amount'), 0) }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-slate-700 mb-3 text-sm">Add Transaction</h3>
            <form method="POST" action="{{ route('contact.transaction.store', $contact) }}" class="grid grid-cols-2 sm:flex sm:flex-wrap gap-3 items-end">
                @csrf
                <div>
                    <label class="text-xs text-slate-500 block mb-1">Type</label>
                    <select name="type" class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full">
                        <option value="lent">Lent (Maine diya)</option>
                        <option value="borrowed">Borrowed (Maine liya)</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500 block mb-1">Date</label>
                    <input type="date" name="date" value="{{ date('Y-m-d') }}" class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full">
                </div>
                <div>
                    <label class="text-xs text-slate-500 block mb-1">Amount (₹)</label>
                    <input type="number" name="amount" step="0.01" placeholder="0.00" class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full sm:w-32">
                </div>
                <div class="col-span-2 sm:flex-1">
                    <label class="text-xs text-slate-500 block mb-1">Reason</label>
                    <input type="text" name="reason" placeholder="Optional" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <button class="w-full sm:w-auto bg-slate-800 text-white px-4 py-2 rounded-lg text-sm hover:bg-slate-900">Add</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[380px]">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Date</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Type</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Reason</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transactions as $txn)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-600">{{ $txn->date->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $txn->type === 'lent' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $txn->type === 'lent' ? 'Lent' : 'Borrowed' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $txn->reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $txn->type === 'lent' ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $txn->type === 'lent' ? '+' : '-' }}₹{{ number_format($txn->amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-8 text-slate-400">No transactions yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
    </div>
</x-app-layout>
