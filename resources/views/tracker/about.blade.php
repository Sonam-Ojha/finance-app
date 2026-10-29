<x-app-layout>
    <x-slot name="title">About</x-slot>

    <div class="max-w-2xl mx-auto space-y-4">
        <div class="card !p-0 divide-soft">
            @foreach([
                ['Expenses recorded', number_format($expenses)],
                ['Categories', $categories],
                ['Storage', 'Synced to your account'],
            ] as [$label, $value])
                <div class="flex items-center justify-between px-4 py-3.5">
                    <span class="strong">{{ $label }}</span>
                    <span class="text-sm muted">{{ $value }}</span>
                </div>
            @endforeach
        </div>

        <div class="card space-y-2">
            <h3 class="card-title">Where your data lives</h3>
            <p class="text-sm">Your expenses, incomes and categories are stored in your account on the server, so signing in on another device — or here on the website — brings them back. Deleting the app does not delete your data.</p>
        </div>

        <div class="card space-y-2">
            <h3 class="card-title">What signing in protects</h3>
            <p class="text-sm">Your password is checked by the server and never stored in the clear.</p>
        </div>

        <p class="text-xs muted text-center">Designed and developed by Devesh Dubey</p>
    </div>
</x-app-layout>
