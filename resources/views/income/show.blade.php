<x-app-layout>
    <x-slot name="title">Income Detail</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('income.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Income Detail</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Type</span>
                <span class="font-semibold text-gray-800">{{ \App\Models\Income::typeLabel($income->type) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Date</span>
                <span class="text-gray-700">{{ $income->date->format('d M Y') }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Amount</span>
                <span class="text-2xl font-bold text-emerald-600">₹{{ number_format($income->amount, 2) }}</span>
            </div>
            @if($income->payment_mode)
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Payment Mode</span>
                <span class="text-gray-700">{{ ucfirst(str_replace('_',' ',$income->payment_mode)) }}</span>
            </div>
            @endif
            @foreach(['company_name'=>'Company','salary_month'=>'Salary Month','client_name'=>'Client','policy_number'=>'Policy #','person_name'=>'Person','mobile_number'=>'Mobile','business_name'=>'Business','reason'=>'Reason','note'=>'Note'] as $field => $label)
                @if($income->$field)
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500">{{ $label }}</span>
                    <span class="text-gray-700">{{ $income->$field }}</span>
                </div>
                @endif
            @endforeach
            <div class="pt-3 flex gap-3 border-t">
                <a href="{{ route('income.edit', $income) }}" class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-700">Edit</a>
                <form method="POST" action="{{ route('income.destroy', $income) }}" onsubmit="return confirm('Delete this income record?')">
                    @csrf @method('DELETE')
                    <button class="bg-red-100 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-red-200">Delete</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
