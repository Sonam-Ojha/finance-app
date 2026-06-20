<x-app-layout>
    <x-slot name="title">Add Contact</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('contact.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Add Contact</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('contact.store') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                        <input type="text" name="mobile" value="{{ old('mobile') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="bg-gray-800 text-white px-6 py-2 rounded-lg font-medium hover:bg-gray-900">Save Contact</button>
                        <a href="{{ route('contact.index') }}" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
