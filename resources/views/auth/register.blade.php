<x-guest-layout>

    <div class="form-title">Account Banao ✨</div>
    <div class="form-subtitle">Join for free and start tracking your finances</div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        {{-- Name --}}
        <div class="field-group">
            <label class="field-label">Full Name</label>
            <div class="field-wrap">
                <input type="text" name="name" id="name"
                       value="{{ old('name') }}"
                       required autofocus autocomplete="name"
                       placeholder="Your full name"
                       class="field-input {{ $errors->has('name') ? 'is-error' : '' }}">
                <span class="field-icon">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </span>
            </div>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        {{-- Email --}}
        <div class="field-group">
            <label class="field-label">Email Address</label>
            <div class="field-wrap">
                <input type="email" name="email" id="email"
                       value="{{ old('email') }}"
                       required autocomplete="username"
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
                       required autocomplete="new-password"
                       placeholder="Min 8 characters"
                       class="field-input {{ $errors->has('password') ? 'is-error' : '' }}">
                <span class="field-icon">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
            </div>
            @error('password')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        {{-- Confirm Password --}}
        <div class="field-group">
            <label class="field-label">Confirm Password</label>
            <div class="field-wrap">
                <input type="password" name="password_confirmation" id="password_confirmation"
                       required autocomplete="new-password"
                       placeholder="Re-enter your password"
                       class="field-input {{ $errors->has('password_confirmation') ? 'is-error' : '' }}">
                <span class="field-icon">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </span>
            </div>
            @error('password_confirmation')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn-primary" style="margin-top:4px">Create Account &rarr;</button>

        <div class="divider">
            <div class="divider-line"></div>
            <span class="divider-text">ALREADY A MEMBER?</span>
            <div class="divider-line"></div>
        </div>

        <a href="{{ route('login') }}" class="btn-secondary">Already have an account? Sign In &rarr;</a>
    </form>

</x-guest-layout>
