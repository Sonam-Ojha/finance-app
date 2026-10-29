{{--
    The one frame around every signed-in page: the sidebar, the top bar and
    the page heading. layouts/app ("My account") and layouts/admin include it
    with $area set, so both views share one design system; only the links and
    the header actions differ.

    Pages set the title slot, and may add heading (when the h1 should read
    differently from the browser tab), subtitle and actions.
--}}
@php
    $user = Auth::user();
    $admin = $area === 'admin';
    // The admin pages are light only; My account follows Appearance.
    $theme = $admin ? 'light' : ($user->theme_mode ?: 'light');
    [, $sidebar, $eyebrow] = \App\Support\SidebarColors::for($user);
    $avatarUrl = \App\Support\Accounts::avatarUrl($user);
    $words = preg_split('/\s+/', trim($user->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    $initials = count($words) > 1
        ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1))
        : mb_strtoupper(mb_substr($words[0] ?? ($user->email ?? '?'), 0, 2));
    $section = $admin ? 'Admin panel' : 'My account';
    $pageTitle = $title ?? 'Home';

    // [route or null while it is not built, label, icon, active patterns]
    $nav = $admin ? [
        ['admin.dashboard', 'Dashboard', 'chart', ['admin.dashboard']],
        ['admin.users.index', 'Users', 'users', ['admin.users.*']],
        [null, 'Default categories', 'tag', []],
        [null, 'Currencies & banks', 'money', []],
        ['admin.notifications.index', 'Notifications', 'megaphone', ['admin.notifications.*']],
        [null, 'App control', 'adjustments', []],
        // TODO: a count badge once support tickets exist.
        [null, 'Support', 'envelope', []],
        ['admin.activity.index', 'Activity log', 'list', ['admin.activity.*']],
        ['admin.settings', 'Settings', 'cog', ['admin.settings']],
    ] : [
        // The app's tabs and screens, in its order.
        ['dashboard', 'Home', 'home', ['dashboard']],
        ['transactions.index', 'Transactions', 'list', ['transactions.*', 'expenses.*', 'incomes.*']],
        ['analytics.index', 'Analytics', 'chart', ['analytics.*']],
        ['budgets.index', 'Budgets', 'wallet', ['budgets.*']],
        ['categories.index', 'Categories', 'tag', ['categories.*']],
        ['notifications.index', 'Notifications', 'bell', ['notifications.*']],
        ['settings.index', 'Settings', 'cog', ['settings.*', 'currency.*', 'about', 'profile.*', 'account.*']],
    ];
    $mobileTabs = $admin ? [] : [$nav[0], $nav[1], $nav[2], $nav[6]];
    $alerts = $admin ? 0 : $overBudget;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} – Expense Tracker{{ $admin ? ' Admin' : '' }}</title>
    @if($theme === 'system')
        <script>if (matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');</script>
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|poppins:500,600,700|jetbrains-mono:400&display=swap" rel="stylesheet">
    @unless($admin)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>window.APP_CURRENCY = @json($layoutMoney->toJs());</script>
        @include('partials.pwa')
    @endunless
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="font-inter text-sm leading-[1.45] antialiased bg-surface-page text-ink dark:bg-night-page dark:text-night-ink" x-data="{ open: false }">

<div class="flex min-h-screen">

    {{-- ══════════ SIDEBAR (colour: Settings → Sidebar colour) ══════════ --}}
    <aside class="shell-side fixed inset-y-0 left-0 z-50 flex w-[240px] -translate-x-full flex-col gap-7 overflow-y-auto px-4 py-7 text-white transition-transform duration-300
                  md:sticky md:top-0 md:h-screen md:flex-shrink-0 md:translate-x-0"
           style="background-color: {{ $sidebar }}" :class="open && '!translate-x-0'">

        <div class="flex items-start justify-between px-3">
            <a href="{{ route($admin ? 'admin.dashboard' : 'dashboard') }}" class="text-white no-underline">
                <p class="font-display text-xl font-semibold leading-[1.2]">Expense Tracker</p>
                <p class="adm-eyebrow mt-1" style="color: {{ $eyebrow }}">{{ $section }}</p>
            </a>
            <button type="button" @click="open = false" class="md:hidden -mr-2 flex h-11 w-11 items-center justify-center rounded-chip hover:bg-white/10" aria-label="Close menu">
                <x-icon name="x" />
            </button>
        </div>

        {{-- Admins: one login, two views — running the app, and their own expenses. --}}
        @if($user->isAdmin())
            <nav class="grid grid-cols-2 gap-1 rounded-card bg-white/[.12] p-1" aria-label="Switch view">
                @foreach([['admin', 'Admin', 'admin.dashboard'], ['account', 'My account', 'dashboard']] as [$key, $label, $route])
                    @if($area === $key)
                        <span aria-current="page" class="flex h-10 items-center justify-center rounded-[9px] bg-white text-sm font-semibold" style="color: {{ $sidebar }}">{{ $label }}</span>
                    @else
                        <a href="{{ route($route) }}" class="flex h-10 items-center justify-center rounded-[9px] text-sm font-semibold text-white hover:bg-white/[.08]">{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
        @endif

        <nav class="flex flex-col gap-1" aria-label="{{ $section }}">
            @foreach($nav as [$route, $label, $icon, $patterns])
                @if($route)
                    @php $active = request()->routeIs(...$patterns); @endphp
                    <a href="{{ route($route) }}" @click="open = false" @if($active) aria-current="page" @endif
                       class="shell-link {{ $active ? 'bg-white/[.14] font-semibold' : 'hover:bg-white/[.08]' }}">
                        <x-icon :name="$icon" class="h-5 w-5 flex-shrink-0" />
                        <span class="flex-1">{{ $label }}</span>
                        @if($route === 'notifications.index' && $alerts > 0)
                            <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-status-error px-1.5 text-xs font-semibold text-white">{{ $alerts }}</span>
                        @endif
                    </a>
                @else
                    <span aria-disabled="true" class="shell-link cursor-not-allowed text-white/75">
                        <x-icon :name="$icon" class="h-5 w-5 flex-shrink-0" />
                        <span class="flex-1">{{ $label }}</span>
                        <span class="rounded-full bg-white/[.12] px-2 py-0.5 text-xs font-semibold text-white/80">Soon</span>
                    </span>
                @endif
            @endforeach
        </nav>

        {{-- The admin links stay in My account too; the routes check again (`admin` middleware). --}}
        @if(! $admin && $user->isAdmin())
            <div class="flex flex-col gap-1">
                <p class="adm-eyebrow mb-1 px-3 text-white/80">Admin</p>
                @foreach([['admin.dashboard', 'Dashboard', 'chart'], ['admin.notifications.index', 'Send notification', 'megaphone'], ['admin.users.index', 'Users', 'users']] as [$route, $label, $icon])
                    <a href="{{ route($route) }}" class="shell-link hover:bg-white/[.08]">
                        <x-icon :name="$icon" class="h-5 w-5 flex-shrink-0" /> {{ $label }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-auto flex flex-col gap-3 border-t border-white/20 pt-5">
            @if($admin)
                <a href="{{ route('dashboard') }}" class="shell-link hover:bg-white/[.08]">
                    <x-icon name="wallet" class="h-5 w-5" /> My own expenses
                </a>
            @endif
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-chip px-3 py-2 text-white no-underline hover:bg-white/[.08]">
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="" class="h-9 w-9 flex-shrink-0 rounded-full object-cover">
                @else
                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-brand-cyan text-sm font-semibold text-brand-navy">{{ $initials ?: '?' }}</span>
                @endif
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold">{{ $user->name ?: 'Add your name' }}</span>
                    <span class="block truncate text-[13px] text-white/80">{{ $admin ? 'Admin · also a user' : ($user->email ?: 'No email set') }}</span>
                </span>
            </a>
            <form method="POST" action="{{ route('logout') }}"
                  data-confirm-title="Sign out?"
                  data-confirm-message="You will need your password to sign back in. Every expense, income and category stays safely in your account."
                  data-confirm-button="Sign out">
                @csrf
                <button type="submit" class="shell-link w-full hover:bg-white/[.08]">
                    <x-icon name="logout" class="h-5 w-5" /> Sign out
                </button>
            </form>
        </div>
    </aside>

    <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 z-40 bg-black/50 md:hidden"></div>

    <div class="flex min-w-0 flex-1 flex-col">

        {{-- ══════════ TOP BAR ══════════ --}}
        <header class="sticky top-0 z-30 flex h-16 flex-shrink-0 items-center gap-3 border-b border-surface-line bg-surface-card px-4 md:px-10 dark:border-night-line dark:bg-night-card">
            <button type="button" @click="open = true" class="hdr-btn -ml-2 md:hidden" aria-label="Open menu">
                <x-icon name="menu" />
            </button>

            <div class="min-w-0">
                <p class="truncate text-sm">
                    <span class="muted">{{ $section }}</span>
                    <span class="muted" aria-hidden="true">/</span>
                    <span class="font-semibold strong">{{ $pageTitle }}</span>
                </p>
                <p class="hidden text-[13px] muted sm:block">{{ \App\Support\Labels::now()->format('l, j F Y') }}</p>
            </div>

            <div class="ml-auto flex items-center gap-1 sm:gap-2">
                @if($admin)
                    <a href="{{ route('admin.users.index') }}" class="hdr-btn" aria-label="Find a user"><x-icon name="search" /></a>
                    <a href="{{ route('admin.activity.index') }}" class="hdr-btn" aria-label="Activity log"><x-icon name="list" /></a>
                    <a href="{{ route('admin.notifications.index') }}" class="btn-primary !min-h-11 hidden sm:inline-flex">
                        <x-icon name="megaphone" class="h-5 w-5" /> Send notification
                    </a>
                @else
                    <a href="{{ route('transactions.index', ['find' => 1]) }}" class="hdr-btn" aria-label="Search transactions"><x-icon name="search" /></a>
                    <a href="{{ route('notifications.index') }}" class="hdr-btn relative"
                       aria-label="Notifications{{ $alerts ? ', ' . $alerts . ' budget ' . Str::plural('alert', $alerts) : '' }}">
                        <x-icon name="bell" />
                        @if($alerts > 0)
                            <span class="absolute right-2.5 top-2.5 h-2.5 w-2.5 rounded-full bg-status-error ring-2 ring-surface-card dark:ring-night-card"></span>
                        @endif
                    </a>
                    <a href="{{ route('expenses.create') }}" class="btn-primary !min-h-11 hidden sm:inline-flex">
                        <x-icon name="plus" class="h-5 w-5" /> Add expense
                    </a>
                @endif
                <a href="{{ route('profile.edit') }}" class="ml-1 flex-shrink-0 rounded-full" aria-label="Account information">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="" class="h-9 w-9 rounded-full object-cover">
                    @else
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-cyan text-sm font-semibold text-brand-navy">{{ $initials ?: '?' }}</span>
                    @endif
                </a>
            </div>
        </header>

        {{-- ══════════ PAGE ══════════ --}}
        <main class="flex min-w-0 flex-1 flex-col gap-6 px-4 pt-6 md:px-10 md:pb-12 md:pt-8 {{ $admin ? 'pb-12' : 'pb-24' }}">
            @if(session('success'))
                <div class="adm-strip" role="status">
                    <x-icon name="check" class="mt-0.5 h-5 w-5 flex-shrink-0 text-status-successText dark:text-emerald-400" />
                    <p class="flex-1">
                        <span class="font-semibold text-status-successText dark:text-emerald-400">Done.</span> {{ session('success') }}
                        @if(session('success_link'))
                            <a href="{{ session('success_link') }}" class="link ml-1">View</a>
                        @endif
                    </p>
                </div>
            @endif
            @if(session('error'))
                <div class="adm-strip" role="alert">
                    <x-icon name="warning" class="mt-0.5 h-5 w-5 flex-shrink-0 text-status-error" />
                    <p><span class="font-semibold text-status-error">Heads up.</span> {{ session('error') }}</p>
                </div>
            @endif

            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="adm-h1">{{ $heading ?? $pageTitle }}</h1>
                    @isset($subtitle)
                        <p class="mt-1 text-sm muted">{{ $subtitle }}</p>
                    @endisset
                </div>
                @isset($actions)
                    <div class="flex flex-wrap items-center gap-3">{{ $actions }}</div>
                @endisset
            </div>

            {{ $slot }}
        </main>
    </div>
</div>

{{-- ══════════ PHONE TABS (My account) ══════════ --}}
@if($mobileTabs)
    <nav class="fixed inset-x-0 bottom-0 z-40 flex h-16 items-center justify-around border-t border-surface-line bg-surface-card px-2 md:hidden dark:border-night-line dark:bg-night-card" aria-label="Tabs">
        @foreach($mobileTabs as $i => [$route, $label, $icon, $patterns])
            @if($i === 2)
                <a href="{{ route('expenses.create') }}" aria-label="Add expense"
                   class="-mt-6 flex h-12 w-12 items-center justify-center rounded-full bg-brand-navy text-white ring-4 ring-surface-page dark:bg-brand-cyan dark:text-brand-navy dark:ring-night-page">
                    <x-icon name="plus" class="h-6 w-6" />
                </a>
            @endif
            @php $active = request()->routeIs(...$patterns); @endphp
            <a href="{{ route($route) }}" @if($active) aria-current="page" @endif
               class="flex flex-1 flex-col items-center gap-0.5 px-2 py-1.5 text-[11px] {{ $active ? 'font-semibold text-brand-navy dark:text-brand-cyan' : 'text-ink-muted dark:text-night-muted' }}">
                <x-icon :name="$icon" />
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
@endif

{{-- ══════════ CONFIRM DIALOG (see resources/js/app.js) ══════════ --}}
<div x-data="confirmDialog" @confirm-submit.window="ask($event.detail)" @keydown.escape.window="open = false">
    <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" @click.self="open = false">
        <div class="card w-full max-w-sm border border-surface-line dark:border-night-line" role="alertdialog" aria-modal="true" :aria-label="title">
            <h2 class="card-title" x-text="title"></h2>
            <p class="mt-2 text-sm muted" x-text="message" x-show="message"></p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn-secondary !min-h-11" @click="open = false">Cancel</button>
                {{-- Admin confirms are role and send actions; My account's are deletions. --}}
                <button type="button" class="{{ $admin ? 'btn-primary' : 'btn-danger' }} !min-h-11" @click="confirm()" x-text="button"></button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
