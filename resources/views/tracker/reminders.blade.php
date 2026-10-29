<x-app-layout>
    <x-slot name="title">Reminders</x-slot>
    <x-slot name="subtitle">What the app sends you on its own. Pushes need the app on your phone with notifications allowed.</x-slot>
    @php
        $iconBox = 'icon-tile';
        $reminderAt = $user->daily_reminder_at;
        $times = collect(range(17 * 2, 23 * 2 + 1))->mapWithKeys(fn ($half) => [
            sprintf('%02d:%02d', intdiv($half, 2), $half % 2 * 30) => \Carbon\Carbon::createFromTime(intdiv($half, 2), $half % 2 * 30)->format('g:i A'),
        ]);
    @endphp

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section>
            <h2 class="section-label mb-2 px-1">Daily reminder</h2>
            <div class="card !p-0 divide-soft overflow-hidden">
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="{{ $iconBox }}"><x-icon name="bell" /></span>
                    <span class="flex-1 min-w-0">
                        <span class="block font-semibold strong">Daily reminder</span>
                        <span class="block text-[13px] muted">A push if you have not added an expense for the day yet.</span>
                    </span>
                    <form method="POST" action="{{ route('settings.reminders') }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="daily_reminder_at" value="{{ $reminderAt ? '' : '21:00' }}">
                        <button type="submit" role="switch" aria-checked="{{ $reminderAt ? 'true' : 'false' }}" aria-label="Daily reminder" class="adm-switch"><span></span></button>
                    </form>
                </div>
                @if($reminderAt)
                    <form method="POST" action="{{ route('settings.reminders') }}" class="flex items-center gap-3 px-4 py-3">
                        @csrf @method('PUT')
                        <span class="{{ $iconBox }}"><x-icon name="calendar" /></span>
                        <label for="daily_reminder_at" class="flex-1 font-semibold strong">Reminder time</label>
                        <select id="daily_reminder_at" name="daily_reminder_at" onchange="this.form.submit()" class="input !w-auto !min-h-10 !py-1.5 !text-sm">
                            @foreach($times->union([$reminderAt => \Carbon\Carbon::createFromFormat('H:i', $reminderAt)->format('g:i A')])->sortKeys() as $value => $label)
                                <option value="{{ $value }}" @selected($value === $reminderAt)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>
        </section>

        <section>
            <h2 class="section-label mb-2 px-1">Alerts and emails</h2>
            <div class="card !p-0 divide-soft overflow-hidden">
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
    </div>
</x-app-layout>
