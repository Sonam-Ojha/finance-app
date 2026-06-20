<x-app-layout>
    <x-slot name="title">Add Credit Card</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('credit-card.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Add Credit Card</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('credit-card.store') }}">
                @csrf
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Card Name *</label>
                            <input type="text" name="card_name" value="{{ old('card_name') }}" placeholder="e.g. HDFC Regalia" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name *</label>
                            <input type="text" name="bank_name" value="{{ old('bank_name') }}" placeholder="e.g. HDFC" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Credit Limit (₹) *</label>
                            <input type="number" name="credit_limit" step="0.01" value="{{ old('credit_limit', 0) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Outstanding Amount (₹) *</label>
                            <input type="number" name="outstanding_amount" step="0.01" value="{{ old('outstanding_amount', 0) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date (Day of Month)</label>
                            <input type="number" name="due_date_day" min="1" max="31" value="{{ old('due_date_day') }}" placeholder="e.g. 15" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-purple-700">Save Card</button>
                        <a href="{{ route('credit-card.index') }}" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
