<x-app-layout>
    <x-slot name="title">Help & support</x-slot>
    <x-slot name="subtitle">Stuck, found a bug, or have an idea? Write to us — we reply here and by email.</x-slot>
    @php
        $label = ['open' => 'Waiting for a reply', 'answered' => 'Replied', 'closed' => 'Closed'];
    @endphp

    <div class="grid items-start gap-6 lg:grid-cols-2">
        {{-- Ask --}}
        <section class="card space-y-5" aria-labelledby="new-request"
                 x-data="{
                     subject: @js(old('subject', '')),
                     message: @js(old('message', '')),
                     preview: null,
                     name: '',
                     pick(event) {
                         const file = event.target.files[0];
                         if (this.preview) URL.revokeObjectURL(this.preview);
                         this.preview = file ? URL.createObjectURL(file) : null;
                         this.name = file ? file.name : '';
                     },
                     clear() {
                         if (this.preview) URL.revokeObjectURL(this.preview);
                         this.preview = null; this.name = ''; this.$refs.file.value = '';
                     },
                 }">
            <h2 id="new-request" class="card-title">New request</h2>
            <form method="POST" action="{{ route('support.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <div>
                    <label for="subject" class="field-label flex justify-between">Subject <span class="font-normal muted" x-text="subject.length + '/120'"></span></label>
                    <input id="subject" name="subject" type="text" maxlength="120" x-model="subject" required placeholder="e.g. My budget alert did not arrive" class="input">
                    @error('subject')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="message" class="field-label">What happened?</label>
                    <textarea id="message" name="message" rows="6" maxlength="5000" x-model="message" required
                              placeholder="Tell us what you did, what you expected and what you saw instead." class="input py-2"></textarea>
                    @error('message')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <span class="field-label">Screenshot <span class="font-normal muted">(optional)</span></span>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="btn-secondary cursor-pointer">
                            <x-icon name="camera" class="w-5 h-5" /> <span x-text="preview ? 'Change' : 'Add a screenshot'">Add a screenshot</span>
                            <input x-ref="file" type="file" name="screenshot" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick($event)">
                        </label>
                        <template x-if="preview">
                            <div class="flex items-center gap-3">
                                <img :src="preview" alt="The screenshot you picked" class="h-16 w-16 rounded-lg object-cover">
                                <button type="button" class="link text-sm" @click="clear()">Remove</button>
                            </div>
                        </template>
                    </div>
                    @error('screenshot')
                        <p class="field-error">{{ $message }}</p>
                    @else
                        <p class="mt-1 text-xs muted">A JPEG, PNG or WebP image up to 5 MB. Only you and our support team can see it.</p>
                    @enderror
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary"><x-icon name="envelope" class="w-5 h-5" /> Send request</button>
                </div>
            </form>
        </section>

        {{-- Past requests --}}
        <section class="space-y-3" aria-labelledby="your-requests">
            <h2 id="your-requests" class="section-label px-1">Your requests</h2>
            @forelse($tickets as $ticket)
                <a href="{{ route('support.show', $ticket) }}" class="card flex items-start gap-3 hover:ring-2 hover:ring-brand-navy/30 dark:hover:ring-brand-cyan/40">
                    <span class="icon-tile"><x-icon name="envelope" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="truncate font-semibold strong">{{ $ticket->subject }}</span>
                            @if($ticket->unread_by_user)
                                <span class="flex-shrink-0 rounded-full bg-status-error px-2 py-0.5 text-[11px] font-semibold text-white">New reply</span>
                            @endif
                        </span>
                        <span class="mt-0.5 block text-[13px] muted">
                            {{ $label[$ticket->status] }} · {{ $ticket->messages_count }} {{ \Illuminate\Support\Str::plural('message', $ticket->messages_count) }}
                            · {{ \App\Support\Labels::dayHeading($ticket->updated_at->timezone(config('app.display_timezone'))->toDateString()) }}
                        </span>
                    </span>
                    <x-icon name="chevron-right" class="mt-1 w-4 h-4 muted" />
                </a>
            @empty
                <div class="card py-10 text-center">
                    <p class="text-4xl" aria-hidden="true">💬</p>
                    <p class="mt-3 font-semibold strong">No requests yet</p>
                    <p class="mt-1 text-sm muted">When you write to us, your requests and our replies show up here.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-app-layout>
