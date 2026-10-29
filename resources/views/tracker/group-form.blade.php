<x-app-layout>
    <x-slot name="title">{{ $group ? 'Edit group' : 'New group' }}</x-slot>
    @php
        $emoji  = old('emoji', $group?->emoji ?? \App\Support\Groups::EMOJIS[0]);
        $emojis = \App\Support\Groups::EMOJIS;
        // A group made in the app may use an emoji the website does not list.
        if (! in_array($emoji, $emojis, true)) array_unshift($emojis, $emoji);
        $back = $group ? route('groups.show', $group) : route('groups.index');
    @endphp

    <div class="space-y-4">
        <form method="POST" action="{{ $group ? route('groups.update', $group) : route('groups.store') }}" class="card space-y-5">
            @csrf
            @if($group) @method('PUT') @endif

            <div class="grid gap-x-10 gap-y-5 lg:grid-cols-2">
                <div x-data="{ name: @js(old('name', $group?->name ?? '')) }">
                    <label for="name" class="field-label flex justify-between">Name <span class="font-normal" x-text="name.length + '/40'"></span></label>
                    <input id="name" name="name" type="text" maxlength="40" required x-model="name" value="{{ old('name', $group?->name) }}" @unless($group) autofocus @endunless
                           placeholder="e.g. Goa trip, Flat 302, Office lunch" class="input">
                    @error('name')
                        <p class="field-error">{{ $message }}</p>
                    @else
                        @unless($group)<p class="mt-1 text-xs muted">You can add people by email once it is made.</p>@endunless
                    @enderror
                </div>

                <fieldset>
                    <legend class="field-label">Emoji</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach($emojis as $option)
                            <label>
                                <input type="radio" name="emoji" value="{{ $option }}" class="peer sr-only" @checked($emoji === $option)>
                                <span class="chip !px-2.5 text-xl leading-none" aria-label="{{ $option }}">{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('emoji')<p class="field-error">{{ $message }}</p>@enderror
                </fieldset>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ $back }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">{{ $group ? 'Save' : 'Create group' }}</button>
            </div>
        </form>

        @if($group && $canDelete)
            <form method="POST" action="{{ route('groups.destroy', $group) }}" class="text-center"
                  data-confirm-title="Delete {{ $group->name }}?"
                  data-confirm-message="Every expense and payment in it is removed for everyone in the group. This cannot be undone."
                  data-confirm-button="Delete group">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger-ghost"><x-icon name="trash" class="h-4 w-4" /> Delete group</button>
            </form>
        @endif
    </div>
</x-app-layout>
