<x-app-layout>
    <x-slot name="title">Account information</x-slot>

    <div class="max-w-2xl space-y-4">
        {{-- Photo: saved as soon as it is picked --}}
        <div class="card flex items-center gap-4" x-data="{ busy: false, failed: false }">
            @if($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="Your photo" class="w-20 h-20 rounded-full object-cover">
            @else
                <span class="w-20 h-20 rounded-full bg-surface-page dark:bg-night-line flex items-center justify-center muted"><x-icon name="user" class="w-9 h-9" /></span>
            @endif
            <div class="space-y-2">
                <form method="POST" action="{{ route('profile.photo') }}" x-ref="photoForm">
                    @csrf
                    <input type="hidden" name="avatar" x-ref="avatar">
                    <label class="btn-secondary cursor-pointer" :class="busy && 'opacity-50 pointer-events-none'">
                        <x-icon name="camera" class="w-4 h-4" /> {{ $avatarUrl ? 'Change photo' : 'Add photo' }}
                        <input type="file" accept="image/*" class="sr-only"
                               @change="if ($event.target.files[0]) { busy = true; failed = false; photoToDataUri($event.target.files[0]).then(uri => { $refs.avatar.value = uri; $refs.photoForm.submit(); }).catch(() => { busy = false; failed = true; }); }">
                    </label>
                </form>
                @if($avatarUrl)
                    <form method="POST" action="{{ route('profile.photo.destroy') }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-sm text-status-error dark:text-red-400 hover:underline">Remove</button>
                    </form>
                @endif
                <p class="field-error" x-show="failed" x-cloak>Could not use that photo. Try a different one.</p>
                @error('avatar')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="card space-y-5">
            @csrf @method('PATCH')

            <div>
                <label for="name" class="field-label">Name</label>
                <input id="name" name="name" type="text" maxlength="40" value="{{ old('name', $user->name) }}" placeholder="Your name" class="input">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="birthday" class="field-label">Date of birth</label>
                <input id="birthday" name="birthday" type="date" min="{{ $oldest }}" max="{{ $youngest }}"
                       value="{{ old('birthday', $user->birthday?->toDateString()) }}" class="input">
                @error('birthday')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" class="input" required>
                @error('email')
                    <p class="field-error">{{ $message }}</p>
                @else
                    <p class="mt-1 text-xs muted">This is the email you sign in with — password reset links go here, so keep it one you can read.</p>
                @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>

        {{-- Delete account --}}
        <div class="text-center" x-data="{ open: @js($errors->userDeletion->isNotEmpty()) }">
            <button type="button" @click="open = true" class="btn-danger-ghost">Delete account</button>

            <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-4" @click.self="open = false" @keydown.escape.window="open = false">
                <form method="POST" action="{{ route('profile.destroy') }}" class="card w-full max-w-sm text-left space-y-4" role="dialog" aria-modal="true" aria-labelledby="delete-title"
                      @submit="$refs.deleteButton.disabled = true; $refs.deleteButton.textContent = 'Deleting...'">
                    @csrf @method('DELETE')
                    <h2 id="delete-title" class="text-base font-semibold strong">Delete your account?</h2>
                    @if($user->has_password)
                        <p class="text-sm muted">Enter your password to permanently delete your account and all data.</p>
                        <div>
                            <input name="password" type="password" placeholder="Your password" class="input" autocomplete="current-password">
                            @error('password', 'userDeletion')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    @else
                        <p class="text-sm muted">This permanently deletes your account and all its data. It cannot be undone.</p>
                    @endif
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="open = false">Cancel</button>
                        <button type="submit" class="btn-danger" x-ref="deleteButton">Delete my account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
