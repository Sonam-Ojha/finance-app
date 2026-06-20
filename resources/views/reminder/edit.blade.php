<x-app-layout>
    <x-slot name="title">Edit Reminder</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('reminder.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Edit Reminder</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('reminder.update', $reminder) }}">
                @csrf @method('PATCH')
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" name="title" value="{{ old('title', $reminder->title) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type *</label>
                        <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            @foreach(['loan_emi'=>'Loan EMI','credit_card'=>'Credit Card Due','pending_payment'=>'Pending Payment','investment_maturity'=>'Investment Maturity','other'=>'Other'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('type', $reminder->type) == $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Due Date *</label>
                        <input type="date" name="due_date" value="{{ old('due_date', $reminder->due_date->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹)</label>
                        <input type="number" name="amount" step="0.01" value="{{ old('amount', $reminder->amount) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $reminder->notes) }}</textarea>
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-yellow-700">Update</button>
                    <a href="{{ route('reminder.index') }}" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
