<x-guest-layout>
    <div class="form-title">Forgot your password?</div>
    <div class="form-subtitle">Type your email and we will send you a link to choose a new one.</div>

    @if (session('status'))
        <div class="status-msg" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="field-group">
            <label class="field-label" for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   placeholder="your@email.com" class="field-input {{ $errors->has('email') ? 'is-error' : '' }}" style="padding-right: 14px;">
            @error('email')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn-primary">Email me a reset link</button>
    </form>

    <div class="divider">
        <div class="divider-line"></div>
        <span class="divider-text">Remembered it?</span>
        <div class="divider-line"></div>
    </div>

    <a href="{{ route('login') }}" class="btn-secondary">Back to sign in</a>
</x-guest-layout>
