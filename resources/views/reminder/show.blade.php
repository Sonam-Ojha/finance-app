<x-app-layout>
    <x-slot name="title">Reminder Detail</x-slot>
    <div class="pt-2 max-w-xl">
        <div class="flex items-center gap-3 mb-4">
            <a href="{{ route('reminder.index') }}" class="text-gray-500 hover:text-gray-700">← Back</a>
            <h2 class="text-xl font-bold text-gray-800">Reminder Detail</h2>
        </div>
        <div class="bg-white rounded-xl shadow p-6 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Title</span>
                <span class="font-semibold text-gray-800">{{ $reminder->title }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Type</span>
                <span class="text-gray-700">{{ str_replace('_', ' ', ucfirst($reminder->type)) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Due Date</span>
                <span class="text-gray-700">{{ $reminder->due_date->format('d M Y') }}</span>
            </div>
            @if($reminder->amount)
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Amount</span>
                <span class="text-xl font-bold text-orange-600">₹{{ number_format($reminder->amount, 2) }}</span>
            </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Status</span>
                <span class="text-xs px-2 py-1 rounded {{ $reminder->is_done ? 'bg-emerald-100 text-emerald-700' : 'bg-yellow-100 text-yellow-700' }}">
                    {{ $reminder->is_done ? 'Done' : 'Pending' }}
                </span>
            </div>
            @if($reminder->notes)
            <div><p class="text-sm font-medium text-gray-500 mb-1">Notes</p><p class="text-gray-700">{{ $reminder->notes }}</p></div>
            @endif
            <div class="pt-3 flex gap-3 border-t">
                @if(!$reminder->is_done)
                <form method="POST" action="{{ route('reminder.done', $reminder) }}">
                    @csrf @method('PATCH')
                    <button class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-emerald-700">Mark Done ✓</button>
                </form>
                @endif
                <a href="{{ route('reminder.edit', $reminder) }}" class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-700">Edit</a>
                <form method="POST" action="{{ route('reminder.destroy', $reminder) }}" onsubmit="return confirm('Delete?')">
                    @csrf @method('DELETE')
                    <button class="bg-red-100 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-red-200">Delete</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
