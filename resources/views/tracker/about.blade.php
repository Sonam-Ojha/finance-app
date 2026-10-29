<x-app-layout>
    <x-slot name="title">About</x-slot>

    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach([
                ['Expenses recorded', number_format($expenses)],
                ['Categories', $categories],
                ['Storage', 'Synced to your account'],
            ] as [$label, $value])
                <div class="card">
                    <p class="text-sm muted">{{ $label }}</p>
                    <p class="mt-1 font-display text-xl font-semibold text-brand-navy dark:text-white">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="card space-y-2">
                <h2 class="card-title">Where your data lives</h2>
                <p>Your expenses, incomes and categories are stored in your account on the server, so signing in on another device — or here on the website — brings them back. Deleting the app does not delete your data.</p>
            </div>

            <div class="card space-y-2">
                <h2 class="card-title">What signing in protects</h2>
                <p>Your password is checked by the server and never stored in the clear.</p>
            </div>
        </div>

        <p class="text-xs muted text-center">Designed and developed by Devesh Dubey</p>
    </div>
</x-app-layout>
