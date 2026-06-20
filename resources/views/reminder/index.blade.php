<x-app-layout>
    <x-slot name="title">Reminders</x-slot>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800">Reminders</h2>
            <a href="{{ route('reminder.create') }}" class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-yellow-700">+ Add Reminder</a>
        </div>

        @if($upcoming->count())
        <div>
            <h3 class="font-semibold text-gray-600 mb-2">🔔 Upcoming</h3>
            <div class="space-y-2">
                @foreach($upcoming as $r)
                    <div class="bg-white rounded-xl shadow-sm p-4 flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-800">{{ $r->title }}</p>
                            <div class="flex gap-3 text-xs text-gray-400 mt-0.5">
                                <span>{{ $r->due_date->format('d M Y') }}</span>
                                <span class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ str_replace('_', ' ', ucfirst($r->type)) }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            @if($r->amount)
                                <span class="font-semibold text-orange-600">₹{{ number_format($r->amount, 0) }}</span>
                            @endif
                            <form method="POST" action="{{ route('reminder.done', $r) }}">
                                @csrf @method('PATCH')
                                <button class="bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded-lg text-xs hover:bg-emerald-200">Mark Done ✓</button>
                            </form>
                            <a href="{{ route('reminder.edit', $r) }}" class="text-xs text-blue-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('reminder.destroy', $r) }}" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-500 hover:underline">Delete</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($past->count())
        <div>
            <h3 class="font-semibold text-gray-400 mb-2">✅ Past / Done</h3>
            <div class="space-y-2">
                @foreach($past as $r)
                    <div class="bg-gray-50 rounded-xl p-4 flex items-center justify-between opacity-60">
                        <div>
                            <p class="font-medium text-gray-700 line-through">{{ $r->title }}</p>
                            <p class="text-xs text-gray-400">{{ $r->due_date->format('d M Y') }}</p>
                        </div>
                        @if($r->amount)
                            <span class="text-sm text-gray-500">₹{{ number_format($r->amount, 0) }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($upcoming->isEmpty() && $past->isEmpty())
            <div class="text-center py-12 text-gray-400">
                <p class="text-4xl mb-2">🔔</p>
                <p>No reminders set</p>
            </div>
        @endif
    </div>
</x-app-layout>
