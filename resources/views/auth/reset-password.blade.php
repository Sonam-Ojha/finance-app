<x-guest-layout>
    <div class="form-title">Choose a new password</div>
    <div class="form-subtitle">Use at least 8 characters. You will be signed out everywhere else.</div>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="field-group">
            <label class="field-label" for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username"
                   class="field-input {{ $errors->has('email') ? 'is-error' : '' }}" style="padding-right: 14px;">
            @error('email')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="field-group">
            <label class="field-label" for="password">New password</label>
            <input id="password" type="password" name="password" required autofocus autocomplete="new-password"
                   placeholder="At least 8 characters" class="field-input {{ $errors->has('password') ? 'is-error' : '' }}" style="padding-right: 14px;">
            @error('password')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="field-group">
            <label class="field-label" for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   placeholder="Re-enter your password" class="field-input {{ $errors->has('password_confirmation') ? 'is-error' : '' }}" style="padding-right: 14px;">
            @error('password_confirmation')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn-primary">Save new password</button>
    </form>
</x-guest-layout>
