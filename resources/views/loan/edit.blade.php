<x-app-layout>
    <x-slot name="title">Edit Loan</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('loan.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Edit Loan</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('loan.update', $loan) }}">
                @csrf @method('PATCH')
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Loan Name *</label>
                        <input type="text" name="loan_name" value="{{ old('loan_name', $loan->loan_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank / Person Name *</label>
                        <input type="text" name="bank_or_person_name" value="{{ old('bank_or_person_name', $loan->bank_or_person_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Loan Amount (₹) *</label>
                        <input type="number" name="total_amount" step="0.01" value="{{ old('total_amount', $loan->total_amount) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pending Amount (₹) *</label>
                        <input type="number" name="pending_amount" step="0.01" value="{{ old('pending_amount', $loan->pending_amount) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Interest Rate (%)</label>
                        <input type="number" name="interest_rate" step="0.01" value="{{ old('interest_rate', $loan->interest_rate) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">EMI Amount (₹)</label>
                        <input type="number" name="emi_amount" step="0.01" value="{{ old('emi_amount', $loan->emi_amount) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" value="{{ old('start_date', $loan->start_date?->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" value="{{ old('end_date', $loan->end_date?->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="active" @selected(old('status', $loan->status) == 'active')>Active</option>
                            <option value="closed" @selected(old('status', $loan->status) == 'closed')>Closed</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $loan->notes) }}</textarea>
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-orange-700">Update</button>
                    <a href="{{ route('loan.index') }}" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
