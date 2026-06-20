<x-app-layout>
    <x-slot name="title">Contact Ledger</x-slot>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800">Contact Ledger</h2>
            <a href="{{ route('contact.create') }}" class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-900">+ Add Contact</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($contacts as $contact)
                @php
                    $lent = $contact->transactions->where('type', 'lent')->sum('amount');
                    $borrowed = $contact->transactions->where('type', 'borrowed')->sum('amount');
                    $balance = $lent - $borrowed;
                @endphp
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-bold text-gray-800">{{ $contact->name }}</p>
                            @if($contact->mobile)
                                <p class="text-xs text-gray-400">{{ $contact->mobile }}</p>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('contact.edit', $contact) }}" class="text-xs text-blue-600 hover:underline">Edit</a>
                        </div>
                    </div>
                    <div class="mt-3">
                        @if($balance > 0)
                            <p class="text-emerald-600 font-bold">Lent: ₹{{ number_format($balance, 0) }}</p>
                            <p class="text-xs text-gray-400">They owe you</p>
                        @elseif($balance < 0)
                            <p class="text-red-600 font-bold">Borrowed: ₹{{ number_format(abs($balance), 0) }}</p>
                            <p class="text-xs text-gray-400">You owe them</p>
                        @else
                            <p class="text-gray-500 font-medium">Settled ✓</p>
                        @endif
                    </div>
                    <a href="{{ route('contact.show', $contact) }}" class="block text-center bg-gray-50 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-100 mt-3">View Ledger</a>
                </div>
            @empty
                <div class="col-span-3 text-center py-12 text-gray-400">
                    <p class="text-4xl mb-2">👥</p>
                    <p>No contacts added yet</p>
                </div>
            @endforelse
        </div>
        {{ $contacts->links() }}
    </div>
</x-app-layout>
