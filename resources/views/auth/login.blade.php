<x-guest-layout>

    <div class="form-title">Welcome back! 👋</div>
    <div class="form-subtitle">Sign in to your account</div>

    @if (session('status'))
        <div class="status-msg">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Email --}}
        <div class="field-group">
            <label class="field-label">Email Address</label>
            <div class="field-wrap">
                <input type="email" name="email" id="email"
                       value="{{ old('email') }}"
                       required autofocus autocomplete="username"
                       placeholder="your@email.com"
                       class="field-input {{ $errors->has('email') ? 'is-error' : '' }}">
                <span class="field-icon">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
            </div>
            @error('email')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        {{-- Password --}}
        <div class="field-group">
            <label class="field-label">Password</label>
            <div class="field-wrap">
                <input type="password" name="password" id="password"
                       required autocomplete="current-password"
                       placeholder="••••••••"
                       class="field-input {{ $errors->has('password') ? 'is-error' : '' }}">
                <span class="field-icon">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
            </div>
            @error('password')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        {{-- Remember + Forgot --}}
        <div class="row-between">
            <label class="check-label">
                <input type="checkbox" name="remember">
                Remember me
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
            @endif
        </div>

        <button type="submit" class="btn-primary">Sign In &rarr;</button>

        <div class="divider">
            <div class="divider-line"></div>
            <span class="divider-text">NEW USER?</span>
            <div class="divider-line"></div>
        </div>

        <a href="{{ route('register') }}" class="btn-secondary">
            Create a new account &rarr;
        </a>
    </form>

</x-guest-layout>
