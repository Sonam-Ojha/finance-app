<x-app-layout>
    <x-slot name="title">Edit Income</x-slot>

    <div class="pt-2 max-w-2xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('income.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Edit Income</h2>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('income.update', $income) }}" x-data="{ type: '{{ $income->type }}' }">
                @csrf @method('PATCH')

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Income Type *</label>
                        <select name="type" x-model="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                            <option value="salary">Salary</option>
                            <option value="lic_commission">LIC Income / Commission</option>
                            <option value="business">Business Income</option>
                            <option value="received_from">Received From Person</option>
                            <option value="other">Other Income</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="date" value="{{ old('date', $income->date->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹) *</label>
                        <input type="number" name="amount" step="0.01" value="{{ old('amount', $income->amount) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                </div>

                <div x-show="type === 'salary'" class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                        <input type="text" name="company_name" value="{{ old('company_name', $income->company_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salary Month</label>
                        <input type="month" name="salary_month" value="{{ old('salary_month', $income->salary_month) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                <div x-show="type === 'lic_commission'" class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Client Name</label>
                        <input type="text" name="client_name" value="{{ old('client_name', $income->client_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Policy Number</label>
                        <input type="text" name="policy_number" value="{{ old('policy_number', $income->policy_number) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Plan Name</label>
                        <input type="text" name="plan_name" value="{{ old('plan_name', $income->plan_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Commission Type</label>
                        <input type="text" name="commission_type" value="{{ old('commission_type', $income->commission_type) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                <div x-show="type === 'business'" class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business Name</label>
                    <input type="text" name="business_name" value="{{ old('business_name', $income->business_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>

                <div x-show="type === 'received_from'" class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Person Name</label>
                        <input type="text" name="person_name" value="{{ old('person_name', $income->person_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                        <input type="text" name="mobile_number" value="{{ old('mobile_number', $income->mobile_number) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <input type="text" name="reason" value="{{ old('reason', $income->reason) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                <div x-show="type === 'other'" class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                    <input type="text" name="category_name" value="{{ old('category_name', $income->category_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode</label>
                    <select name="payment_mode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach(['Cash','Bank Transfer','UPI','Cheque'] as $mode)
                            <option value="{{ $mode }}" @selected(old('payment_mode', $income->payment_mode) == $mode)>{{ $mode }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                    <textarea name="note" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('note', $income->note) }}</textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="bg-emerald-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-emerald-700 transition">Update Income</button>
                    <a href="{{ route('income.index') }}" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
