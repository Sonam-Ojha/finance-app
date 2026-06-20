<x-app-layout>
    <x-slot name="title">Edit Pending Money</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('bad-debt.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Edit Pending Money Record</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('bad-debt.update', $badDebt) }}">
                @csrf @method('PATCH')
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Person Name *</label>
                        <input type="text" name="person_name" value="{{ old('person_name', $badDebt->person_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                        <input type="text" name="mobile_number" value="{{ old('mobile_number', $badDebt->mobile_number) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹) *</label>
                        <input type="number" name="amount" step="0.01" value="{{ old('amount', $badDebt->amount) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Given *</label>
                        <input type="date" name="date_given" value="{{ old('date_given', $badDebt->date_given->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <input type="text" name="reason" value="{{ old('reason', $badDebt->reason) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expected Return Date</label>
                        <input type="date" name="expected_return_date" value="{{ old('expected_return_date', $badDebt->expected_return_date?->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="pending" @selected(old('status', $badDebt->status) === 'pending')>Pending</option>
                            <option value="partial_received" @selected(old('status', $badDebt->status) === 'partial_received')>Partial Received</option>
                            <option value="received" @selected(old('status', $badDebt->status) === 'received')>Received</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount Received (₹)</label>
                        <input type="number" name="received_amount" step="0.01" value="{{ old('received_amount', $badDebt->received_amount) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $badDebt->notes) }}</textarea>
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-yellow-700">Update</button>
                    <a href="{{ route('bad-debt.index') }}" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
