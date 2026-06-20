<x-app-layout>
    <x-slot name="title">Loans / EMI</x-slot>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800">Loans & EMI</h2>
            <a href="{{ route('loan.create') }}" class="bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-orange-700">+ Add Loan</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @forelse($loans as $loan)
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="font-bold text-gray-800">{{ $loan->loan_name }}</p>
                            <p class="text-xs text-gray-400">{{ $loan->bank_or_person_name }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2 py-0.5 rounded {{ $loan->status === 'active' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-500' }}">{{ ucfirst($loan->status) }}</span>
                            <a href="{{ route('loan.edit', $loan) }}" class="text-xs text-blue-600 hover:underline">Edit</a>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 my-3 text-center">
                        <div class="bg-gray-50 rounded-lg p-2">
                            <p class="text-xs text-gray-400">Total</p>
                            <p class="text-sm font-bold text-gray-700">₹{{ number_format($loan->total_amount, 0) }}</p>
                        </div>
                        <div class="bg-red-50 rounded-lg p-2">
                            <p class="text-xs text-gray-400">Pending</p>
                            <p class="text-sm font-bold text-red-600">₹{{ number_format($loan->pending_amount, 0) }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2">
                            <p class="text-xs text-gray-400">EMI</p>
                            <p class="text-sm font-bold text-gray-700">₹{{ number_format($loan->emi_amount, 0) }}</p>
                        </div>
                    </div>
                    @if($loan->interest_rate)
                        <p class="text-xs text-gray-400 mb-2">Interest: {{ $loan->interest_rate }}% |
                            @if($loan->end_date) Ends: {{ $loan->end_date->format('M Y') }} @endif
                        </p>
                    @endif
                    <a href="{{ route('loan.show', $loan) }}" class="block text-center bg-orange-50 text-orange-700 py-2 rounded-lg text-sm hover:bg-orange-100 transition mt-2">Payment History</a>
                </div>
            @empty
                <div class="col-span-2 text-center py-12 text-gray-400">
                    <p class="text-4xl mb-2">🏛️</p>
                    <p>No loans added yet</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
