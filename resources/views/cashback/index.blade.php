<x-app-layout>
    <x-slot name="title">Cashback</x-slot>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-slate-800">Cashback Records</h2>
            <a href="{{ route('cashback.create') }}" class="bg-pink-600 text-white px-3 py-2 rounded-lg text-xs sm:text-sm font-medium hover:bg-pink-700 whitespace-nowrap">+ Add Cashback</a>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-pink-500 text-white rounded-xl p-4">
                <p class="text-xs opacity-80">Total Received</p>
                <p class="text-2xl font-bold mt-1">₹{{ number_format($totalReceived, 0) }}</p>
            </div>
            <div class="bg-yellow-500 text-white rounded-xl p-4">
                <p class="text-xs opacity-80">Pending</p>
                <p class="text-2xl font-bold mt-1">₹{{ number_format($totalPending, 0) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[480px]">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Date</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Platform</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Status</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Notes</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Amount</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($cashbacks as $cb)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ $cb->date->format('d M Y') }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $cb->platform_name }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded {{ $cb->status === 'received' ? 'bg-emerald-100 text-emerald-700' : 'bg-yellow-100 text-yellow-700' }}">{{ ucfirst($cb->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ Str::limit($cb->notes, 30) ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-pink-700">₹{{ number_format($cb->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('cashback.edit', $cb) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                                    <form method="POST" action="{{ route('cashback.destroy', $cb) }}" onsubmit="return confirm('Delete?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-500 hover:underline text-xs">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-8 text-gray-400">No cashback records</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
        {{ $cashbacks->links() }}
    </div>
</x-app-layout>
