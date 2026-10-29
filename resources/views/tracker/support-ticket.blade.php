<x-app-layout>
    <x-slot name="title">Help & support</x-slot>
    <x-slot name="heading">{{ $ticket->subject }}</x-slot>
    @php
        $label = ['open' => 'Waiting for a reply', 'answered' => 'Replied', 'closed' => 'Closed'];
        $at = fn ($time) => \App\Support\Labels::dayHeading($time->copy()->timezone(config('app.display_timezone'))->toDateString())
            . ', ' . $time->copy()->timezone(config('app.display_timezone'))->format('g:i A');
    @endphp
    <x-slot name="subtitle">{{ $label[$ticket->status] }} · asked {{ $at($ticket->created_at) }}</x-slot>
    <x-slot name="actions">
        <a href="{{ route('support.index') }}" class="btn-secondary"><x-icon name="chevron-left" class="w-5 h-5" /> All requests</a>
    </x-slot>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section class="card space-y-4" aria-label="Conversation">
            <ol class="space-y-4">
                @foreach($ticket->messages as $message)
                    @php $support = $message->fromSupport(); @endphp
                    <li class="flex {{ $support ? 'justify-start' : 'justify-end' }}">
                        <div class="max-w-[85%]">
                            <p class="mb-1 text-xs muted {{ $support ? '' : 'text-right' }}">
                                <span class="font-semibold strong">{{ $support ? 'Support' : 'You' }}</span> · {{ $at($message->created_at) }}
                            </p>
                            <div class="whitespace-pre-line break-words rounded-card px-4 py-3 text-sm {{ $support ? 'bg-surface-page text-ink dark:bg-night-line dark:text-night-ink' : 'bg-brand-navy text-white dark:bg-brand-cyan dark:text-brand-navy' }}">{{ $message->body }}</div>
                            @if($message->screenshot_path)
                                @php $src = route('support.screenshot', [$ticket, $message]); @endphp
                                <button type="button" class="mt-2 ml-auto block overflow-hidden rounded-card border border-surface-field dark:border-night-field"
                                        @click="$dispatch('open-photo', { src: @js($src), name: 'Your screenshot', contain: true })" aria-label="Open your screenshot">
                                    <img src="{{ $src }}" alt="Your screenshot" loading="lazy" class="max-h-48 w-auto object-contain">
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="card space-y-4" aria-labelledby="write-back">
            <h2 id="write-back" class="card-title">{{ $ticket->isClosed() ? 'Need more help?' : 'Write back' }}</h2>
            @if($ticket->isClosed())
                <p class="text-sm muted">This request is closed. Writing again opens it, and we will pick it up.</p>
            @elseif($ticket->status === 'open')
                <p class="text-sm muted">We have your message and will reply here and by email. Add anything that might help.</p>
            @endif
            <form method="POST" action="{{ route('support.messages.store', $ticket) }}" class="space-y-4" x-data="{ message: @js(old('message', '')) }">
                @csrf
                <div>
                    <label for="message" class="field-label">Message</label>
                    <textarea id="message" name="message" rows="5" maxlength="5000" x-model="message" required class="input py-2" placeholder="Type your message…"></textarea>
                    @error('message')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary"><x-icon name="envelope" class="w-5 h-5" /> Send</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
