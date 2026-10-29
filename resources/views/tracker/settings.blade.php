<x-app-layout>
    <x-slot name="title">Settings</x-slot>
    @php
        $row = 'flex items-center gap-3 px-4 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors';
        $iconBox = 'w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0';
    @endphp

    <div class="max-w-2xl mx-auto space-y-5">
        <section>
            <h3 class="section-label mb-2 px-1">Preferences</h3>
            <div class="card !p-0 divide-soft overflow-hidden">
                @foreach([
                    [route('settings.appearance'), 'Appearance', $theme, $user->theme_mode === 'dark' ? 'moon' : 'sun', 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300'],
                    [route('currency.index'), 'Currency', $currency, 'money', 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300'],
                    [route('categories.index'), 'Categories', $categories, 'tag', 'bg-purple-100 text-purple-600 dark:bg-purple-500/15 dark:text-purple-300'],
                    [route('budgets.index'), 'Budgets', $budgets ? "{$budgets} set" : 'None', 'wallet', 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300'],
                ] as [$href, $label, $value, $icon, $tone])
                    <a href="{{ $href }}" class="{{ $row }}">
                        <span class="{{ $iconBox }} {{ $tone }}"><x-icon :name="$icon" /></span>
                        <span class="flex-1 font-medium strong">{{ $label }}</span>
                        <span class="text-sm muted">{{ $value }}</span>
                        <x-icon name="chevron-right" class="w-4 h-4 muted" />
                    </a>
                @endforeach
            </div>
        </section>

        <section>
            <h3 class="section-label mb-2 px-1">More</h3>
            <div class="card !p-0 divide-soft overflow-hidden">
                <form method="POST" action="{{ route('settings.email') }}" class="flex items-center gap-3 px-4 py-3.5">
                    @csrf @method('PUT')
                    @php $on = $user->email_notifications ?? true; @endphp
                    <input type="hidden" name="enabled" value="{{ $on ? 0 : 1 }}">
                    <span class="{{ $iconBox }} bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300"><x-icon name="envelope" /></span>
                    <span class="flex-1 min-w-0">
                        <span class="block font-medium strong">Email notifications</span>
                        <span class="block text-xs muted">An email each time you add an expense, income, category or budget.</span>
                    </span>
                    <button type="submit" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="Email notifications"
                            class="relative w-11 h-6 rounded-full flex-shrink-0 transition-colors {{ $on ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600' }}">
                        <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow transition-all {{ $on ? 'left-[22px]' : 'left-0.5' }}"></span>
                    </button>
                </form>
                <a href="{{ route('notifications.index') }}" class="{{ $row }}">
                    <span class="{{ $iconBox }} bg-pink-100 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300"><x-icon name="bell" /></span>
                    <span class="flex-1 font-medium strong">Notifications</span>
                    <x-icon name="chevron-right" class="w-4 h-4 muted" />
                </a>
                <a href="{{ route('about') }}" class="{{ $row }}">
                    <span class="{{ $iconBox }} bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300"><x-icon name="info" /></span>
                    <span class="flex-1 font-medium strong">About</span>
                    <x-icon name="chevron-right" class="w-4 h-4 muted" />
                </a>
            </div>
        </section>

        <section>
            <h3 class="section-label mb-2 px-1">Account</h3>
            <div class="card !p-0 divide-soft overflow-hidden">
                <a href="{{ route('profile.edit') }}" class="{{ $row }}">
                    <span class="{{ $iconBox }} bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300"><x-icon name="user" /></span>
                    <span class="flex-1 font-medium strong">Account information</span>
                    <x-icon name="chevron-right" class="w-4 h-4 muted" />
                </a>
                <a href="{{ route('account.password') }}" class="{{ $row }}">
                    <span class="{{ $iconBox }} bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300"><x-icon name="lock" /></span>
                    <span class="flex-1 font-medium strong">{{ $user->has_password ? 'Change password' : 'Set a password' }}</span>
                    <x-icon name="chevron-right" class="w-4 h-4 muted" />
                </a>
                <form method="POST" action="{{ route('logout') }}"
                      data-confirm-title="Sign out?"
                      data-confirm-message="You will need your password to sign back in. Every expense, income and category stays safely in your account."
                      data-confirm-button="Sign out">
                    @csrf
                    <button type="submit" class="{{ $row }} w-full text-left">
                        <span class="{{ $iconBox }} bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-300"><x-icon name="logout" /></span>
                        <span class="flex-1 font-medium text-red-600 dark:text-red-400">Sign out</span>
                    </button>
                </form>
            </div>
        </section>
    </div>
</x-app-layout>
