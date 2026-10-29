<x-admin-layout>
    <x-slot name="title">Notifications</x-slot>

    <x-slot name="subtitle">
        {{ \App\Support\Counts::indian($devices) }} {{ \Illuminate\Support\Str::plural('phone', $devices) }} signed in, from {{ \App\Support\Counts::indian($reachable) }} {{ \Illuminate\Support\Str::plural('user', $reachable) }}.
        Every message is also kept in the app's notification list.
    </x-slot>

    @unless($configured)
        <div class="adm-strip" role="status">
            <x-icon name="warning" class="mt-0.5 h-5 w-5 flex-shrink-0 text-status-error" />
            <div>
                <p class="font-semibold text-brand-navy">Push notifications are not set up yet.</p>
                <p class="text-ink-muted">Messages are saved to the app's notification list, but no phone gets a push until the Firebase service-account key is on the server (<code class="font-mono text-[13px]">storage/app/firebase-credentials.json</code>).</p>
            </div>
        </div>
    @endunless

    <div class="flex flex-wrap items-start gap-4">
        {{-- Compose --}}
        <section class="adm-card flex-[1_1_320px]"
                 x-data="{ audience: @js(old('audience', 'all')), title: @js(old('title', '')), body: @js(old('body', '')) }">
            <h2 class="adm-h2">New notification</h2>
            <form method="POST" action="{{ route('admin.notifications.store') }}" class="mt-4 flex flex-col gap-4"
                  data-confirm-title="Send this notification?"
                  :data-confirm-message="audience === 'all' ? 'It goes to every user, and every signed-in phone gets a push.' : 'It goes to the one user you named.'"
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
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           :required="audience === 'user'" placeholder="someone@example.com" class="adm-input w-full">
                    @error('email')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="adm-btn-primary w-full"><x-icon name="megaphone" class="h-5 w-5" /> Send notification</button>
            </form>
        </section>

        {{-- History --}}
        <section class="adm-card flex-[2_1_480px] min-w-0">
            <h2 class="adm-h2">Sent</h2>
            <ul class="mt-2">
                @forelse($history as $item)
                    <li class="border-b border-surface-page py-4 last:border-0">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $item->title }}</p>
                                <p class="mt-0.5 whitespace-pre-line break-words text-ink-muted">{{ $item->body }}</p>
                            </div>
                            @if($item->status === \App\Models\AdminNotification::STATUS_NOT_CONFIGURED)
                                <span class="adm-badge-medium flex-shrink-0">Inbox only</span>
                            @elseif($item->failed > 0)
                                <span class="adm-badge-high flex-shrink-0">{{ $item->delivered }}/{{ $item->devices }} delivered</span>
                            @else
                                <span class="adm-badge-low flex-shrink-0">{{ $item->delivered }}/{{ $item->devices }} delivered</span>
                            @endif
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
    </div>
</x-admin-layout>
