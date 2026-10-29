<x-app-layout>
    <x-slot name="title">{{ $hasPassword ? 'Change password' : 'Set a password' }}</x-slot>

    <form method="POST" action="{{ route('account.password.update') }}" class="max-w-2xl mx-auto card space-y-5"
          x-data="{ next: '', again: '' }">
        @csrf @method('PUT')

        <h2 class="page-title">{{ $hasPassword ? 'Change password' : 'Set a password' }}</h2>

        @if($hasPassword)
            <div>
                <label for="current_password" class="field-label">Current password</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="input" required>
                @error('current_password')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        @else
            <p class="text-sm muted">You sign in with Google. A password lets you sign in with your email too.</p>
        @endif

        <div>
            <label for="new_password" class="field-label">New password</label>
            <input id="new_password" name="new_password" type="password" autocomplete="new-password" x-model="next" class="input" required>
            @error('new_password')
                <p class="field-error">{{ $message }}</p>
            @else
                <p class="mt-1 text-xs muted">At least 8 characters, with a letter and a number.</p>
            @enderror
        </div>

        <div>
            <label for="new_password_confirmation" class="field-label">Confirm new password</label>
            <input id="new_password_confirmation" name="new_password_confirmation" type="password" autocomplete="new-password" x-model="again"
                   placeholder="Type the new password again" class="input" required>
            <p class="field-error" x-show="again.length >= next.length && again !== '' && again !== next" x-cloak>The two new passwords do not match.</p>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('settings.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary" :disabled="! next || ! again">Save</button>
        </div>
    </form>
</x-app-layout>
