<x-app-layout>
    <x-slot name="title">Investment Detail</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('investment.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Investment Detail</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Name</span>
                <span class="font-semibold text-gray-800">{{ $investment->investment_name }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Category</span>
                <span class="text-gray-700">{{ strtoupper($investment->category) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Date</span>
                <span class="text-gray-700">{{ $investment->date->format('d M Y') }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Amount Invested</span>
                <span class="text-xl font-bold text-indigo-700">₹{{ number_format($investment->amount_invested, 2) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Current Value</span>
                <span class="text-xl font-bold {{ $investment->current_value >= $investment->amount_invested ? 'text-emerald-600' : 'text-red-600' }}">
                    ₹{{ number_format($investment->current_value ?? 0, 2) }}
                </span>
            </div>
            @if($investment->maturity_date)
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Maturity Date</span>
                <span class="text-gray-700">{{ $investment->maturity_date->format('d M Y') }}</span>
            </div>
            @endif
            @if($investment->returns)
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Returns %</span>
                <span class="text-gray-700">{{ $investment->returns }}%</span>
            </div>
            @endif
            @if($investment->notes)
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Notes</p>
                <p class="text-gray-700">{{ $investment->notes }}</p>
            </div>
            @endif
            <div class="pt-3 flex gap-3 border-t">
                <a href="{{ route('investment.edit', $investment) }}" class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-700">Edit</a>
                <form method="POST" action="{{ route('investment.destroy', $investment) }}" onsubmit="return confirm('Delete?')">
                    @csrf @method('DELETE')
                    <button class="bg-red-100 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-red-200">Delete</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
