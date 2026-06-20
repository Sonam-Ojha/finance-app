<x-app-layout>
    <x-slot name="title">Edit Bank Account</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('bank.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Edit Bank Account</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('bank.update', $bank) }}">
                @csrf @method('PATCH')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name *</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $bank->bank_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last 4 Digits</label>
                            <input type="text" name="account_number_last4" value="{{ old('account_number_last4', $bank->account_number_last4) }}" maxlength="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Account Type</label>
                            <select name="account_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                @foreach(['savings','current','salary'] as $t)
                                    <option value="{{ $t }}" @selected(old('account_type', $bank->account_type) == $t)>{{ ucfirst($t) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Current Balance (₹) *</label>
                            <input type="number" name="current_balance" step="0.01" value="{{ old('current_balance', $bank->current_balance) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">IFSC Code</label>
                            <input type="text" name="ifsc_code" value="{{ old('ifsc_code', $bank->ifsc_code) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $bank->notes) }}</textarea>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700">Update</button>
                        <a href="{{ route('bank.index') }}" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
