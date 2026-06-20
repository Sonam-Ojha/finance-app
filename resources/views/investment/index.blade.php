<x-app-layout>
    <x-slot name="title">Investments</x-slot>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-slate-800">Investments</h2>
            <a href="{{ route('investment.create') }}" class="bg-indigo-600 text-white px-3 py-2 rounded-lg text-xs sm:text-sm font-medium hover:bg-indigo-700 whitespace-nowrap">+ Add Investment</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-indigo-500 text-white rounded-xl p-4">
                <p class="text-xs opacity-80">Total Invested</p>
                <p class="text-2xl font-bold mt-1">₹{{ number_format($totalInvested, 0) }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow border-l-4 border-emerald-400">
                <p class="text-xs text-gray-500">Current Value</p>
                <p class="text-xl font-bold text-emerald-700 mt-1">₹{{ number_format($totalCurrent, 0) }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow border-l-4 border-{{ ($totalCurrent - $totalInvested) >= 0 ? 'emerald' : 'red' }}-400">
                <p class="text-xs text-gray-500">Gain / Loss</p>
                <p class="text-xl font-bold {{ ($totalCurrent - $totalInvested) >= 0 ? 'text-emerald-600' : 'text-red-600' }} mt-1">
                    {{ ($totalCurrent - $totalInvested) >= 0 ? '+' : '' }}₹{{ number_format($totalCurrent - $totalInvested, 0) }}
                </p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[520px]">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Name</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Category</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Date</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Invested</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Current</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($investments as $inv)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $inv->investment_name }}</td>
                            <td class="px-4 py-3">
                                <span class="bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded text-xs">{{ strtoupper($inv->category) }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $inv->date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right font-medium">₹{{ number_format($inv->amount_invested, 0) }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $inv->current_value >= $inv->amount_invested ? 'text-emerald-600' : 'text-red-500' }}">
                                ₹{{ number_format($inv->current_value ?? 0, 0) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('investment.edit', $inv) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                                    <form method="POST" action="{{ route('investment.destroy', $inv) }}" onsubmit="return confirm('Delete?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-500 hover:underline text-xs">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-8 text-gray-400">No investments added yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
        {{ $investments->links() }}
    </div>
</x-app-layout>
