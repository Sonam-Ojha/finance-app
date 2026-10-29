<x-admin-layout>
    <x-slot name="title">Support request</x-slot>
    <x-slot name="heading">{{ $ticket->subject }}</x-slot>
    @php
        $badge = [
            'open'     => ['adm-badge-medium', 'Waiting for you'],
            'answered' => ['adm-badge-low', 'Answered'],
            'closed'   => ['adm-badge bg-surface-page text-ink-muted', 'Closed'],
        ];
        $user = $ticket->user;
    @endphp
    <x-slot name="subtitle">
        <span class="font-mono">#{{ $ticket->id }}</span> · asked {{ \App\Http\Controllers\Admin\DashboardController::when($ticket->created_at, true) }}
        · <span class="{{ $badge[$ticket->status][0] }} align-middle">{{ $badge[$ticket->status][1] }}</span>
    </x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.support.index') }}" class="adm-btn-outline !h-11"><x-icon name="chevron-left" class="h-5 w-5" /> All requests</a>
    </x-slot>

    <div class="flex flex-wrap items-start gap-4">
        {{-- The conversation, and the reply box --}}
        <section class="adm-card flex min-w-0 flex-[2_1_520px] flex-col gap-4" aria-label="Conversation">
            <ol class="flex flex-col gap-4">
                @foreach($ticket->messages as $message)
                    @php $support = $message->fromSupport(); @endphp
                    <li class="flex {{ $support ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[85%]">
                            <p class="adm-caption mb-1 {{ $support ? 'text-right' : '' }}">
                                <span class="font-semibold text-ink">{{ $support ? ($message->author_id === auth()->id() ? 'You' : ($message->author?->name ?: 'Support')) : ($user?->name ?: 'User') }}</span>
                                · {{ \App\Http\Controllers\Admin\DashboardController::when($message->created_at, true) }}
                            </p>
                            <div class="whitespace-pre-line break-words rounded-card px-4 py-3 text-sm {{ $support ? 'bg-brand-navy text-white' : 'bg-surface-page text-ink' }}">{{ $message->body }}</div>
                            @if($message->screenshot_path)
                                @php $src = route('admin.support.screenshot', [$ticket, $message]); @endphp
                                <button type="button" class="mt-2 block overflow-hidden rounded-card border border-surface-field hover:border-brand-navy"
                                        @click="$dispatch('open-photo', { src: @js($src), name: 'Screenshot', contain: true })"
                                        aria-label="Open the screenshot">
                                    <img src="{{ $src }}" alt="Screenshot sent with this message" loading="lazy" class="max-h-48 w-auto object-contain">
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>

            <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" class="flex flex-col gap-3 border-t border-surface-page pt-4"
                  x-data="{ message: @js(old('message', '')) }">
                @csrf
                <div>
                    <label for="reply" class="adm-label">Your reply</label>
                    <textarea id="reply" name="message" rows="5" maxlength="5000" x-model="message" required
                              placeholder="Write to {{ $user?->name ?: 'them' }}…" class="adm-input h-auto w-full py-2"></textarea>
                    <p class="adm-caption mt-1 flex justify-between gap-2">
                        <span>Sent by email and to their phone, and shown in Help & support.</span>
                        <span><span x-text="message.length"></span>/5000</span>
                    </p>
                    @error('message')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="adm-btn-primary self-start" @disabled(! $user)>
                    <x-icon name="envelope" class="h-5 w-5" /> Send reply
                </button>
                @unless($user)
                    <p class="adm-caption">This account was deleted, so a reply cannot reach anyone.</p>
                @endunless
            </form>
        </section>

        <div class="flex flex-[1_1_300px] flex-col gap-4">
            {{-- Who asked: name and email only --}}
            <section class="adm-card">
                <h2 class="adm-h2">From</h2>
                @if($user)
                    <div class="mt-3 flex items-center gap-3">
                        <x-admin.avatar :user="$user" />
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $user->name ?: '—' }} <span class="font-mono text-[13px] font-normal text-ink-muted">#{{ $user->id }}</span></p>
                            <p class="adm-caption truncate">{{ $user->email }}</p>
                        </div>
                    </div>
                    <p class="adm-caption mt-3">Joined {{ $user->created_at?->timezone(config('app.display_timezone'))->format('j M Y') }}</p>
                    <a href="{{ route('admin.users.index', ['q' => $user->email]) }}" class="adm-link mt-2 inline-block">Find in Users</a>
                @else
                    <p class="mt-3 text-sm text-ink-muted">This account has been deleted.</p>
                @endif
            </section>

            <section class="adm-card flex flex-col gap-3">
                <h2 class="adm-h2">Status</h2>
                <p class="text-sm">
                    @if($ticket->status === 'open')
                        Waiting for your reply.
                    @elseif($ticket->status === 'answered')
                        Support replied last. It opens again if they write back.
                    @else
                        Closed{{ $ticket->closed_at ? ' ' . \App\Http\Controllers\Admin\DashboardController::when($ticket->closed_at, true) : '' }}. It opens again if they write back.
                    @endif
                </p>
                @if($ticket->isClosed())
                    <form method="POST" action="{{ route('admin.support.reopen', $ticket) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="adm-btn-outline w-full">Reopen</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.support.close', $ticket) }}"
                          data-confirm-title="Close this request?"
                          data-confirm-message="It moves to Closed. If they write again, it opens again."
                          data-confirm-button="Close request">
                        @csrf @method('PATCH')
                        <button type="submit" class="adm-btn-outline w-full">Close request</button>
                    </form>
                @endif
            </section>

            @if($history->isNotEmpty())
                <section class="adm-card">
                    <h2 class="adm-h2">Their other requests</h2>
                    <ul class="mt-2">
                        @foreach($history as $other)
                            <li class="flex items-center justify-between gap-3 border-b border-surface-page py-2.5 last:border-0">
                                <a href="{{ route('admin.support.show', $other) }}" class="min-w-0 truncate text-sm font-semibold text-brand-navy hover:underline">{{ $other->subject }}</a>
                                <span class="{{ $badge[$other->status][0] }}">{{ $badge[$other->status][1] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-admin-layout>
