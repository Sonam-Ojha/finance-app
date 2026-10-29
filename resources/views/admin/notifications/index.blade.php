<x-admin-layout>
    <x-slot name="title">Notifications</x-slot>
    <x-slot name="subtitle">
        {{ \App\Support\Counts::indian($devices) }} {{ \Illuminate\Support\Str::plural('phone', $devices) }} signed in, from {{ \App\Support\Counts::indian($reachable) }} {{ \Illuminate\Support\Str::plural('user', $reachable) }}
        · {{ \App\Support\Counts::indian($emailable) }} {{ \Illuminate\Support\Str::plural('user', $emailable) }} with emails on.
        Every message is also kept in the app's notification list.
    </x-slot>

    @unless($configured)
        <div class="adm-strip" role="status">
            <x-icon name="warning" class="mt-0.5 h-5 w-5 flex-shrink-0 text-status-error" />
            <div>
                <p class="font-semibold text-brand-navy">Push notifications are not set up yet.</p>
                <p class="text-ink-muted">Messages are saved to the app's notification list, but no phone gets a push until the Firebase service-account key is on the server (<code class="font-mono text-[13px]">storage/app/firebase-credentials.json</code>). Email still works.</p>
            </div>
        </div>
    @endunless

    @php
        $channels = old('channels', ['push']);
        $weekdays = \App\Models\ScheduledNotification::WEEKDAYS;
        $emailReach = \App\Support\Counts::indian($emailable) . ' ' . \Illuminate\Support\Str::plural('user', $emailable) . ' with emails on';
    @endphp
    <div class="flex flex-wrap items-start gap-4"
         x-data="{
             audience: @js(old('audience', 'all')),
             email: @js(old('email', '')),
             title: @js(old('title', '')),
             body: @js(old('body', '')),
             channels: @js($channels),
             when: @js(old('when', 'now')),
             repeat: @js(old('repeat', 'daily')),
             time: @js(old('time', '19:00')),
             date: @js(old('date', $now->copy()->addDay()->toDateString())),
             weekday: @js((string) old('weekday', $now->dayOfWeek)),
             days: @js($weekdays),
             tab: @js(in_array('push', $channels, true) ? 'push' : 'email'),
             has(channel) { return this.channels.includes(channel) },
             clock() {
                 const [h, m] = (this.time || '19:00').split(':').map(Number);
                 return `${(h + 11) % 12 + 1}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
             },
             scheduleText() {
                 if (this.repeat === 'daily') return `Every day at ${this.clock()}`;
                 if (this.repeat === 'weekly') return `Every ${this.days[this.weekday]} at ${this.clock()}`;
                 const day = this.date ? new Date(this.date + 'T00:00').toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short' }) : 'the day you pick';
                 return `Once, on ${day} at ${this.clock()}`;
             },
             confirmText() {
                 const all = this.audience === 'all';
                 const how = [];
                 if (this.has('push')) how.push(all ? 'a push to every signed-in phone' : 'a push to their phone');
                 if (this.has('email')) how.push(all ? 'an email to everyone with emails on' : 'an email, if they keep emails on');
                 const start = this.when === 'later' ? `${this.scheduleText()} (India time), it goes` : 'It goes';
                 return `${start} to ${all ? 'every user' : (this.email || 'the user you named')}: ${how.join(' and ')}. It is also kept in the app's notification list.`;
             },
         }"
         x-init="$watch('channels', (on) => { if (on.length && ! on.includes(tab)) tab = on[0] })">

        {{-- Compose: now, or on a schedule --}}
        <section class="adm-card flex-[1_1_360px]">
            <h2 class="adm-h2">New notification</h2>
            <form method="POST" action="{{ route('admin.notifications.store') }}" class="mt-4 flex flex-col gap-4"
                  :data-confirm-title="when === 'later' ? 'Schedule this notification?' : 'Send this notification?'"
                  :data-confirm-message="confirmText()"
                  :data-confirm-button="when === 'later' ? 'Schedule' : 'Send'">
                @csrf
                <div>
                    <label for="title" class="adm-label">Title</label>
                    <input id="title" type="text" name="title" x-model="title" maxlength="100" required
                           placeholder="e.g. New feature: monthly budgets" class="adm-input w-full">
                    <p class="adm-caption mt-1 text-right"><span x-text="title.length"></span>/100</p>
                    @error('title')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="body" class="adm-label">Message</label>
                    <textarea id="body" name="body" rows="4" x-model="body" maxlength="500" required
                              placeholder="What should people know?" class="adm-input h-auto w-full py-2"></textarea>
                    <p class="adm-caption mt-1 text-right"><span x-text="body.length"></span>/500</p>
                    @error('body')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </div>

                <fieldset>
                    <legend class="adm-label">Send as</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach([
                            ['push', 'Push', 'megaphone', \App\Support\Counts::indian($devices) . ' ' . \Illuminate\Support\Str::plural('phone', $devices)],
                            ['email', 'Email', 'envelope', $emailReach],
                        ] as [$value, $label, $icon, $reach])
                            <label class="flex cursor-pointer items-start gap-3 rounded-card border-2 p-3 transition-colors"
                                   :class="has(@js($value)) ? 'border-brand-navy bg-brand-tint' : 'border-surface-field hover:border-brand-navy/40'">
                                <input type="checkbox" name="channels[]" value="{{ $value }}" x-model="channels"
                                       class="mt-0.5 h-5 w-5 rounded border-surface-field text-brand-navy focus:ring-brand-navy">
                                <span class="min-w-0">
                                    <span class="flex items-center gap-1.5 font-semibold text-brand-navy"><x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}</span>
                                    <span class="adm-caption block">{{ $reach }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p x-show="channels.length === 0" x-cloak class="mt-1 text-[13px] text-status-error">Pick push, email or both.</p>
                    @error('channels')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </fieldset>

                <fieldset>
                    <legend class="adm-label">Send to</legend>
                    <div class="adm-seg w-full">
                        <label class="adm-seg-item flex-1 cursor-pointer justify-center" :aria-pressed="audience === 'all'">
                            <input type="radio" name="audience" value="all" x-model="audience" class="sr-only"> All users
                        </label>
                        <label class="adm-seg-item flex-1 cursor-pointer justify-center" :aria-pressed="audience === 'user'">
                            <input type="radio" name="audience" value="user" x-model="audience" class="sr-only"> One user
                        </label>
                    </div>
                </fieldset>
                <div x-show="audience === 'user'" x-cloak>
                    <label for="email" class="adm-label">User's email</label>
                    <input id="email" type="email" name="email" x-model="email"
                           :required="audience === 'user'" placeholder="someone@example.com" class="adm-input w-full">
                    @error('email')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </div>

                {{-- When: now, or later — once, every day or every week --}}
                <fieldset>
                    <legend class="adm-label">When</legend>
                    <div class="adm-seg w-full">
                        <label class="adm-seg-item flex-1 cursor-pointer justify-center" :aria-pressed="when === 'now'">
                            <input type="radio" name="when" value="now" x-model="when" class="sr-only"> Send now
                        </label>
                        <label class="adm-seg-item flex-1 cursor-pointer justify-center gap-1.5" :aria-pressed="when === 'later'">
                            <input type="radio" name="when" value="later" x-model="when" class="sr-only"> <x-icon name="calendar" class="h-4 w-4" /> Schedule
                        </label>
                    </div>
                </fieldset>

                <div x-show="when === 'later'" x-cloak class="flex flex-col gap-3 rounded-card border border-surface-field bg-surface-page/60 p-3">
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Repeat">
                        @foreach(\App\Models\ScheduledNotification::REPEATS as $key => $label)
                            <label class="chip" :class="repeat === @js($key) && 'chip-on'">
                                <input type="radio" name="repeat" value="{{ $key }}" x-model="repeat" class="sr-only" :disabled="when !== 'later'"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('repeat')<p class="text-[13px] text-status-error">{{ $message }}</p>@enderror

                    <div class="grid grid-cols-2 gap-2">
                        <div x-show="repeat === 'once'">
                            <label for="date" class="adm-label">Date</label>
                            <input id="date" type="date" name="date" x-model="date" min="{{ $now->toDateString() }}"
                                   :disabled="when !== 'later' || repeat !== 'once'" class="adm-input w-full">
                        </div>
                        <div x-show="repeat === 'weekly'" x-cloak>
                            <label for="weekday" class="adm-label">Day</label>
                            <select id="weekday" name="weekday" x-model="weekday" :disabled="when !== 'later' || repeat !== 'weekly'" class="adm-input w-full">
                                @foreach($weekdays as $index => $day)
                                    <option value="{{ $index }}">{{ $day }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div :class="repeat === 'daily' && 'col-span-2'">
                            <label for="time" class="adm-label">Time</label>
                            <input id="time" type="time" name="time" x-model="time" step="60" :disabled="when !== 'later'" class="adm-input w-full">
                        </div>
                    </div>
                    @foreach(['date', 'weekday', 'time'] as $field)
                        @error($field)<p class="text-[13px] text-status-error">{{ $message }}</p>@enderror
                    @endforeach
                    <p class="flex items-center gap-2 text-sm font-semibold text-brand-navy">
                        <x-icon name="calendar" class="h-4 w-4" /> <span x-text="scheduleText() + ' (India time)'"></span>
                    </p>
                </div>

                <button type="submit" class="adm-btn-primary w-full disabled:cursor-not-allowed disabled:opacity-50" :disabled="channels.length === 0">
                    <x-icon name="megaphone" class="h-5 w-5" /> <span x-text="when === 'later' ? 'Schedule notification' : 'Send notification'">Send notification</span>
                </button>
            </form>
        </section>

        {{-- Preview: the push on a lock screen, the email open in a mail app --}}
        <section class="adm-card flex-[1_1_360px]" aria-labelledby="preview-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="preview-heading" class="adm-h2">Preview</h2>
                <div class="adm-seg" role="group" aria-label="Preview">
                    <button type="button" class="adm-seg-item" :aria-pressed="tab === 'push'" @click="tab = 'push'">Push</button>
                    <button type="button" class="adm-seg-item" :aria-pressed="tab === 'email'" @click="tab = 'email'">Email</button>
                </div>
            </div>
            <p class="adm-caption mt-2" x-show="! has(tab)" x-cloak>
                <span x-text="tab === 'push' ? 'Push' : 'Email'"></span> is off for this message. Tick it under “Send as” to send it this way too.
            </p>

            {{-- Push: the lock screen --}}
            <div x-show="tab === 'push'" class="mt-6 flex justify-center" :class="! has('push') && 'opacity-50'">
                <x-admin.phone :time="$now->format('g:i')" style="background: linear-gradient(170deg, #0b3d77 0%, #34407a 48%, #6a4a74 100%)">
                    <div class="flex flex-1 flex-col px-3">
                        <x-icon name="lock" class="mx-auto mt-4 h-4 w-4 text-white/90" />
                        <p class="mt-1 text-center font-display text-[60px] font-semibold leading-none tracking-tight">{{ $now->format('g:i') }}</p>
                        <p class="mt-2 text-center text-[14px] font-medium text-white/90">{{ $now->format('l, j F') }}</p>

                        <div class="mt-6 rounded-[18px] bg-white/25 p-3 backdrop-blur-md">
                            <div class="flex items-center gap-2 text-[11px] text-white/85">
                                <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-[6px] bg-brand-navy text-[11px] font-bold text-white" aria-hidden="true">₹</span>
                                <span class="flex-1 truncate font-medium">Expense Tracker</span>
                                <span>now</span>
                            </div>
                            <p class="mt-1.5 break-words text-[14px] font-semibold leading-snug" :class="! title && 'text-white/60'" x-text="title || 'Your title'">Your title</p>
                            <p class="line-clamp-4 whitespace-pre-line break-words text-[13px] leading-snug text-white/90" :class="! body && 'text-white/60'" x-text="body || 'Your message shows here as you type.'">Your message shows here as you type.</p>
                        </div>

                        <div class="mt-auto flex items-center justify-between px-2 pb-4" aria-hidden="true">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/25">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M8 3h8v4l-2 3v10a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V10L8 7V3Z"/><path d="M12 13v2"/></svg>
                            </span>
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/25"><x-icon name="camera" class="h-5 w-5" /></span>
                        </div>
                    </div>
                </x-admin.phone>
            </div>

            {{-- Email: open in a mail app, as resources/views/mail/announcement draws it --}}
            <div x-show="tab === 'email'" x-cloak class="mt-6 flex justify-center" :class="! has('email') && 'opacity-50'">
                <x-admin.phone :time="$now->format('g:i')" light>
                    {{-- The mail app's bar --}}
                    <div class="flex flex-shrink-0 items-center justify-between px-3 py-1.5 text-ink-muted" aria-hidden="true">
                        <x-icon name="chevron-left" class="h-5 w-5" />
                        <span class="flex items-center gap-3">
                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7Zm-1-3h18v3H3V4Zm7 7h4"/></svg>
                            <x-icon name="trash" class="h-[18px] w-[18px]" />
                            <x-icon name="envelope" class="h-[18px] w-[18px]" />
                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                        </span>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden px-3">
                        <p class="break-words text-[16px] font-semibold leading-snug text-ink" :class="! title && 'text-ink-muted'">
                            <span x-text="title || 'Your title'">Your title</span>
                            <span class="ml-1 inline-block rounded bg-surface-page px-1.5 align-middle text-[10px] font-medium text-ink-muted">Inbox</span>
                        </p>

                        <div class="mt-3 flex items-start gap-2">
                            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-brand-navy text-[12px] font-bold text-white" aria-hidden="true">₹</span>
                            <div class="min-w-0 flex-1 leading-tight">
                                <p class="flex items-baseline justify-between gap-2 text-[12px]">
                                    <span class="truncate font-semibold text-ink">Expense Tracker</span>
                                    <span class="flex-shrink-0 text-[10px] text-ink-muted">{{ $now->format('g:i A') }}</span>
                                </p>
                                <p class="truncate text-[10px] text-ink-muted">{{ $from }} · to me</p>
                            </div>
                        </div>

                        {{-- The message itself: the mail layout, shrunk to the screen --}}
                        <div class="mt-3 min-h-0 flex-1 overflow-hidden rounded-[10px] bg-[#edf2f7] px-2.5 pb-3 pt-2.5 text-[#718096]">
                            <p class="text-center text-[12px] font-bold text-[#3d4852]">Expense Tracker</p>
                            <div class="mt-2 rounded-[6px] bg-white p-3 text-[10.5px] leading-relaxed">
                                <p class="font-bold text-[#3d4852]">Hi [their name],</p>
                                <p class="mt-1.5 break-words text-[13px] font-bold leading-snug text-[#3d4852]" x-text="title || 'Your title'"></p>
                                <p class="mt-1 line-clamp-6 whitespace-pre-line break-words" x-text="body || 'Your message shows here as you type.'"></p>
                                <p class="mt-2.5 text-center"><span class="inline-block rounded-[4px] bg-[#2d3748] px-2.5 py-1 text-[10px] font-semibold text-white">Open Expense Tracker</span></p>
                                <p class="mt-2.5 border-t border-[#e8e5ef] pt-1.5 text-[8.5px] leading-snug">You are getting this because email notifications are on for your Expense Tracker account.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-shrink-0 gap-2 px-3 pb-1 pt-2" aria-hidden="true">
                        @foreach(['Reply', 'Forward'] as $action)
                            <span class="flex-1 rounded-full border border-surface-field py-1.5 text-center text-[11px] font-semibold text-ink-muted">{{ $action }}</span>
                        @endforeach
                    </div>
                </x-admin.phone>
            </div>
            <p class="adm-caption mt-4 text-center" x-show="tab === 'email'" x-cloak>
                To: <span x-text="audience === 'all' ? @js($emailReach . ', one email each') : (email || 'the user you name')"></span>
            </p>
        </section>
    </div>

    {{-- What went out, and what is coming --}}
    <section class="adm-card min-w-0" x-data="{ list: @js($tab) }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="adm-h2" x-text="list === 'scheduled' ? 'Scheduled' : 'Sent'">{{ $tab === 'scheduled' ? 'Scheduled' : 'Sent' }}</h2>
            <div class="adm-seg" role="group" aria-label="List">
                <button type="button" class="adm-seg-item" :aria-pressed="list === 'sent'" @click="list = 'sent'">Sent</button>
                <button type="button" class="adm-seg-item gap-1.5" :aria-pressed="list === 'scheduled'" @click="list = 'scheduled'">
                    Scheduled
                    @if($active = $schedules->where('active', true)->count())
                        <span class="rounded-full bg-brand-cyan px-1.5 text-xs text-brand-navy">{{ $active }}</span>
                    @endif
                </button>
            </div>
        </div>

        <ul class="mt-2" x-show="list === 'sent'" @if($tab === 'scheduled') x-cloak @endif>
            @forelse($history as $item)
                <li class="border-b border-surface-page py-4 last:border-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $item->title }}</p>
                            <p class="mt-0.5 whitespace-pre-line break-words text-ink-muted">{{ $item->body }}</p>
                        </div>
                        <div class="flex flex-shrink-0 flex-col items-end gap-1.5">
                            @if($item->via_push)
                                @if($item->status === \App\Models\AdminNotification::STATUS_NOT_CONFIGURED)
                                    <span class="adm-badge-medium">Push: inbox only</span>
                                @else
                                    <span class="{{ $item->failed > 0 ? 'adm-badge-high' : 'adm-badge-low' }}">Push {{ $item->delivered }}/{{ $item->devices }} delivered</span>
                                @endif
                            @endif
                            @if($item->via_email)
                                @if($item->emailsPending())
                                    <span class="adm-badge-medium">Email sending…</span>
                                @else
                                    <span class="{{ $item->emails_failed > 0 ? 'adm-badge-high' : 'adm-badge-low' }}">Email {{ $item->emails_sent }}/{{ $item->email_recipients }} sent</span>
                                @endif
                            @endif
                        </div>
                    </div>
                    <p class="adm-caption mt-2 flex flex-wrap gap-x-3 gap-y-1">
                        @if($item->scheduled_notification_id)
                            <span class="inline-flex items-center gap-1 font-semibold text-brand-navy"><x-icon name="calendar" class="h-3.5 w-3.5" /> Scheduled</span>
                        @endif
                        <span>To: {{ $item->audience === 'all' ? 'All users' : ($item->target ? mask_email($item->target->email) : 'deleted user') }}</span>
                        <span>By: {{ $item->sender?->name ?: ($item->sender ? mask_email($item->sender->email) : 'deleted admin') }}</span>
                        <span>{{ $item->created_at->timezone(config('app.display_timezone'))->format('j M Y, g:i A') }}</span>
                    </p>
                </li>
            @empty
                <li class="py-10 text-center text-ink-muted">No notifications sent yet.</li>
            @endforelse
            @if($history->hasPages())
                <li class="mt-4 list-none">{{ $history->links() }}</li>
            @endif
        </ul>

        <ul class="mt-2" x-show="list === 'scheduled'" @if($tab !== 'scheduled') x-cloak @endif>
            @forelse($schedules as $schedule)
                @php $done = ! $schedule->active && $schedule->repeat === 'once' && $schedule->runs > 0; @endphp
                <li class="border-b border-surface-page py-4 last:border-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $schedule->title }}</p>
                            <p class="mt-0.5 line-clamp-2 whitespace-pre-line break-words text-ink-muted">{{ $schedule->body }}</p>
                        </div>
                        <div class="flex flex-shrink-0 flex-col items-end gap-1.5">
                            @if($schedule->active)
                                <span class="adm-badge-low">Active</span>
                            @elseif($done)
                                <span class="adm-badge bg-surface-page text-ink-muted">Done</span>
                            @else
                                <span class="adm-badge-medium">Paused</span>
                            @endif
                            <span class="adm-caption">{{ collect(['Push' => $schedule->via_push, 'Email' => $schedule->via_email])->filter()->keys()->join(' + ') }}</span>
                        </div>
                    </div>
                    <p class="adm-caption mt-2 flex flex-wrap gap-x-3 gap-y-1">
                        <span class="inline-flex items-center gap-1 font-semibold text-brand-navy"><x-icon name="calendar" class="h-3.5 w-3.5" /> {{ $schedule->describe() }}</span>
                        @if($schedule->active && $schedule->next_run_at)
                            <span>Next: {{ $schedule->next_run_at->timezone(config('app.display_timezone'))->format('D j M, g:i A') }}</span>
                        @endif
                        <span>To: {{ $schedule->audience === 'all' ? 'All users' : ($schedule->target ? mask_email($schedule->target->email) : 'deleted user') }}</span>
                        <span>Sent {{ $schedule->runs }} {{ \Illuminate\Support\Str::plural('time', $schedule->runs) }}</span>
                    </p>
                    @unless($done)
                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.notifications.scheduled.toggle', $schedule) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="adm-btn-outline !h-10 !px-4 !text-sm">{{ $schedule->active ? 'Pause' : 'Resume' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.notifications.scheduled.destroy', $schedule) }}"
                                  data-confirm-title="Delete this schedule?"
                                  data-confirm-message="“{{ $schedule->title }}” stops going out. What it already sent stays in the app's notification list."
                                  data-confirm-button="Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="min-h-10 px-2 text-sm font-semibold text-status-error hover:underline">Delete</button>
                            </form>
                        </div>
                    @endunless
                </li>
            @empty
                <li class="py-10 text-center text-ink-muted">Nothing scheduled. Pick “Schedule” under “When” to send a notification later, or every day.</li>
            @endforelse
        </ul>
    </section>
</x-admin-layout>
