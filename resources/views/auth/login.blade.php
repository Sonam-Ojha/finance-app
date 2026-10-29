<x-guest-layout>
    <x-slot name="title">Sign in – Expense Tracker</x-slot>

    <h1 class="form-title">Welcome back</h1>
    <p class="form-subtitle">Sign in to your Expense Tracker account.</p>

    @if (session('status'))
        <div class="status-msg" role="status">{{ session('status') }}</div>
    @endif

    @include('auth.partials.google-button', ['context' => 'signin'])

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="field-group">
            <label class="field-label" for="email">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username" placeholder="you@example.com"
                   class="field-input {{ $errors->has('email') ? 'is-error' : '' }}">
            @error('email')<div class="field-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="field-group">
            <label class="field-label" for="password">Password</label>
            <div class="field-wrap">
                <input type="password" name="password" id="password"
                       required autocomplete="current-password"
                       class="field-input {{ $errors->has('password') ? 'is-error' : '' }}">
            </div>
            @error('password')<div class="field-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="row-between">
            <label class="check-label">
                <input type="checkbox" name="remember">
                Remember me
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
            @endif
        </div>

        <button type="submit" class="btn-primary">Sign in</button>
    </form>

    <x-slot name="footer">New here? <a href="{{ route('register') }}">Create an account</a></x-slot>
</x-guest-layout>
