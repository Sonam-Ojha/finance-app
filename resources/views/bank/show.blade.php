<x-app-layout>
    <x-slot name="title">{{ $bank->bank_name }}</x-slot>
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('bank.index') }}" class="text-slate-500 hover:text-slate-700 text-sm">← Back</a>
            <h2 class="text-lg font-bold text-slate-800">{{ $bank->bank_name }}</h2>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div class="bg-emerald-500 text-white rounded-xl p-4">
                <p class="text-xs opacity-80">Current Balance</p>
                <p class="text-2xl font-bold mt-1">₹{{ number_format($bank->current_balance, 2) }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow border-l-4 border-blue-400">
                <p class="text-xs text-gray-500">Account Type</p>
                <p class="text-lg font-bold text-gray-800 mt-1">{{ ucfirst($bank->account_type) }}</p>
            </div>
            @if($bank->ifsc_code)
            <div class="bg-white rounded-xl p-4 shadow border-l-4 border-gray-400">
                <p class="text-xs text-gray-500">IFSC Code</p>
                <p class="text-lg font-bold text-gray-800 mt-1">{{ $bank->ifsc_code }}</p>
            </div>
            @endif
        </div>

        {{-- Add Transaction --}}
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-slate-700 mb-3 text-sm">Add Transaction</h3>
            <form method="POST" action="{{ route('bank.transaction.store', $bank) }}" class="grid grid-cols-2 sm:flex sm:flex-wrap gap-3 items-end">
                @csrf
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Type</label>
                    <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="deposit">Deposit</option>
                        <option value="withdrawal">Withdrawal</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Date</label>
                    <input type="date" name="date" value="{{ date('Y-m-d') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Amount (₹)</label>
                    <input type="number" name="amount" step="0.01" placeholder="0.00" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32">
                </div>
                <div class="flex-1">
                    <label class="text-xs text-gray-500 block mb-1">Description</label>
                    <input type="text" name="description" placeholder="Optional" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <button class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Add</button>
            </form>
        </div>

        {{-- Transactions Table --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[400px]">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Date</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Type</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Description</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($transactions as $txn)
                        <tr>
                            <td class="px-4 py-3 text-gray-600">{{ $txn->date->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded {{ $txn->type === 'deposit' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ ucfirst($txn->type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $txn->description ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $txn->type === 'deposit' ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $txn->type === 'deposit' ? '+' : '-' }}₹{{ number_format($txn->amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-8 text-gray-400">No transactions yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
        {{ $transactions->links() }}
    </div>
</x-app-layout>
