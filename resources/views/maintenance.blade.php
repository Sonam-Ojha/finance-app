{{-- Maintenance mode (Admin › App control): what everyone but admins sees,
     with a 503. App\Http\Middleware\WebMaintenance. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Back soon – Expense Tracker</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|poppins:500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-surface-page p-4 font-inter text-sm leading-[1.45] text-ink antialiased">
    <main class="w-full max-w-md rounded-card bg-surface-card p-8 text-center shadow-sm">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-tint text-brand-navy" aria-hidden="true">
            <x-icon name="adjustments" class="h-7 w-7" />
        </div>
        <p class="mt-5 text-[13px] font-semibold uppercase tracking-[0.05em] text-ink-muted">Expense Tracker</p>
        <h1 class="mt-1 font-display text-2xl font-semibold text-brand-navy">We'll be back soon</h1>
        <p class="mt-3 whitespace-pre-line break-words text-[15px] text-ink">{{ $message }}</p>
        <p class="mt-4 text-[13px] text-ink-muted">Everything you recorded is safe. Try again in a little while.</p>

        <div class="mt-6 flex flex-col items-center gap-2">
            <a href="{{ url()->current() }}" class="inline-flex h-11 items-center justify-center rounded-card bg-brand-navy px-5 font-semibold text-white hover:bg-[#0b3d77]">Try again</a>
            @if($user)
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="min-h-11 px-3 text-[13px] font-semibold text-brand-navy hover:underline">Sign out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="min-h-11 px-3 py-3 text-[13px] text-ink-muted hover:underline">Team sign-in</a>
            @endif
        </div>
    </main>
</body>
</html>
