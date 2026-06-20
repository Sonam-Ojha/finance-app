<x-app-layout>
    <x-slot name="title">Add Commission</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('commission.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Add Commission</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('commission.store') }}">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Commission Amount (₹) *</label>
                        <input type="number" name="commission_amount" step="0.01" value="{{ old('commission_amount') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Source Name * (e.g. LIC, Insurance Company)</label>
                        <input type="text" name="source_name" value="{{ old('source_name') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Client Name</label>
                        <input type="text" name="client_name" value="{{ old('client_name') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                        <input type="text" name="product_name" value="{{ old('product_name') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="received">Received</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="bg-teal-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-teal-700">Save</button>
                    <a href="{{ route('commission.index') }}" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
