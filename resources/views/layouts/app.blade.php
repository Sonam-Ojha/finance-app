<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} – Smart Paisa</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak]{display:none!important}
        body{background:#f1f5f9}

        /* ── Sidebar base ── */
        .sp-sidebar{background:linear-gradient(160deg,#0d1117 0%,#161d2e 60%,#1a2540 100%)}

        /* ── Nav items ── */
        .sp-link{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;font-size:13px;font-weight:500;color:#94a3b8;transition:background .15s,color .15s;text-decoration:none;white-space:nowrap}
        .sp-link:hover{background:rgba(255,255,255,.07);color:#e2e8f0}
        .sp-link.active{background:rgba(99,102,241,.18);color:#a5b4fc}

        .sp-group{width:100%;display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-radius:10px;font-size:13px;font-weight:500;color:#94a3b8;transition:background .15s,color .15s;background:none;border:none;cursor:pointer}
        .sp-group:hover{background:rgba(255,255,255,.07);color:#e2e8f0}
        .sp-group.active{color:#a5b4fc}

        .sp-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;font-size:13px}

        .sp-children{margin:3px 0 3px 14px;padding-left:14px;border-left:1px solid rgba(255,255,255,.07)}
        .sp-child{display:flex;align-items:center;gap:9px;padding:7px 10px;border-radius:8px;font-size:12.5px;color:#64748b;transition:background .15s,color .15s;text-decoration:none;width:100%;background:none;border:none;cursor:pointer}
        .sp-child:hover{background:rgba(255,255,255,.06);color:#cbd5e1}
        .sp-child.active{color:#818cf8;background:rgba(99,102,241,.1)}
        .sp-dot{width:5px;height:5px;border-radius:50%;background:currentColor;flex-shrink:0;opacity:.5}
        .sp-child.active .sp-dot{opacity:1}

        .sp-label{padding:12px 12px 4px;font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#334155;margin:0}

        /* ── Scrollbar ── */
        .no-scroll::-webkit-scrollbar{display:none}
        .no-scroll{-ms-overflow-style:none;scrollbar-width:none}

        /* ── Table wrapper for mobile ── */
        .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
    </style>
</head>
<body class="font-sans antialiased">

<div class="flex min-h-screen" x-data="{ open: false }">

    {{-- ══════════ SIDEBAR ══════════ --}}
    <aside class="sp-sidebar fixed inset-y-0 left-0 z-50 w-64 flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0"
           :class="open ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0'">

        {{-- Logo --}}
        <div class="flex items-center gap-3 px-4 py-[15px] border-b border-white/[.07] flex-shrink-0">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-lg text-white flex-shrink-0"
                 style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">₹</div>
            <div class="leading-tight">
                <div class="font-bold text-white text-[15px]">Smart Paisa</div>
                <div class="text-[11px] text-slate-500">Personal Finance</div>
            </div>
            {{-- Mobile close --}}
            <button @click="open=false" class="ml-auto lg:hidden text-slate-500 hover:text-slate-300 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 no-scroll overflow-y-auto py-3 px-2 space-y-0.5"
             x-data="{m:{
                income:   {{ request()->routeIs('income.*','commission.*','cashback.*') ? 'true':'false' }},
                expense:  {{ request()->routeIs('expense.*') ? 'true':'false' }},
                accounts: {{ request()->routeIs('bank.*','credit-card.*') ? 'true':'false' }},
                loans:    {{ request()->routeIs('loan.*','investment.*') ? 'true':'false' }},
                tracking: {{ request()->routeIs('bad-debt.*','contact.*','reminder.*') ? 'true':'false' }}
             }}">

            <a href="{{ route('dashboard') }}" @click="open=false"
               class="sp-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="sp-icon" style="background:#6366f1">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 6a1 1 0 011-1h5a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zm9 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                </span>
                Dashboard
            </a>

            <p class="sp-label">Finance</p>

            {{-- Income --}}
            <div>
                <button @click="m.income=!m.income"
                        class="sp-group {{ request()->routeIs('income.*','commission.*','cashback.*') ? 'active' : '' }}">
                    <span class="flex items-center gap-2.5">
                        <span class="sp-icon" style="background:#10b981">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>
                        </span>
                        Income
                    </span>
                    <svg class="w-3 h-3 text-slate-500 transition-transform duration-200 flex-shrink-0" :class="m.income?'rotate-180':''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="m.income" x-cloak
                     x-transition:enter="transition-all duration-200 ease-out"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="sp-children">
                    <a href="{{ route('income.index') }}" @click="open=false" class="sp-child {{ request()->routeIs('income.*') ? 'active' : '' }}"><span class="sp-dot"></span>Income List</a>
                    <a href="{{ route('commission.index') }}" @click="open=false" class="sp-child {{ request()->routeIs('commission.*') ? 'active' : '' }}"><span class="sp-dot"></span>Commission</a>
                    <a href="{{ route('cashback.index') }}" @click="open=false" class="sp-child {{ request()->routeIs('cashback.*') ? 'active' : '' }}"><span class="sp-dot"></span>Cashback</a>
                </div>
            </div>

            {{-- Expenses --}}
            <div>
                <button @click="m.expense=!m.expense"
                        class="sp-group {{ request()->routeIs('expense.*') ? 'active' : '' }}">
                    <span class="flex items-center gap-2.5">
                        <span class="sp-icon" style="background:#ef4444">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3z"/><path d="M16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
                        </span>
                        Expenses
                    </span>
                    <svg class="w-3 h-3 text-slate-500 transition-transform duration-200 flex-shrink-0" :class="m.expense?'rotate-180':''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="m.expense" x-cloak
                     x-transition:enter="transition-all duration-200 ease-out"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="sp-children">
                    <a href="{{ route('expense.index') }}" @click="open=false" class="sp-child {{ request()->routeIs('expense.index','expense.create','expense.edit','expense.show') ? 'active' : '' }}"><span class="sp-dot"></span>All Expenses</a>
                    <a href="{{ route('expense.categories') }}" @click="open=false" class="sp-child {{ request()->routeIs('expense.categories*') ? 'active' : '' }}"><span class="sp-dot"></span>Categories</a>
                </div>
            </div>

            <p class="sp-label">Accounts</p>

            {{-- Accounts --}}
            <div>
                <button @click="m.accounts=!m.accounts"
                        class="sp-group {{ request()->routeIs('bank.*','credit-card.*') ? 'active' : '' }}">
                    <span class="flex items-center gap-2.5">
                        <span class="sp-icon" style="background:#3b82f6">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                        </span>
                        Accounts
                    </span>
                    <svg class="w-3 h-3 text-slate-500 transition-transform duration-200 flex-shrink-0" :class="m.accounts?'rotate-180':''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="m.accounts" x-cloak
                     x-transition:enter="transition-all duration-200 ease-out"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="sp-children">
                    <a href="{{ route('bank.index') }}" @click="open=false" class="sp-child {{ request()->routeIs('bank.*') ? 'active' : '' }}"><span class="sp-dot"></span>Bank Accounts</a>
                    <a href="{{ route('credit-card.index') }}" @click="open=false" class="sp-child {{ request()->routeIs('credit-card.*') ? 'active' : '' }}"><span class="sp-dot"></span>Credit Cards</a>
                </div>
            </div>

            {{-- Loans & Invest --}}
            <div>
                <button @click="m.loans=!m.loans"
                        class="sp-group {{ request()->routeIs('loan.*','investment.*') ? 'active' : '' }}">
                    <span class="flex items-center gap-2.5">
                        <span class="sp-icon" style="background:#f59e0b">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                        </span>
                        Loans & Invest
                    </span>
                    <svg class="w-3 h-3 text-slate-500 transition-transform duration-200 flex-shrink-0" :class="m.loans?'rotate-180':''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="m.loans" x-cloak
                     x-transition:enter="transition-all duration-200 ease-out"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="sp-children">
                    <a href="{{ route('loan.index') }}" @click="open=false" class="sp-child {{ request()->routeIs('loan.*') ? 'active' : '' }}"><span class="sp-dot"></span>Loans / EMI</a>
                    <a href="{{ route('investment.index') }}" @click="open=false" class="sp-child {{ request()->routeIs('investment.*') ? 'active' : '' }}"><span class="sp-dot"></span>Investments</a>
                </div>
            </div>

            <p class="sp-label">Tracking</p>

            {{-- Tracking --}}
            <div>
                <button @click="m.tracking=!m.tracking"
                        class="sp-group {{ request()->routeIs('bad-debt.*','contact.*','reminder.*') ? 'active' : '' }}">
                    <span class="flex items-center gap-2.5">
                        <span class="sp-icon" style="background:#8b5cf6">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11 4a1 1 0 10-2 0v4a1 1 0 102 0V7zm-3 1a1 1 0 10-2 0v3a1 1 0 102 0V8zM8 9a1 1 0 00-2 0v2a1 1 0 102 0V9z" clip-rule="evenodd"/></svg>
                        </span>
                        Tracking
                    </span>
                    <svg class="w-3 h-3 text-slate-500 transition-transform duration-200 flex-shrink-0" :class="m.tracking?'rotate-180':''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="m.tracking" x-cloak
                     x-transition:enter="transition-all duration-200 ease-out"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="sp-children">
                    <a href="{{ route('bad-debt.index') }}" @click="open=false" class="sp-child {{ request()->routeIs('bad-debt.*') ? 'active' : '' }}"><span class="sp-dot"></span>Pending Money</a>
                    <a href="{{ route('contact.index') }}" @click="open=false" class="sp-child {{ request()->routeIs('contact.*') ? 'active' : '' }}"><span class="sp-dot"></span>Contact Ledger</a>
                    <a href="{{ route('reminder.index') }}" @click="open=false" class="sp-child {{ request()->routeIs('reminder.*') ? 'active' : '' }}"><span class="sp-dot"></span>Reminders</a>
                </div>
            </div>

            {{-- Reports --}}
            <a href="{{ route('report.index') }}" @click="open=false"
               class="sp-link {{ request()->routeIs('report.*') ? 'active' : '' }}">
                <span class="sp-icon" style="background:#0ea5e9">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 10a1 1 0 10-2 0v3a1 1 0 102 0v-3zm4-1a1 1 0 011 1v3a1 1 0 11-2 0v-3a1 1 0 011-1zm-2-5a1 1 0 10-2 0v1a1 1 0 102 0V6z" clip-rule="evenodd"/></svg>
                </span>
                Reports
            </a>

        </nav>

        {{-- User footer --}}
        <div class="flex-shrink-0 p-3 border-t border-white/[.07]">
            <div x-data="{ uo: {{ request()->routeIs('profile.*') ? 'true':'false' }} }">
                <button @click="uo=!uo"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/[.05] transition-colors">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                         style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
                        {{ strtoupper(substr(Auth::user()->name,0,1)) }}
                    </div>
                    <div class="flex-1 text-left min-w-0">
                        <p class="text-[13px] font-semibold text-slate-200 truncate leading-tight">{{ Auth::user()->name }}</p>
                        <p class="text-[11px] text-slate-500 truncate leading-tight">{{ Auth::user()->email }}</p>
                    </div>
                    <svg class="w-3.5 h-3.5 text-slate-500 transition-transform flex-shrink-0" :class="uo?'rotate-180':''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="uo" x-cloak x-transition:enter="transition-all duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="mt-1 space-y-0.5 px-1">
                    <a href="{{ route('profile.edit') }}" @click="open=false" class="sp-child {{ request()->routeIs('profile.*') ? 'active':'' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Profile Settings
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="sp-child w-full text-left" style="color:#f87171">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </aside>

    {{-- Mobile overlay --}}
    <div x-show="open" @click="open=false" x-cloak
         class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden"></div>

    {{-- ══════════ MAIN ══════════ --}}
    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen">

        {{-- Topbar --}}
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="flex items-center gap-3 px-4 h-14">
                {{-- Hamburger --}}
                <button @click="open=true"
                        class="lg:hidden flex items-center justify-center w-9 h-9 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                {{-- Brand on mobile --}}
                <div class="lg:hidden flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                         style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">₹</div>
                    <span class="font-bold text-slate-700 text-sm">Smart Paisa</span>
                </div>

                {{-- Page title (desktop) --}}
                <div class="hidden lg:block">
                    <h1 class="text-[15px] font-semibold text-slate-800 leading-tight">{{ $title ?? 'Dashboard' }}</h1>
                    <p class="text-[11px] text-slate-400">{{ now()->format('l, d M Y') }}</p>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    {{-- Notification bell --}}
                    <a href="{{ route('reminder.index') }}"
                       class="flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </a>
                    {{-- Quick add --}}
                    <a href="{{ route('income.create') }}"
                       class="hidden sm:flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-3 py-2 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        Add Income
                    </a>
                    {{-- Avatar --}}
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                         style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
                        {{ strtoupper(substr(Auth::user()->name,0,1)) }}
                    </div>
                </div>
            </div>
        </header>

        {{-- Flash messages --}}
        @if(session('success') || session('error') || $errors->any())
        <div class="px-4 pt-4 space-y-2">
            @if(session('success'))
            <div class="flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm">
                <svg class="w-4 h-4 text-emerald-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ session('success') }}
            </div>
            @endif
            @if(session('error'))
            <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
                <svg class="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                {{ session('error') }}
            </div>
            @endif
            @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
            @endif
        </div>
        @endif

        {{-- Page content --}}
        <main class="flex-1 p-4 pb-20 lg:pb-6">
            {{ $slot }}
        </main>

    </div>

</div>

{{-- ══════════ MOBILE BOTTOM NAV ══════════ --}}
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-slate-200 flex items-center justify-around px-2 h-16 shadow-lg">
    <a href="{{ route('dashboard') }}" class="mob-nav {{ request()->routeIs('dashboard') ? 'mob-nav-active' : '' }}">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 6a1 1 0 011-1h5a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zm9 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
        <span>Home</span>
    </a>
    <a href="{{ route('income.index') }}" class="mob-nav {{ request()->routeIs('income.*','commission.*','cashback.*') ? 'mob-nav-active' : '' }}">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>
        <span>Income</span>
    </a>
    <a href="{{ route('expense.index') }}" class="mob-nav {{ request()->routeIs('expense.*') ? 'mob-nav-active' : '' }}">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3z"/></svg>
        <span>Expense</span>
    </a>
    <a href="{{ route('bank.index') }}" class="mob-nav {{ request()->routeIs('bank.*','credit-card.*') ? 'mob-nav-active' : '' }}">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
        <span>Accounts</span>
    </a>
    <button onclick="document.querySelector('aside').style.transform='translateX(0)'; document.querySelector('[\\@click=\\'open=true\\']') && null" @click="open=true" class="mob-nav">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        <span>More</span>
    </button>
</nav>

<style>
.mob-nav{display:flex;flex-direction:column;align-items:center;gap:2px;padding:6px 8px;border-radius:10px;font-size:10px;font-weight:500;color:#94a3b8;transition:color .15s;text-decoration:none;flex:1}
.mob-nav:hover,.mob-nav-active{color:#6366f1}
</style>

</body>
</html>
