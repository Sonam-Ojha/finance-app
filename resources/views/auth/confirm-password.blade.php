<x-guest-layout>
    <div class="form-title">Confirm your password</div>
    <div class="form-subtitle">This is a protected area. Type your password to carry on.</div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="field-group">
            <label class="field-label" for="password">Password</label>
            <input id="password" type="password" name="password" required autofocus autocomplete="current-password"
                   class="field-input {{ $errors->has('password') ? 'is-error' : '' }}" style="padding-right: 14px;">
            @error('password')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn-primary">Confirm</button>
    </form>
</x-guest-layout>
