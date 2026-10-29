<x-app-layout>
    <x-slot name="title">Settings</x-slot>
    @php
        $row = 'flex items-center gap-3 px-4 py-3 hover:bg-surface-page dark:hover:bg-white/5 transition-colors';
        $iconBox = 'icon-tile';
    @endphp

    {{-- Two columns of sections on wide screens --}}
    <div class="grid items-start gap-6 lg:grid-cols-2">
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

        {{-- Reminders the server sends on its own (pushes need the app with notifications allowed) --}}
        <section>
            <h2 class="section-label mb-2 px-1">Reminders</h2>
            @php
                $reminderAt = $user->daily_reminder_at;
                $times = collect(range(17 * 2, 23 * 2 + 1))->mapWithKeys(fn ($half) => [
                    sprintf('%02d:%02d', intdiv($half, 2), $half % 2 * 30) => \Carbon\Carbon::createFromTime(intdiv($half, 2), $half % 2 * 30)->format('g:i A'),
                ]);
            @endphp
            <div class="card !p-0 divide-soft overflow-hidden">
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="{{ $iconBox }}"><x-icon name="bell" /></span>
                    <span class="flex-1 min-w-0">
                        <span class="block font-semibold strong">Daily reminder</span>
                        <span class="block text-[13px] muted">A push if you have not added an expense for the day yet.</span>
                    </span>
                    @if($reminderAt)
                        <form method="POST" action="{{ route('settings.reminders') }}">
                            @csrf @method('PUT')
                            <label for="daily_reminder_at" class="sr-only">Reminder time</label>
                            <select id="daily_reminder_at" name="daily_reminder_at" onchange="this.form.submit()" class="input !w-auto !min-h-10 !py-1.5 !text-sm">
                                @foreach($times->union([$reminderAt => \Carbon\Carbon::createFromFormat('H:i', $reminderAt)->format('g:i A')])->sortKeys() as $value => $label)
                                    <option value="{{ $value }}" @selected($value === $reminderAt)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('settings.reminders') }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="daily_reminder_at" value="{{ $reminderAt ? '' : '21:00' }}">
                        <button type="submit" role="switch" aria-checked="{{ $reminderAt ? 'true' : 'false' }}" aria-label="Daily reminder" class="adm-switch"><span></span></button>
                    </form>
                </div>
                @foreach([
                    ['budget_alert_push', 'Budget alerts on your phone', 'A push when a budget is 80% used, and when it is passed.', 'warning'],
                    ['monthly_summary', 'Monthly summary email', 'Last month’s money in, spending and budgets, on the 1st.', 'envelope'],
                ] as [$field, $label, $hint, $icon])
                    @php $on = (bool) ($user->{$field} ?? true); @endphp
                    <form method="POST" action="{{ route('settings.reminders') }}" class="flex items-center gap-3 px-4 py-3">
                        @csrf @method('PUT')
                        <input type="hidden" name="{{ $field }}" value="{{ $on ? 0 : 1 }}">
                        <span class="{{ $iconBox }}"><x-icon :name="$icon" /></span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-semibold strong">{{ $label }}</span>
                            <span class="block text-[13px] muted">{{ $hint }}</span>
                        </span>
                        <button type="submit" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="{{ $label }}" class="adm-switch"><span></span></button>
                    </form>
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
