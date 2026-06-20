<x-app-layout>
    <x-slot name="title">Pending Money</x-slot>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-slate-800">Pending Money</h2>
            <a href="{{ route('bad-debt.create') }}" class="bg-yellow-600 text-white px-3 py-2 rounded-lg text-xs sm:text-sm font-medium hover:bg-yellow-700 whitespace-nowrap">+ Add Record</a>
        </div>
        <div class="bg-amber-500 text-white rounded-xl p-4 inline-block shadow">
            <p class="text-xs opacity-80">Total Pending</p>
            <p class="text-3xl font-bold mt-1">₹{{ number_format($totalPending, 0) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[580px]">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Person</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Given On</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Expected Return</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Status</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Amount</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Pending</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($debts as $debt)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">{{ $debt->person_name }}</p>
                                @if($debt->mobile_number)
                                    <p class="text-xs text-gray-400">{{ $debt->mobile_number }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $debt->date_given->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $debt->expected_return_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded
                                    {{ $debt->status === 'received' ? 'bg-emerald-100 text-emerald-700' :
                                       ($debt->status === 'partial_received' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700') }}">
                                    {{ str_replace('_', ' ', ucfirst($debt->status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium">₹{{ number_format($debt->amount, 0) }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-red-600">₹{{ number_format($debt->amount - $debt->received_amount, 0) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('bad-debt.edit', $debt) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                                    <form method="POST" action="{{ route('bad-debt.destroy', $debt) }}" onsubmit="return confirm('Delete?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-500 hover:underline text-xs">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-8 text-gray-400">No pending money records</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
        {{ $debts->links() }}
    </div>
</x-app-layout>
