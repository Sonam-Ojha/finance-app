<x-admin-layout>
    <x-slot name="title">App control</x-slot>
    <x-slot name="subtitle">Turn things on or off for every user at once. Each change is saved in the Activity log.</x-slot>

    @if($maintenance)
        <div class="adm-strip" role="status">
            <x-icon name="warning" class="mt-0.5 h-5 w-5 flex-shrink-0 text-status-error" />
            <p><span class="font-semibold text-status-error">Maintenance mode is on.</span>
                Users cannot use the app or the website right now. Turn it off below when you are done.</p>
        </div>
    @endif

    @php
        // A small "On" / "Off" label next to each switch's name.
        $pill = fn (bool $on, string $onText = 'On', string $offText = 'Off', bool $warn = false) => sprintf(
            '<span class="rounded-full px-2 py-0.5 text-[12px] font-semibold %s">%s</span>',
            $on ? ($warn ? 'bg-red-50 text-status-error' : 'bg-emerald-50 text-status-successText') : 'bg-surface-page text-ink-muted',
            e($on ? $onText : $offText),
        );
    @endphp

    <div class="grid items-start gap-6 lg:grid-cols-2">

        {{-- ── Who can get in ── --}}
        <section class="adm-card flex flex-col">
            <h2 class="adm-h2">App and website</h2>
            <p class="adm-caption mt-1">Who can get in.</p>

            <div class="mt-2 divide-y divide-surface-line dark:divide-night-line">
                {{-- Maintenance mode --}}
                <div class="py-4">
                    <div class="flex items-start gap-4">
                        <span class="icon-tile"><x-icon name="cog" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p id="maintenance-label" class="font-semibold">Maintenance mode</p>
                                {!! $pill($maintenance, 'On · only admins get in', 'Off · open for everyone', true) !!}
                            </div>
                            <p class="adm-caption mt-1">Closes the app and the website for everyone except admins and shows them a message. Use it while you fix or update something.</p>
                        </div>
                        <form method="POST" action="{{ route('admin.app-control.maintenance') }}"
                              data-confirm-title="{{ $maintenance ? 'Turn maintenance mode off?' : 'Turn maintenance mode on?' }}"
                              data-confirm-message="{{ $maintenance ? 'The app and the website open for everyone again.' : 'Users will not be able to use the app or the website until you turn it off. Admins can still get in.' }}"
                              data-confirm-button="{{ $maintenance ? 'Turn off' : 'Turn on' }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="enabled" value="{{ $maintenance ? 0 : 1 }}">
                            <button type="submit" role="switch" aria-checked="{{ $maintenance ? 'true' : 'false' }}" aria-labelledby="maintenance-label" class="adm-switch"><span></span></button>
                        </form>
                    </div>

                    <details class="group mt-3 pl-14" @if($errors->has('message')) open @endif>
                        <summary class="adm-link cursor-pointer list-none text-sm">
                            Change the message people see <x-icon name="chevron-down" class="inline h-4 w-4 transition-transform group-open:rotate-180" />
                        </summary>
                        <form method="POST" action="{{ route('admin.app-control.maintenance') }}" class="mt-3 flex flex-col gap-2"
                              x-data="{ message: @js(old('message', $maintenanceMessage ?? '')) }">
                            @csrf @method('PUT')
                            <label for="message" class="sr-only">Message people see</label>
                            <textarea id="message" name="message" rows="3" maxlength="300" x-model="message"
                                      placeholder="{{ \App\Support\AppControl::DEFAULT_MAINTENANCE_MESSAGE }}" class="adm-input h-auto w-full py-2"></textarea>
                            <p class="adm-caption flex justify-between gap-2"><span>Leave it empty to use the message shown in grey.</span><span><span x-text="message.length"></span>/300</span></p>
                            @error('message')<p class="text-[13px] text-status-error">{{ $message }}</p>@enderror
                            <button type="submit" class="adm-btn-outline self-start">Save message</button>
                        </form>
                    </details>
                </div>

                {{-- Email verification in the app --}}
                <div class="flex items-start gap-4 py-4">
                    <span class="icon-tile"><x-icon name="lock" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p id="email-label" class="font-semibold">Require a verified email in the app</p>
                            {!! $pill($emailRequired) !!}
                        </div>
                        <p class="adm-caption mt-1">People who have not confirmed their email must type the 6-digit code we email them before they can use the app. The website always asks for it.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.app-control.email-verification') }}"
                          data-confirm-title="{{ $emailRequired ? 'Stop asking for a verified email?' : 'Ask for a verified email?' }}"
                          data-confirm-message="{{ $emailRequired ? 'People can use the app without confirming their email.' : 'Anyone who has not confirmed their email will be asked for the code before they can carry on in the app.' }}"
                          data-confirm-button="{{ $emailRequired ? 'Stop asking' : 'Ask for it' }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="required" value="{{ $emailRequired ? 0 : 1 }}">
                        <button type="submit" role="switch" aria-checked="{{ $emailRequired ? 'true' : 'false' }}" aria-labelledby="email-label" class="adm-switch"><span></span></button>
                    </form>
                </div>
            </div>
        </section>

        {{-- ── App updates ── --}}
        <section class="adm-card flex flex-col gap-4">
            <div>
                <h2 class="adm-h2">App updates</h2>
                <p class="adm-caption mt-1">Ask people on an old version of the app to update it.</p>
            </div>

            <div class="flex items-start gap-3 rounded-card bg-surface-page p-4 text-sm dark:bg-night-page">
                <x-icon name="info" class="mt-0.5 h-5 w-5 flex-shrink-0 text-brand-navy dark:text-brand-cyan" />
                <div>
                    <p class="font-semibold">Right now</p>
                    <p class="mt-0.5">
                        {{ $minVersion ? "Apps older than {$minVersion} must update before they can be used." : 'Nobody has to update.' }}
                        @if($latestVersion) Apps older than {{ $latestVersion }} see “Update available”. @endif
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.app-control.versions') }}" class="flex flex-col gap-4"
                  data-confirm-title="Save update settings?"
                  data-confirm-message="If you set “Force update”, anyone on an older app has to update from the Play Store before they can carry on."
                  data-confirm-button="Save">
                @csrf @method('PUT')
                <div>
                    <label for="min_version" class="adm-label">Force update — versions older than</label>
                    <input id="min_version" name="min_version" type="text" maxlength="20" value="{{ old('min_version', $minVersion) }}" placeholder="e.g. 1.2.0" class="adm-input w-full font-mono">
                    <p class="adm-caption mt-1">They cannot use the app until they update. Leave it empty to turn this off.</p>
                    @error('min_version')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="latest_version" class="adm-label">Suggest an update — versions older than</label>
                    <input id="latest_version" name="latest_version" type="text" maxlength="20" value="{{ old('latest_version', $latestVersion) }}" placeholder="e.g. 1.3.0" class="adm-input w-full font-mono">
                    <p class="adm-caption mt-1">They see an “Update available” note but can carry on. Leave it empty to turn this off.</p>
                    @error('latest_version')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </div>
                <details class="group" @if($errors->has('update_message') || $updateMessage) open @endif>
                    <summary class="adm-link cursor-pointer list-none text-sm">
                        Message on the update screen (optional) <x-icon name="chevron-down" class="inline h-4 w-4 transition-transform group-open:rotate-180" />
                    </summary>
                    <label for="update_message" class="sr-only">Message on the update screen</label>
                    <textarea id="update_message" name="update_message" rows="2" maxlength="300"
                              placeholder="{{ \App\Support\AppControl::DEFAULT_UPDATE_MESSAGE }}" class="adm-input mt-2 h-auto w-full py-2">{{ old('update_message', $updateMessage) }}</textarea>
                    @error('update_message')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </details>
                <button type="submit" class="adm-btn-primary self-start">Save</button>
            </form>

            <p class="adm-caption border-t border-surface-page pt-3 dark:border-night-line">Works for app 1.2.0 and newer. Older apps do not tell the server their version, so they are never stopped.</p>
        </section>

        {{-- ── Reminders and other messages the server sends on its own ── --}}
        <section class="adm-card flex flex-col gap-4 lg:col-span-2">
            <div>
                <h2 class="adm-h2">Reminders and automatic messages</h2>
                <p class="adm-caption mt-1">Sent to every user on their own. Users cannot turn these off; only you can, here. Phone notifications reach people who have the app with notifications allowed.</p>
            </div>
            @php
                $times = collect(range(6 * 2, 23 * 2 + 1))->mapWithKeys(fn ($half) => [
                    sprintf('%02d:%02d', intdiv($half, 2), $half % 2 * 30) => \Carbon\Carbon::createFromTime(intdiv($half, 2), $half % 2 * 30)->format('g:i A'),
                ]);
                $rows = [
                    [\App\Support\AppControl::BUDGET_ALERT_PUSH, 'Budget alerts', 'A phone notification when a budget is 80% used, and again when it is used up.', 'warning'],
                    [\App\Support\AppControl::MONTHLY_SUMMARY, 'Monthly summary email', 'On the 1st: last month’s money in, spending and budgets.', 'envelope'],
                    [\App\Support\AppControl::BIRTHDAY_WISHES, 'Birthday wishes', 'A phone notification and an email on the user’s birthday, from 9 AM.', 'calendar'],
                ];
            @endphp
            <div class="grid gap-3 md:grid-cols-2">
                {{-- The daily reminder: a switch and its time --}}
                <div class="flex flex-col gap-3 rounded-card border border-surface-line p-4 dark:border-night-line">
                    <div class="flex items-start gap-3">
                        <span class="icon-tile"><x-icon name="bell" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p id="daily-reminder-label" class="font-semibold">Daily reminder</p>
                                {!! $pill((bool) $reminderAt) !!}
                            </div>
                            <p class="adm-caption mt-1">“Logged today’s spending?” — a phone notification to anyone who has not added an expense that day.</p>
                        </div>
                        <form method="POST" action="{{ route('admin.app-control.reminders') }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="daily_reminder" value="{{ $reminderAt ? 0 : 1 }}">
                            <button type="submit" role="switch" aria-checked="{{ $reminderAt ? 'true' : 'false' }}" aria-labelledby="daily-reminder-label" class="adm-switch"><span></span></button>
                        </form>
                    </div>
                    @if($reminderAt)
                        <form method="POST" action="{{ route('admin.app-control.reminders') }}" class="flex items-center gap-3 border-t border-surface-page pt-3 dark:border-night-line">
                            @csrf @method('PUT')
                            <label for="daily_reminder_at" class="flex-1 text-sm font-semibold">Time (India)</label>
                            <select id="daily_reminder_at" name="daily_reminder_at" onchange="this.form.submit()" class="adm-input !h-10 !w-auto !py-1 text-sm" style="padding-right: 2.25rem">
                                @foreach($times->union([$savedReminderAt => \Carbon\Carbon::createFromFormat('H:i', $savedReminderAt)->format('g:i A')])->sortKeys() as $value => $label)
                                    <option value="{{ $value }}" @selected($value === $savedReminderAt)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                        @error('daily_reminder_at')<p class="text-[13px] text-status-error">{{ $message }}</p>@enderror
                    @endif
                </div>

                @foreach($rows as [$key, $label, $hint, $icon])
                    @php $on = $autoMessages[$key]; @endphp
                    <div class="flex items-start gap-3 rounded-card border border-surface-line p-4 dark:border-night-line">
                        <span class="icon-tile"><x-icon :name="$icon" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p id="{{ $key }}-label" class="font-semibold">{{ $label }}</p>
                                {!! $pill($on) !!}
                            </div>
                            <p class="adm-caption mt-1">{{ $hint }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.app-control.reminders') }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="{{ $key }}" value="{{ $on ? 0 : 1 }}">
                            <button type="submit" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-labelledby="{{ $key }}-label" class="adm-switch"><span></span></button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── For developers: what the app is told ── --}}
        <details class="adm-card group lg:col-span-2">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                <span>
                    <span class="adm-h2 block">Technical details</span>
                    <span class="adm-caption">For developers: what the app reads when it opens (GET /api/v1/app-status).</span>
                </span>
                <x-icon name="chevron-down" class="h-5 w-5 flex-shrink-0 text-ink-muted transition-transform group-open:rotate-180" />
            </summary>
            <pre class="mt-4 overflow-x-auto rounded-card bg-surface-page p-4 font-mono text-[13px] leading-relaxed text-ink dark:bg-night-page dark:text-night-ink">{{ json_encode(['data' => $status], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    </div>
</x-admin-layout>
