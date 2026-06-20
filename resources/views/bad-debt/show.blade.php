<x-app-layout>
    <x-slot name="title">Pending Money Detail</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('bad-debt.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Pending Money Detail</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Person</span>
                <span class="font-semibold text-gray-800">{{ $badDebt->person_name }}</span>
            </div>
            @if($badDebt->mobile_number)
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Mobile</span>
                <span class="text-gray-700">{{ $badDebt->mobile_number }}</span>
            </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Amount Given</span>
                <span class="text-xl font-bold text-red-600">₹{{ number_format($badDebt->amount, 2) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Received</span>
                <span class="text-xl font-bold text-emerald-600">₹{{ number_format($badDebt->received_amount ?? 0, 2) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Still Pending</span>
                <span class="text-xl font-bold text-orange-600">₹{{ number_format($badDebt->pending_amount, 2) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Date Given</span>
                <span class="text-gray-700">{{ $badDebt->date_given->format('d M Y') }}</span>
            </div>
            @if($badDebt->expected_return_date)
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Expected Return</span>
                <span class="text-gray-700">{{ $badDebt->expected_return_date->format('d M Y') }}</span>
            </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Status</span>
                @php $sc = ['pending'=>'yellow','received'=>'emerald','partial_received'=>'blue'][$badDebt->status] @endphp
                <span class="text-xs px-2 py-1 rounded bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ ucfirst(str_replace('_',' ',$badDebt->status)) }}</span>
            </div>
            @if($badDebt->reason)
            <div><p class="text-sm font-medium text-gray-500 mb-1">Reason</p><p class="text-gray-700">{{ $badDebt->reason }}</p></div>
            @endif
            @if($badDebt->notes)
            <div><p class="text-sm font-medium text-gray-500 mb-1">Notes</p><p class="text-gray-700">{{ $badDebt->notes }}</p></div>
            @endif
            <div class="pt-3 flex gap-3 border-t">
                <a href="{{ route('bad-debt.edit', $badDebt) }}" class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-700">Edit</a>
                <form method="POST" action="{{ route('bad-debt.destroy', $badDebt) }}" onsubmit="return confirm('Delete?')">
                    @csrf @method('DELETE')
                    <button class="bg-red-100 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-red-200">Delete</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
