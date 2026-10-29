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

    @php $channels = old('channels', ['push']); @endphp
    <div class="flex flex-wrap items-start gap-4"
         x-data="{
             audience: @js(old('audience', 'all')),
             email: @js(old('email', '')),
             title: @js(old('title', '')),
             body: @js(old('body', '')),
             channels: @js($channels),
             tab: @js(in_array('push', $channels, true) ? 'push' : 'email'),
             has(channel) { return this.channels.includes(channel) },
             confirmText() {
                 const all = this.audience === 'all';
                 const how = [];
                 if (this.has('push')) how.push(all ? 'a push to every signed-in phone' : 'a push to their phone');
                 if (this.has('email')) how.push(all ? 'an email to the {{ $emailable }} users with emails on' : 'an email, if they keep emails on');
                 return `It goes to ${all ? 'every user' : (this.email || 'the user you named')}: ${how.join(' and ')}. It is also kept in the app's notification list.`;
             },
         }"
         x-init="$watch('channels', (on) => { if (on.length && ! on.includes(tab)) tab = on[0] })">

        {{-- Compose --}}
        <section class="adm-card flex-[1_1_360px]">
            <h2 class="adm-h2">New notification</h2>
            <form method="POST" action="{{ route('admin.notifications.store') }}" class="mt-4 flex flex-col gap-4"
                  data-confirm-title="Send this notification?"
                  :data-confirm-message="confirmText()"
                  data-confirm-button="Send">
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
                            ['email', 'Email', 'envelope', \App\Support\Counts::indian($emailable) . ' ' . \Illuminate\Support\Str::plural('user', $emailable) . ' with emails on'],
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
                <button type="submit" class="adm-btn-primary w-full disabled:cursor-not-allowed disabled:opacity-50" :disabled="channels.length === 0">
                    <x-icon name="megaphone" class="h-5 w-5" /> Send notification
                </button>
            </form>
        </section>

        {{-- Preview: what lands on a phone, and in an inbox --}}
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

            {{-- Push: the phone's lock screen --}}
            <div x-show="tab === 'push'" class="mt-6 flex justify-center" :class="! has('push') && 'opacity-50'">
                <div class="rounded-[48px] bg-[#0b0f19] p-[10px] shadow-[0_24px_48px_-16px_rgba(0,46,98,.45)]">
                    <div class="relative flex h-[540px] w-[258px] flex-col overflow-hidden rounded-[38px] px-3 pb-2 pt-3 text-white"
                         style="background: linear-gradient(170deg, #0b3d77 0%, #34407a 48%, #6a4a74 100%)">
                        {{-- Status bar and the camera cut-out --}}
                        <div class="flex items-center justify-between px-4 text-[12px] font-semibold" aria-hidden="true">
                            <span>{{ $now->format('g:i') }}</span>
                            <span class="absolute left-1/2 top-[10px] h-[26px] w-[88px] -translate-x-1/2 rounded-full bg-black"></span>
                            <span class="flex items-center gap-1">
                                <svg class="h-3 w-4" viewBox="0 0 16 12" fill="currentColor"><rect x="0" y="8" width="3" height="4" rx="1"/><rect x="4.3" y="5.5" width="3" height="6.5" rx="1"/><rect x="8.6" y="3" width="3" height="9" rx="1"/><rect x="12.9" y="0" width="3" height="12" rx="1"/></svg>
                                <svg class="h-3 w-4" viewBox="0 0 16 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M1.5 4.5a9.5 9.5 0 0 1 13 0M4 7.2a6 6 0 0 1 8 0"/><circle cx="8" cy="10" r="1.2" fill="currentColor" stroke="none"/></svg>
                                <svg class="h-3 w-6" viewBox="0 0 26 12" fill="none"><rect x=".75" y=".75" width="21.5" height="10.5" rx="3" stroke="currentColor" stroke-opacity=".5" stroke-width="1.5"/><rect x="2.5" y="2.5" width="18" height="7" rx="1.5" fill="currentColor"/><rect x="23.5" y="4" width="2" height="4" rx="1" fill="currentColor" fill-opacity=".5"/></svg>
                            </span>
                        </div>

                        <x-icon name="lock" class="mx-auto mt-5 h-4 w-4 text-white/90" />
                        <p class="mt-1 text-center font-display text-[60px] font-semibold leading-none tracking-tight">{{ $now->format('g:i') }}</p>
                        <p class="mt-2 text-center text-[14px] font-medium text-white/90">{{ $now->format('l, j F') }}</p>

                        {{-- The notification --}}
                        <div class="mt-6 rounded-[18px] bg-white/25 p-3 backdrop-blur-md">
                            <div class="flex items-center gap-2 text-[11px] text-white/85">
                                <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-[6px] bg-brand-navy text-[11px] font-bold text-white" aria-hidden="true">₹</span>
                                <span class="flex-1 truncate font-medium">Expense Tracker</span>
                                <span>now</span>
                            </div>
                            <p class="mt-1.5 break-words text-[14px] font-semibold leading-snug" :class="! title && 'text-white/60'" x-text="title || 'Your title'">Your title</p>
                            <p class="line-clamp-4 whitespace-pre-line break-words text-[13px] leading-snug text-white/90" :class="! body && 'text-white/60'" x-text="body || 'Your message shows here as you type.'">Your message shows here as you type.</p>
                        </div>

                        <div class="mt-auto flex items-center justify-between px-5 pb-5" aria-hidden="true">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/25">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M8 3h8v4l-2 3v10a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V10L8 7V3Z"/><path d="M12 13v2"/></svg>
                            </span>
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/25"><x-icon name="camera" class="h-5 w-5" /></span>
                        </div>
                        <span class="mx-auto mb-1 h-[5px] w-[110px] rounded-full bg-white/85" aria-hidden="true"></span>
                    </div>
                </div>
            </div>

            {{-- Email: the message as it lands in an inbox (resources/views/mail/announcement) --}}
            <div x-show="tab === 'email'" x-cloak class="mt-6 overflow-hidden rounded-card border border-surface-line" :class="! has('email') && 'opacity-50'">
                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 border-b border-surface-line bg-surface-page px-4 py-3 text-[13px]">
                    <dt class="text-ink-muted">From</dt>
                    <dd class="min-w-0 truncate">Expense Tracker &lt;{{ $from }}&gt;</dd>
                    <dt class="text-ink-muted">To</dt>
                    <dd class="min-w-0 truncate" x-text="audience === 'all' ? @js(\App\Support\Counts::indian($emailable) . ' ' . \Illuminate\Support\Str::plural('user', $emailable) . ' with emails on, one email each') : (email || 'The user you name')"></dd>
                    <dt class="text-ink-muted">Subject</dt>
                    <dd class="min-w-0 truncate font-semibold" :class="! title && 'font-normal text-ink-muted'" x-text="title || 'Your title'"></dd>
                </dl>
                <div class="bg-[#edf2f7] px-4 py-6 text-[14px] leading-relaxed text-[#718096]">
                    <p class="text-center text-[19px] font-bold text-[#3d4852]">Expense Tracker</p>
                    <div class="mx-auto mt-4 max-w-[480px] rounded bg-white p-5">
                        <p class="font-bold text-[#3d4852]">Hi [their name],</p>
                        <p class="mt-3 break-words text-[18px] font-bold text-[#3d4852]" x-text="title || 'Your title'"></p>
                        <p class="mt-2 whitespace-pre-line break-words" x-text="body || 'Your message shows here as you type.'"></p>
                        <p class="mt-5 text-center"><span class="inline-block rounded bg-[#2d3748] px-4 py-2 text-[13px] font-semibold text-white">Open Expense Tracker</span></p>
                        <p class="mt-5 border-t border-[#e8e5ef] pt-3 text-[12px]">You are getting this because email notifications are on for your Expense Tracker account. You can turn them off in Settings.</p>
                    </div>
                    <p class="mt-4 text-center text-[12px] text-[#b0adc5]">© {{ $now->year }} Expense Tracker</p>
                </div>
            </div>
        </section>
    </div>

    {{-- History --}}
    <section class="adm-card min-w-0">
        <h2 class="adm-h2">Sent</h2>
        <ul class="mt-2">
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
                        <span>To: {{ $item->audience === 'all' ? 'All users' : ($item->target ? mask_email($item->target->email) : 'deleted user') }}</span>
                        <span>By: {{ $item->sender?->name ?: ($item->sender ? mask_email($item->sender->email) : 'deleted admin') }}</span>
                        <span>{{ $item->created_at->timezone(config('app.display_timezone'))->format('j M Y, g:i A') }}</span>
                    </p>
                </li>
            @empty
                <li class="py-10 text-center text-ink-muted">No notifications sent yet.</li>
            @endforelse
        </ul>
        @if($history->hasPages())
            <div class="mt-4">{{ $history->links() }}</div>
        @endif
    </section>
</x-admin-layout>
