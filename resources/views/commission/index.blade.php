<x-app-layout>
    <x-slot name="title">Commission</x-slot>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-slate-800">Commission Records</h2>
            <a href="{{ route('commission.create') }}" class="bg-teal-600 text-white px-3 py-2 rounded-lg text-xs sm:text-sm font-medium hover:bg-teal-700 whitespace-nowrap">+ Add Commission</a>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-teal-500 text-white rounded-xl p-4">
                <p class="text-xs opacity-80">Total Received</p>
                <p class="text-2xl font-bold mt-1">₹{{ number_format($totalReceived, 0) }}</p>
            </div>
            <div class="bg-yellow-500 text-white rounded-xl p-4">
                <p class="text-xs opacity-80">Pending Commission</p>
                <p class="text-2xl font-bold mt-1">₹{{ number_format($totalPending, 0) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[520px]">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Date</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Source</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Client / Product</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Status</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Amount</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($commissions as $c)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ $c->date->format('d M Y') }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $c->source_name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $c->client_name }} {{ $c->product_name ? "/ {$c->product_name}" : '' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded {{ $c->status === 'received' ? 'bg-emerald-100 text-emerald-700' : 'bg-yellow-100 text-yellow-700' }}">{{ ucfirst($c->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-teal-700">₹{{ number_format($c->commission_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('commission.edit', $c) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                                    <form method="POST" action="{{ route('commission.destroy', $c) }}" onsubmit="return confirm('Delete?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-500 hover:underline text-xs">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-8 text-gray-400">No commission records</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
        {{ $commissions->links() }}
    </div>
</x-app-layout>
