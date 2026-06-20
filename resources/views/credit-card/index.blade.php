<x-app-layout>
    <x-slot name="title">Credit Cards</x-slot>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800">Credit Cards</h2>
            <a href="{{ route('credit-card.create') }}" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-purple-700">+ Add Card</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($cards as $card)
                <div class="bg-gradient-to-br from-purple-600 to-purple-900 text-white rounded-xl shadow-sm p-5">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <p class="font-bold text-lg">{{ $card->card_name }}</p>
                            <p class="text-xs opacity-70">{{ $card->bank_name }}</p>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('credit-card.edit', $card) }}" class="text-xs text-white/70 hover:text-white">Edit</a>
                            <form method="POST" action="{{ route('credit-card.destroy', $card) }}" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-300 hover:text-red-100">Delete</button>
                            </form>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="flex justify-between text-xs opacity-70 mb-1">
                            <span>Used</span>
                            <span>₹{{ number_format($card->outstanding_amount, 0) }} / ₹{{ number_format($card->credit_limit, 0) }}</span>
                        </div>
                        <div class="bg-white/20 rounded-full h-2">
                            <div class="bg-yellow-400 h-2 rounded-full" style="width: {{ $card->credit_limit > 0 ? min(100, ($card->outstanding_amount/$card->credit_limit)*100) : 0 }}%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-xs opacity-70">Available Limit</p>
                            <p class="text-xl font-bold">₹{{ number_format($card->credit_limit - $card->outstanding_amount, 0) }}</p>
                        </div>
                        @if($card->due_date_day)
                            <p class="text-xs opacity-70">Due: {{ $card->due_date_day }}th every month</p>
                        @endif
                    </div>
                    <a href="{{ route('credit-card.show', $card) }}" class="block text-center bg-white/20 hover:bg-white/30 py-2 rounded-lg text-sm mt-4 transition">View Transactions</a>
                </div>
            @empty
                <div class="col-span-3 text-center py-12 text-gray-400">
                    <p class="text-4xl mb-2">💳</p>
                    <p>No credit cards added yet</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
