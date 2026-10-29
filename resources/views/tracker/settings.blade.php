<x-app-layout>
    <x-slot name="title">Settings</x-slot>
    @php
        $row = 'flex items-center gap-3 px-4 py-3 hover:bg-surface-page dark:hover:bg-white/5 transition-colors';
        $iconBox = 'icon-tile';
    @endphp

    <div class="max-w-2xl space-y-5">
        <section>
            <h2 class="section-label mb-2 px-1">Preferences</h2>
            <div class="card !p-0 divide-soft overflow-hidden">
                @foreach([
                    [route('settings.appearance'), 'Appearance', $theme, $user->theme_mode === 'dark' ? 'moon' : 'sun'],
                    [route('currency.index'), 'Currency', $currency, 'money'],
                    [route('categories.index'), 'Categories', $categories, 'tag'],
                    [route('budgets.index'), 'Budgets', $budgets ? "{$budgets} set" : 'None', 'wallet'],
                ] as [$href, $label, $value, $icon])
                    <a href="{{ $href }}" class="{{ $row }}">
                        <span class="{{ $iconBox }}"><x-icon :name="$icon" /></span>
                        <span class="flex-1 font-semibold strong">{{ $label }}</span>
                        <span class="text-sm muted">{{ $value }}</span>
                        <x-icon name="chevron-right" class="w-4 h-4 muted" />
                    </a>
                @endforeach
            </div>
        </section>

        <section>
            <h2 class="section-label mb-2 px-1">More</h2>
            <div class="card !p-0 divide-soft overflow-hidden">
                <form method="POST" action="{{ route('settings.email') }}" class="flex items-center gap-3 px-4 py-3.5">
                    @csrf @method('PUT')
                    @php $on = $user->email_notifications ?? true; @endphp
                    <input type="hidden" name="enabled" value="{{ $on ? 0 : 1 }}">
                    <span class="{{ $iconBox }}"><x-icon name="envelope" /></span>
                    <span class="flex-1 min-w-0">
                        <span class="block font-semibold strong">Email notifications</span>
                        <span class="block text-[13px] muted">An email each time you add an expense, income, category or budget.</span>
                    </span>
                    <button type="submit" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="Email notifications" class="adm-switch">
                        <span></span>
                    </button>
                </form>
                <a href="{{ route('notifications.index') }}" class="{{ $row }}">
                    <span class="{{ $iconBox }}"><x-icon name="bell" /></span>
                    <span class="flex-1 font-semibold strong">Notifications</span>
                    <x-icon name="chevron-right" class="w-4 h-4 muted" />
                </a>
                <a href="{{ route('about') }}" class="{{ $row }}">
                    <span class="{{ $iconBox }}"><x-icon name="info" /></span>
                    <span class="flex-1 font-semibold strong">About</span>
                    <x-icon name="chevron-right" class="w-4 h-4 muted" />
                </a>
            </div>
        </section>

        <section>
            <h2 class="section-label mb-2 px-1">Account</h2>
            <div class="card !p-0 divide-soft overflow-hidden">
                <a href="{{ route('profile.edit') }}" class="{{ $row }}">
                    <span class="{{ $iconBox }}"><x-icon name="user" /></span>
                    <span class="flex-1 font-semibold strong">Account information</span>
                    <x-icon name="chevron-right" class="w-4 h-4 muted" />
                </a>
                <a href="{{ route('account.password') }}" class="{{ $row }}">
                    <span class="{{ $iconBox }}"><x-icon name="lock" /></span>
                    <span class="flex-1 font-semibold strong">{{ $user->has_password ? 'Change password' : 'Set a password' }}</span>
                    <x-icon name="chevron-right" class="w-4 h-4 muted" />
                </a>
                <form method="POST" action="{{ route('logout') }}"
                      data-confirm-title="Sign out?"
                      data-confirm-message="You will need your password to sign back in. Every expense, income and category stays safely in your account."
                      data-confirm-button="Sign out">
                    @csrf
                    <button type="submit" class="{{ $row }} w-full text-left">
                        <span class="{{ $iconBox }} bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-300"><x-icon name="logout" /></span>
                        <span class="flex-1 font-medium text-status-error dark:text-red-400">Sign out</span>
                    </button>
                </form>
            </div>
        </section>
    </div>
</x-app-layout>
