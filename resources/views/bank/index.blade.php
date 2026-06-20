<x-app-layout>
    <x-slot name="title">Bank Accounts</x-slot>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-slate-800">Bank Accounts</h2>
            <a href="{{ route('bank.create') }}" class="bg-blue-600 text-white px-3 py-2 rounded-lg text-xs sm:text-sm font-medium hover:bg-blue-700 whitespace-nowrap">+ Add Account</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($accounts as $account)
                <div class="bg-white rounded-xl shadow p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="font-bold text-gray-800">{{ $account->bank_name }}</p>
                            @if($account->account_number_last4)
                                <p class="text-xs text-gray-400 mt-0.5">xxxx xxxx xxxx {{ $account->account_number_last4 }}</p>
                            @endif
                            <span class="text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded mt-1 inline-block">{{ ucfirst($account->account_type) }}</span>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('bank.edit', $account) }}" class="text-xs text-blue-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('bank.destroy', $account) }}" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-500 hover:underline">Delete</button>
                            </form>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-emerald-600 mt-2">₹{{ number_format($account->current_balance, 2) }}</p>
                    <p class="text-xs text-gray-400 mb-3">Current Balance</p>
                    <a href="{{ route('bank.show', $account) }}" class="block text-center bg-blue-50 text-blue-700 py-2 rounded-lg text-sm hover:bg-blue-100 transition">View Transactions</a>
                </div>
            @empty
                <div class="col-span-3 text-center py-12 text-gray-400">
                    <p class="text-4xl mb-2">🏦</p>
                    <p>No bank accounts added yet</p>
                    <a href="{{ route('bank.create') }}" class="text-blue-600 hover:underline text-sm mt-1 inline-block">Add your first account</a>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
