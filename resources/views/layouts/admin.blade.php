@php
    $user = Auth::user();
    $words = preg_split('/\s+/', trim($user->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    $initials = count($words) > 1
        ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1))
        : mb_strtoupper(mb_substr($words[0] ?? ($user->email ?? '?'), 0, 2));
    $avatarUrl = \App\Support\Accounts::avatarUrl($user);

    // [route or null while it is not built, label, icon, active patterns]
    $nav = [
        ['admin.dashboard', 'Dashboard', 'chart', ['admin.dashboard']],
        ['admin.users.index', 'Users', 'users', ['admin.users.*']],
        [null, 'Default categories', 'tag', []],
        [null, 'Currencies & banks', 'money', []],
        ['admin.notifications.index', 'Notifications', 'megaphone', ['admin.notifications.*']],
        [null, 'App control', 'cog', []],
        // TODO: a count badge once support tickets exist.
        [null, 'Support', 'envelope', []],
        ['admin.activity.index', 'Activity log', 'list', ['admin.activity.*']],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} – Expense Tracker Admin</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,600|poppins:500,600|jetbrains-mono:400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak]{display:none!important}
        .adm :focus-visible{outline:2px solid #002e62;outline-offset:2px}
        .adm-side :focus-visible{outline-color:#fff}
    </style>
</head>
<body class="adm font-inter antialiased bg-surface-page text-ink text-sm leading-[1.45]" x-data="{ open: false }">

{{-- Phones: a top bar with the menu button --}}
<header class="md:hidden sticky top-0 z-30 flex items-center gap-3 bg-brand-navy px-4 h-14 text-white">
    <button type="button" @click="open = true" class="-ml-2 flex h-11 w-11 items-center justify-center rounded-chip hover:bg-white/10" aria-label="Open menu">
        <x-icon name="menu" />
    </button>
    <span class="font-display text-lg font-semibold">Expense Tracker</span>
    <span class="adm-eyebrow text-brand-cyan">Admin</span>
</header>

<div class="flex min-h-screen flex-wrap md:flex-nowrap">

    {{-- ══════════ SIDEBAR ══════════ --}}
    <aside class="adm-side fixed inset-y-0 left-0 z-50 flex w-[240px] flex-col gap-7 overflow-y-auto bg-brand-navy px-4 py-7 text-white transition-transform duration-300
                  md:sticky md:top-0 md:h-screen md:flex-shrink-0 md:translate-x-0"
           :class="open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">

        <div class="flex items-start justify-between px-3">
            <div>
                <p class="font-display text-xl font-semibold leading-[1.2]">Expense Tracker</p>
                <p class="adm-eyebrow mt-1 text-brand-cyan">Admin panel</p>
            </div>
            <button type="button" @click="open = false" class="md:hidden -mr-2 flex h-11 w-11 items-center justify-center rounded-chip hover:bg-white/10" aria-label="Close menu">
                <x-icon name="x" />
            </button>
        </div>

        {{-- One login, two views: running the app, and your own expenses --}}
        <nav class="grid grid-cols-2 gap-1 rounded-card bg-white/[.12] p-1" aria-label="Switch view">
            <span aria-current="page" class="flex h-10 items-center justify-center rounded-[9px] bg-white text-sm font-semibold text-brand-navy">Admin</span>
            <a href="{{ route('dashboard') }}" class="flex h-10 items-center justify-center rounded-[9px] text-sm font-semibold text-white hover:bg-white/[.08]">My account</a>
        </nav>

        <nav class="flex flex-col gap-1" aria-label="Admin">
            @foreach($nav as [$route, $label, $icon, $patterns])
                @if($route)
                    @php $active = request()->routeIs(...$patterns); @endphp
                    <a href="{{ route($route) }}" @click="open = false" @if($active) aria-current="page" @endif
                       class="flex min-h-11 items-center gap-3 rounded-chip px-3 py-[11px] text-[15px] text-white no-underline {{ $active ? 'bg-white/[.14] font-semibold' : 'hover:bg-white/[.08]' }}">
                        <x-icon :name="$icon" class="h-5 w-5 flex-shrink-0" />
                        <span>{{ $label }}</span>
                    </a>
                @else
                    <span aria-disabled="true" class="flex min-h-11 cursor-not-allowed items-center gap-3 rounded-chip px-3 py-[11px] text-[15px] text-white/60">
                        <x-icon :name="$icon" class="h-5 w-5 flex-shrink-0" />
                        <span class="flex-1">{{ $label }}</span>
                        <span class="rounded-full bg-white/[.12] px-2 py-0.5 text-xs font-semibold text-white/80">Soon</span>
                    </span>
                @endif
            @endforeach
        </nav>

        <div class="mt-auto flex flex-col gap-3 border-t border-white/20 pt-5">
            <a href="{{ route('dashboard') }}" class="flex min-h-11 items-center gap-3 rounded-chip px-3 text-[15px] text-white hover:bg-white/[.08]">
                <x-icon name="wallet" class="h-5 w-5" /> My own expenses
            </a>
            <div class="flex items-center gap-3 px-3">
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="" class="h-9 w-9 flex-shrink-0 rounded-full object-cover">
                @else
                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-brand-cyan text-sm font-semibold text-brand-navy">{{ $initials }}</span>
                @endif
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ $user->name ?: 'Admin' }}</p>
                    <p class="text-[13px] text-white/80">Admin · also a user</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}"
                  data-confirm-title="Sign out?"
                  data-confirm-message="You will need your password to sign back in. Every expense, income and category stays safely in your account."
                  data-confirm-button="Sign out">
                @csrf
                <button type="submit" class="flex min-h-11 w-full items-center gap-3 rounded-chip px-3 text-[15px] text-white hover:bg-white/[.08]">
                    <x-icon name="logout" class="h-5 w-5" /> Sign out
                </button>
            </form>
        </div>
    </aside>

    <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 z-40 bg-black/50 md:hidden"></div>

    {{-- ══════════ MAIN ══════════ --}}
    <main class="flex min-w-0 flex-[999_1_560px] flex-col gap-6 px-4 pb-12 pt-6 md:px-10 md:pt-8">
        @if(session('success'))
            <div class="adm-strip" role="status">
                <x-icon name="check" class="mt-0.5 h-5 w-5 flex-shrink-0 text-status-successText" />
                <p><span class="font-semibold text-status-successText">Done.</span> {{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div class="adm-strip" role="alert">
                <x-icon name="warning" class="mt-0.5 h-5 w-5 flex-shrink-0 text-status-error" />
                <p><span class="font-semibold text-status-error">Heads up.</span> {{ session('error') }}</p>
            </div>
        @endif

        {{ $slot }}
    </main>
</div>

{{-- Confirm before a destructive submit (see resources/js/app.js) --}}
<div x-data="confirmDialog" @confirm-submit.window="ask($event.detail)" @keydown.escape.window="open = false">
    <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" @click.self="open = false">
        <div class="adm-card w-full max-w-sm border border-surface-line" role="alertdialog" aria-modal="true" :aria-label="title">
            <h2 class="adm-h2" x-text="title"></h2>
            <p class="mt-2 text-sm text-ink-muted" x-text="message" x-show="message"></p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="adm-btn-outline !h-11" @click="open = false">Cancel</button>
                <button type="button" class="adm-btn-primary !h-11" @click="confirm()" x-text="button"></button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
