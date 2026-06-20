<x-app-layout>
    <x-slot name="title">Add Investment</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('investment.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Add Investment</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('investment.store') }}">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Investment Name *</label>
                        <input type="text" name="investment_name" value="{{ old('investment_name') }}" placeholder="e.g. SBI Life Policy, Nifty 50 SIP" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select name="category" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                            <option value="lic">LIC Policy</option>
                            <option value="mutual_fund">Mutual Fund</option>
                            <option value="stocks">Stocks</option>
                            <option value="fd">FD (Fixed Deposit)</option>
                            <option value="gold">Gold</option>
                            <option value="property">Property</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
                        <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount Invested (₹) *</label>
                        <input type="number" name="amount_invested" step="0.01" value="{{ old('amount_invested') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Value (₹)</label>
                        <input type="number" name="current_value" step="0.01" value="{{ old('current_value') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Maturity Date</label>
                        <input type="date" name="maturity_date" value="{{ old('maturity_date') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Returns (₹)</label>
                        <input type="number" name="returns" step="0.01" value="{{ old('returns') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-indigo-700">Save Investment</button>
                    <a href="{{ route('investment.index') }}" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
