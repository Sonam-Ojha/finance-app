<x-app-layout>
    <x-slot name="title">Send notification</x-slot>
    <div class="space-y-4">
        <div>
            <h2 class="text-lg font-bold text-slate-800">Notifications</h2>
            <p class="text-xs text-slate-500">
                {{ $devices }} {{ \Illuminate\Support\Str::plural('phone', $devices) }} signed in, from {{ $reachable }} {{ \Illuminate\Support\Str::plural('user', $reachable) }}.
                Every message is also kept in the app's notification list.
            </p>
        </div>

        @unless($configured)
            <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-sm">
                <svg class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <div>
                    <p class="font-semibold">Push notifications are not set up yet.</p>
                    <p class="text-amber-700">Messages are saved to the app's notification list, but no phone gets a push until the Firebase service-account key is on the server (<code class="text-xs">storage/app/firebase-credentials.json</code>).</p>
                </div>
            </div>
        @endunless

        <div class="grid gap-4 lg:grid-cols-5">
            {{-- Compose --}}
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-5 self-start"
                 x-data="{ audience: '{{ old('audience', 'all') }}', title: @js(old('title', '')), body: @js(old('body', '')) }">
                <h3 class="font-semibold text-slate-800 mb-3">New notification</h3>
                <form method="POST" action="{{ route('admin.notifications.store') }}" class="space-y-4"
                      onsubmit="return confirm(this.audience.value === 'all' ? 'Send this to ALL users?' : 'Send this notification?')">
                    @csrf
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input id="title" type="text" name="title" x-model="title" maxlength="100" required
                               placeholder="e.g. New feature: monthly budgets"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <p class="text-[11px] text-slate-400 mt-1 text-right"><span x-text="title.length"></span>/100</p>
                    </div>
                    <div>
                        <label for="body" class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                        <textarea id="body" name="body" rows="4" x-model="body" maxlength="500" required
                                  placeholder="What should people know?"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                        <p class="text-[11px] text-slate-400 mt-1 text-right"><span x-text="body.length"></span>/500</p>
                    </div>
                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-700 mb-2">Send to</legend>
                        <div class="flex gap-2">
                            <label class="flex-1 flex items-center gap-2 border rounded-lg px-3 py-2 text-sm cursor-pointer"
                                   :class="audience === 'all' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600'">
                                <input type="radio" name="audience" value="all" x-model="audience" class="text-indigo-600"> All users
                            </label>
                            <label class="flex-1 flex items-center gap-2 border rounded-lg px-3 py-2 text-sm cursor-pointer"
                                   :class="audience === 'user' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600'">
                                <input type="radio" name="audience" value="user" x-model="audience" class="text-indigo-600"> One user
                            </label>
                        </div>
                    </fieldset>
                    <div x-show="audience === 'user'" x-cloak>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">User's email *</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               :required="audience === 'user'" placeholder="someone@example.com"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-indigo-700">
                        Send notification
                    </button>
                </form>
            </div>

            {{-- History --}}
            <div class="lg:col-span-3 space-y-2">
                <h3 class="font-semibold text-slate-600">Sent</h3>
                @forelse($history as $item)
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800">{{ $item->title }}</p>
                                <p class="text-sm text-slate-600 mt-0.5 whitespace-pre-line break-words">{{ $item->body }}</p>
                            </div>
                            @if($item->status === \App\Models\AdminNotification::STATUS_NOT_CONFIGURED)
                                <span class="flex-shrink-0 bg-amber-100 text-amber-700 px-2 py-0.5 rounded-md text-xs font-semibold">Inbox only</span>
                            @elseif($item->failed > 0)
                                <span class="flex-shrink-0 bg-red-100 text-red-700 px-2 py-0.5 rounded-md text-xs font-semibold">{{ $item->delivered }}/{{ $item->devices }} delivered</span>
                            @else
                                <span class="flex-shrink-0 bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-md text-xs font-semibold">{{ $item->delivered }}/{{ $item->devices }} delivered</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-400 mt-2">
                            <span>To: {{ $item->audience === 'all' ? 'All users' : ($item->target?->email ?? 'deleted user') }}</span>
                            <span>By: {{ $item->sender?->email ?? 'deleted admin' }}</span>
                            <span>{{ $item->created_at->timezone(config('app.display_timezone'))->format('d M Y, h:i A') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-gray-400 bg-white rounded-xl shadow-sm">
                        <p class="text-4xl mb-2">📣</p>
                        <p>No notifications sent yet</p>
                    </div>
                @endforelse
                {{ $history->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
