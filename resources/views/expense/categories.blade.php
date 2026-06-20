<x-app-layout>
    <x-slot name="title">Expense Categories</x-slot>
    <div class="pt-2 max-w-2xl space-y-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('expense.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Manage Expense Categories</h2>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="font-semibold text-gray-700 mb-4">Add Custom Category</h3>
            <form method="POST" action="{{ route('expense.categories.store') }}" class="flex gap-3 items-end">
                @csrf
                <div class="flex-1">
                    <label class="text-xs text-gray-500 block mb-1">Category Name</label>
                    <input type="text" name="name" placeholder="e.g. Pet Care" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Group</label>
                    <select name="group" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="home">Home</option>
                        <option value="personal">Personal</option>
                        <option value="business">Business</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <button class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm">Add</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Category</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Group</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600">Type</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($categories as $cat)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $cat->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ ucfirst($cat->group) }}</td>
                            <td class="px-4 py-3">
                                @if($cat->is_default)
                                    <span class="text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded">Default</span>
                                @else
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">Custom</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if(!$cat->is_default)
                                    <form method="POST" action="{{ route('expense.categories.destroy', $cat) }}" onsubmit="return confirm('Delete category?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-500 hover:underline text-xs">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
