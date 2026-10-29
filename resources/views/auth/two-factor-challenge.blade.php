<x-guest-layout>

    <div class="form-title">Two-step check</div>
    <div class="form-subtitle">Enter the 6-digit code from your authenticator app. Lost your phone? Use one of your recovery codes instead.</div>

    <form method="POST" action="{{ route('two-factor.challenge.store') }}">
        @csrf

        <div class="field-group">
            <label class="field-label" for="code">Authentication or recovery code</label>
            <div class="field-wrap">
                <input type="text" name="code" id="code"
                       required autofocus autocomplete="one-time-code" inputmode="text"
                       maxlength="32" spellcheck="false" autocapitalize="off"
                       placeholder="123456"
                       aria-describedby="{{ $errors->has('code') ? 'code-error' : '' }}"
                       style="letter-spacing:.15em; font-variant-numeric:tabular-nums"
                       class="field-input {{ $errors->has('code') ? 'is-error' : '' }}">
                <span class="field-icon">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
            </div>
            @error('code')<div class="field-error" id="code-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn-primary">Verify</button>

        <div class="divider">
            <div class="divider-line"></div>
            <span class="divider-text">Not you?</span>
            <div class="divider-line"></div>
        </div>

        <a href="{{ route('login') }}" class="btn-secondary">Back to sign in</a>
    </form>

</x-guest-layout>
