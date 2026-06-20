<x-app-layout>
    <x-slot name="title">Commission Detail</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('commission.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Commission Detail</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Date</span>
                <span class="text-gray-700">{{ $commission->date->format('d M Y') }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Source</span>
                <span class="font-semibold text-gray-800">{{ $commission->source_name }}</span>
            </div>
            @if($commission->client_name)
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Client</span>
                <span class="text-gray-700">{{ $commission->client_name }}</span>
            </div>
            @endif
            @if($commission->product_name)
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Product</span>
                <span class="text-gray-700">{{ $commission->product_name }}</span>
            </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Amount</span>
                <span class="text-2xl font-bold text-teal-700">₹{{ number_format($commission->commission_amount, 2) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Status</span>
                <span class="text-xs px-2 py-1 rounded {{ $commission->status === 'received' ? 'bg-emerald-100 text-emerald-700' : 'bg-yellow-100 text-yellow-700' }}">
                    {{ ucfirst($commission->status) }}
                </span>
            </div>
            @if($commission->notes)
            <div><p class="text-sm font-medium text-gray-500 mb-1">Notes</p><p class="text-gray-700">{{ $commission->notes }}</p></div>
            @endif
            <div class="pt-3 flex gap-3 border-t">
                <a href="{{ route('commission.edit', $commission) }}" class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-700">Edit</a>
                <form method="POST" action="{{ route('commission.destroy', $commission) }}" onsubmit="return confirm('Delete?')">
                    @csrf @method('DELETE')
                    <button class="bg-red-100 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-red-200">Delete</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
