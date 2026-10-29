@php
    $user = Auth::user();
    $theme = $user->theme_mode ?: 'light';
    $words = preg_split('/\s+/', trim($user->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    $initials = count($words) > 1
        ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1))
        : mb_strtoupper(mb_substr($words[0] ?? '', 0, 2));

    // The app's tabs and screens, in its order. [route, label, icon, active patterns]
    $nav = [
        ['dashboard', 'Home', 'home', ['dashboard']],
        ['transactions.index', 'Transactions', 'list', ['transactions.*', 'expenses.*', 'incomes.*']],
        ['analytics.index', 'Analytics', 'chart', ['analytics.*']],
        ['budgets.index', 'Budgets', 'wallet', ['budgets.*']],
        ['categories.index', 'Categories', 'tag', ['categories.*']],
        ['notifications.index', 'Notifications', 'bell', ['notifications.*']],
        ['settings.index', 'Settings', 'cog', ['settings.*', 'currency.*', 'about', 'profile.*', 'account.*']],
    ];
    $mobileTabs = [$nav[0], $nav[1], $nav[2], $nav[6]];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Home' }} – Smart Paisa</title>
    @if($theme === 'system')
        <script>if (matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');</script>
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>window.APP_CURRENCY = @json($layoutMoney->toJs());</script>
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak]{display:none!important}
        .sp-sidebar{background:linear-gradient(160deg,#0d1117 0%,#161d2e 60%,#1a2540 100%)}
        .sp-link{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;font-size:13.5px;font-weight:500;color:#94a3b8;transition:background .15s,color .15s;text-decoration:none;white-space:nowrap}
        .sp-link:hover{background:rgba(255,255,255,.07);color:#e2e8f0}
        .sp-link.active{background:rgba(99,102,241,.18);color:#c7d2fe}
        .sp-label{padding:14px 12px 4px;font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#475569;margin:0}
        .no-scroll::-webkit-scrollbar{display:none}
        .no-scroll{-ms-overflow-style:none;scrollbar-width:none}
    </style>
</head>
<body class="font-sans antialiased bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-300" x-data="{ open: false }">

<div class="flex min-h-screen">

    {{-- ══════════ SIDEBAR ══════════ --}}
    <aside class="sp-sidebar fixed inset-y-0 left-0 z-50 w-64 flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0"
           :class="open ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0'">

        <div class="flex items-center gap-3 px-4 py-[15px] border-b border-white/[.07] flex-shrink-0">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-lg text-white flex-shrink-0"
                 style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">₹</div>
            <div class="leading-tight">
                <div class="font-bold text-white text-[15px]">Smart Paisa</div>
                <div class="text-[11px] text-slate-500">Expense Tracker</div>
            </div>
            <button @click="open=false" class="ml-auto lg:hidden text-slate-500 hover:text-slate-300 p-1" aria-label="Close menu">
                <x-icon name="x" />
            </button>
        </div>

        {{-- Admins: one login, two views — running the app, and their own expenses. --}}
        @if($user->isAdmin())
            <nav class="mx-3 mt-3 grid grid-cols-2 gap-1 rounded-xl p-1 flex-shrink-0" style="background:rgba(255,255,255,.08)" aria-label="Switch view">
                <a href="{{ route('admin.dashboard') }}" class="flex h-10 items-center justify-center rounded-[9px] text-sm font-semibold text-white hover:bg-white/[.08]">Admin</a>
                <span aria-current="page" class="flex h-10 items-center justify-center rounded-[9px] bg-white text-sm font-semibold" style="color:#0d1117">My account</span>
            </nav>
        @endif

        <nav class="flex-1 no-scroll overflow-y-auto py-3 px-2 space-y-0.5">
            @foreach($nav as [$route, $label, $icon, $patterns])
                <a href="{{ route($route) }}" @click="open=false"
                   class="sp-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}">
                    <x-icon :name="$icon" class="w-5 h-5" />
                    {{ $label }}
                    @if($route === 'notifications.index' && $overBudget > 0)
                        <span class="ml-auto min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-[11px] font-bold flex items-center justify-center">{{ $overBudget }}</span>
                    @endif
                </a>
            @endforeach

            {{-- Admin — only admins see it; the routes check again (`admin` middleware). --}}
            @if($user->isAdmin())
                <p class="sp-label">Admin</p>

                <a href="{{ route('admin.dashboard') }}" @click="open=false"
                   class="sp-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <x-icon name="chart" />
                    Dashboard
                </a>

                <a href="{{ route('admin.notifications.index') }}" @click="open=false"
                   class="sp-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">
                    <x-icon name="megaphone" />
                    Send Notification
                </a>

                <a href="{{ route('admin.users.index') }}" @click="open=false"
                   class="sp-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <x-icon name="users" />
                    Users
                </a>
            @endif
        </nav>

        {{-- Account, as the app's account drawer --}}
        <div class="flex-shrink-0 p-3 border-t border-white/[.07]" x-data="{ uo: false }">
            <div x-show="uo" x-cloak x-transition.opacity class="mb-1 space-y-0.5">
                <a href="{{ route('profile.edit') }}" class="sp-link"><x-icon name="user" class="w-4 h-4" />Account information</a>
                <a href="{{ route('account.password') }}" class="sp-link"><x-icon name="lock" class="w-4 h-4" />{{ $user->has_password ? 'Change password' : 'Set a password' }}</a>
                <form method="POST" action="{{ route('logout') }}"
                      data-confirm-title="Sign out?"
                      data-confirm-message="You will need your password to sign back in. Every expense, income and category stays safely in your account."
                      data-confirm-button="Sign out">
                    @csrf
                    <button type="submit" class="sp-link w-full" style="color:#f87171"><x-icon name="logout" class="w-4 h-4" />Sign out</button>
                </form>
            </div>
            <button @click="uo=!uo" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/[.05] transition-colors">
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                @else
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                         style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">{{ $initials ?: '?' }}</div>
                @endif
                <div class="flex-1 text-left min-w-0">
                    <p class="text-[13px] font-semibold text-slate-200 truncate leading-tight">{{ $user->name ?: 'Add your name' }}</p>
                    <p class="text-[11px] text-slate-500 truncate leading-tight">{{ $user->email ?: 'No email set' }}</p>
                </div>
                <x-icon name="chevron-down" class="w-3.5 h-3.5 text-slate-500 transition-transform" ::class="uo ? '' : 'rotate-180'" />
            </button>
        </div>
    </aside>

    <div x-show="open" @click="open=false" x-cloak class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden"></div>

    {{-- ══════════ MAIN ══════════ --}}
    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen min-w-0">

        <header class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 sticky top-0 z-30 shadow-sm">
            <div class="flex items-center gap-2 px-4 h-14">
                <button @click="open=true" class="lg:hidden flex items-center justify-center w-9 h-9 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 flex-shrink-0" aria-label="Open menu">
                    <x-icon name="menu" />
                </button>

                <div class="min-w-0">
                    <h1 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100 leading-tight truncate">{{ $title ?? 'Home' }}</h1>
                    <p class="hidden sm:block text-[11px] text-slate-400">{{ \App\Support\Labels::now()->format('l, d M Y') }}</p>
                </div>

                <div class="ml-auto flex items-center gap-1 sm:gap-2">
                    <a href="{{ route('transactions.index', ['find' => 1]) }}" aria-label="Search transactions"
                       class="flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                        <x-icon name="search" />
                    </a>
                    <a href="{{ route('notifications.index') }}"
                       aria-label="Notifications{{ $overBudget ? ', ' . $overBudget . ' budget ' . Str::plural('alert', $overBudget) : '' }}"
                       class="relative flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                        <x-icon name="bell" />
                        @if($overBudget > 0)
                            <span class="absolute top-1.5 right-2 w-2.5 h-2.5 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-800"></span>
                        @endif
                    </a>
                    <a href="{{ route('expenses.create') }}" class="hidden sm:inline-flex btn-primary !py-2 !text-xs">
                        <x-icon name="plus" class="w-4 h-4" /> Add Expense
                    </a>
                    <a href="{{ route('profile.edit') }}" aria-label="Account information" class="flex-shrink-0">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="" class="w-8 h-8 rounded-full object-cover">
                        @else
                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold"
                                  style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">{{ $initials ?: '?' }}</span>
                        @endif
                    </a>
                </div>
            </div>
        </header>

        @if(session('success') || session('error'))
            <div class="px-4 pt-4 max-w-6xl w-full mx-auto" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition.opacity>
                @if(session('success'))
                    <div class="flex items-center gap-3 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 px-4 py-3 rounded-xl text-sm" role="status">
                        <x-icon name="check" class="w-4 h-4 flex-shrink-0" />
                        <span class="flex-1">{{ session('success') }}</span>
                        @if(session('success_link'))
                            <a href="{{ session('success_link') }}" class="font-semibold underline">View</a>
                        @endif
                    </div>
                @endif
                @if(session('error'))
                    <div class="flex items-center gap-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-800 dark:text-red-200 px-4 py-3 rounded-xl text-sm" role="alert">
                        <x-icon name="warning" class="w-4 h-4 flex-shrink-0" />
                        {{ session('error') }}
                    </div>
                @endif
            </div>
        @endif

        <main class="flex-1 p-4 pb-24 lg:pb-8 w-full max-w-6xl mx-auto">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- ══════════ MOBILE TABS ══════════ --}}
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 flex items-center justify-around px-2 h-16 shadow-lg">
    @foreach($mobileTabs as $i => [$route, $label, $icon, $patterns])
        @if($i === 2)
            <a href="{{ route('expenses.create') }}" aria-label="Add expense"
               class="-mt-6 w-12 h-12 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-lg ring-4 ring-slate-100 dark:ring-slate-900">
                <x-icon name="plus" class="w-6 h-6" />
            </a>
        @endif
        <a href="{{ route($route) }}"
           class="flex flex-col items-center gap-0.5 px-2 py-1.5 text-[10px] font-medium flex-1 {{ request()->routeIs(...$patterns) ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400' }}">
            <x-icon :name="$icon" />
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>

{{-- ══════════ CONFIRM DIALOG (see resources/js/app.js) ══════════ --}}
<div x-data="confirmDialog" @confirm-submit.window="ask($event.detail)" @keydown.escape.window="open = false">
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-4" @click.self="open = false">
        <div class="card w-full max-w-sm shadow-xl" role="alertdialog" aria-modal="true" :aria-label="title">
            <h2 class="text-base font-semibold strong" x-text="title"></h2>
            <p class="mt-2 text-sm muted" x-text="message" x-show="message"></p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="open = false">Cancel</button>
                <button type="button" class="btn-danger" @click="confirm()" x-text="button"></button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
