<x-app-layout>
    <x-slot name="title">Add Expense</x-slot>
    <div class="pt-2 max-w-2xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('expense.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Add Expense</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('expense.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select name="expense_category_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                            <option value="">Select Category</option>
                            @foreach($categories->groupBy('group') as $group => $cats)
                                <optgroup label="{{ ucfirst($group) }} Expenses">
                                    @foreach($cats as $cat)
                                        <option value="{{ $cat->id }}" @selected(old('expense_category_id') == $cat->id)>{{ $cat->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹) *</label>
                        <input type="number" name="amount" step="0.01" value="{{ old('amount') }}" placeholder="0.00" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode</label>
                        <select name="payment_mode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Select</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="UPI">UPI</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bill Receipt (Photo)</label>
                        <input type="file" name="receipt_photo" accept="image/*" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('description') }}</textarea>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="bg-red-500 text-white px-6 py-2 rounded-lg font-medium hover:bg-red-600 transition">Save Expense</button>
                    <a href="{{ route('expense.index') }}" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
