<x-app-layout>
    <x-slot name="title">Expenses</x-slot>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2 flex-wrap">
            <h2 class="text-lg font-bold text-slate-800">Expense Records</h2>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('expense.categories') }}" class="border border-slate-300 text-slate-700 px-3 py-2 rounded-lg text-xs sm:text-sm hover:bg-slate-50 whitespace-nowrap">Categories</a>
                <a href="{{ route('expense.create') }}" class="bg-red-500 text-white px-3 py-2 rounded-lg text-xs sm:text-sm font-medium hover:bg-red-600 whitespace-nowrap">+ Add Expense</a>
            </div>
        </div>

        <form method="GET" class="bg-white rounded-xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500 block mb-1">Category</label>
                <select name="category_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id')==$cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Month</label>
                <select name="month" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Months</option>
                    @foreach(range(1,12) as $m)
                        <option value="{{ $m }}" @selected(request('month')==$m)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Year</label>
                <select name="year" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach(range(date('Y'), date('Y')-5) as $y)
                        <option value="{{ $y }}" @selected(request('year')==$y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <button class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm">Filter</button>
            <a href="{{ route('expense.index') }}" class="text-sm text-gray-500 hover:underline self-center">Reset</a>
        </form>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[580px]">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Date</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Category</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Description</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Payment Mode</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Amount</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($expenses as $expense)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ $expense->date->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs">{{ $expense->category->name ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ Str::limit($expense->description, 40) ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $expense->payment_mode ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-red-600">₹{{ number_format($expense->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('expense.edit', $expense) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                                    <form method="POST" action="{{ route('expense.destroy', $expense) }}" onsubmit="return confirm('Delete?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-500 hover:underline text-xs">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-8 text-gray-400">No expense records found</td></tr>
                    @endforelse
                </tbody>
                @if($expenses->count())
                <tfoot class="bg-gray-50 border-t">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right font-semibold text-gray-600">Total:</td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">₹{{ number_format($expenses->sum('amount'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div></div>
        {{ $expenses->links() }}
    </div>
</x-app-layout>
