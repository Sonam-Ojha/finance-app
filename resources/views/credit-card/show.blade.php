<x-app-layout>
    <x-slot name="title">{{ $creditCard->card_name }}</x-slot>
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('credit-card.index') }}" class="text-slate-500 hover:text-slate-700 text-sm">← Back</a>
            <h2 class="text-lg font-bold text-slate-800">{{ $creditCard->card_name }}</h2>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div class="rounded-xl p-4 text-white shadow col-span-2 sm:col-span-1" style="background:linear-gradient(135deg,#7c3aed,#6d28d9)">
                <p class="text-xs opacity-80">Outstanding</p>
                <p class="text-2xl font-bold mt-1">₹{{ number_format($creditCard->outstanding_amount, 2) }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm border-t-4 border-emerald-400">
                <p class="text-xs text-slate-500">Available Limit</p>
                <p class="text-lg font-bold text-emerald-600 mt-1">₹{{ number_format($creditCard->credit_limit - $creditCard->outstanding_amount, 0) }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm border-t-4 border-blue-400">
                <p class="text-xs text-slate-500">Credit Limit</p>
                <p class="text-lg font-bold text-blue-600 mt-1">₹{{ number_format($creditCard->credit_limit, 0) }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-slate-700 mb-3 text-sm">Add Transaction</h3>
            <form method="POST" action="{{ route('credit-card.transaction.store', $creditCard) }}" class="grid grid-cols-2 sm:flex sm:flex-wrap gap-3 items-end">
                @csrf
                <div>
                    <label class="text-xs text-slate-500 block mb-1">Type</label>
                    <select name="type" class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full">
                        <option value="spend">Spend</option>
                        <option value="payment">Payment</option>
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
                    <label class="text-xs text-slate-500 block mb-1">Description</label>
                    <input type="text" name="description" placeholder="Optional" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <button class="w-full sm:w-auto bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700">Add</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[400px]">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Date</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Type</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600">Description</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transactions as $txn)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-600">{{ $txn->date->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $txn->type === 'payment' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ ucfirst($txn->type) }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $txn->description ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $txn->type === 'payment' ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $txn->type === 'payment' ? '-' : '+' }}₹{{ number_format($txn->amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-8 text-slate-400">No transactions yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
        {{ $transactions->links() }}
    </div>
</x-app-layout>
