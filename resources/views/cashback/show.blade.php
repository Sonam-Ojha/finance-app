<x-app-layout>
    <x-slot name="title">Cashback Detail</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('cashback.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Cashback Detail</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Date</span>
                <span class="text-gray-700">{{ $cashback->date->format('d M Y') }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Platform</span>
                <span class="font-semibold text-gray-800">{{ $cashback->platform_name }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Amount</span>
                <span class="text-2xl font-bold text-purple-700">₹{{ number_format($cashback->amount, 2) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Status</span>
                <span class="text-xs px-2 py-1 rounded {{ $cashback->status === 'received' ? 'bg-emerald-100 text-emerald-700' : 'bg-yellow-100 text-yellow-700' }}">
                    {{ ucfirst($cashback->status) }}
                </span>
            </div>
            @if($cashback->notes)
            <div><p class="text-sm font-medium text-gray-500 mb-1">Notes</p><p class="text-gray-700">{{ $cashback->notes }}</p></div>
            @endif
            <div class="pt-3 flex gap-3 border-t">
                <a href="{{ route('cashback.edit', $cashback) }}" class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-700">Edit</a>
                <form method="POST" action="{{ route('cashback.destroy', $cashback) }}" onsubmit="return confirm('Delete?')">
                    @csrf @method('DELETE')
                    <button class="bg-red-100 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-red-200">Delete</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
