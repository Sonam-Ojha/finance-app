<x-app-layout>
    <x-slot name="title">Edit Cashback</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('cashback.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Edit Cashback</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('cashback.update', $cashback) }}">
                @csrf @method('PATCH')
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="date" value="{{ old('date', $cashback->date->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹) *</label>
                        <input type="number" name="amount" step="0.01" value="{{ old('amount', $cashback->amount) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Platform Name *</label>
                        <input type="text" name="platform_name" value="{{ old('platform_name', $cashback->platform_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="received" @selected(old('status', $cashback->status) === 'received')>Received</option>
                            <option value="pending" @selected(old('status', $cashback->status) === 'pending')>Pending</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $cashback->notes) }}</textarea>
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="bg-pink-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-pink-700">Update</button>
                    <a href="{{ route('cashback.index') }}" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
