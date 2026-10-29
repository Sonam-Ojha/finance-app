{{-- The 6-digit code page (Auth\EmailCodeController). Every signed-in page sends an unverified account here. --}}
<x-guest-layout>
    <div class="form-title">Check your email</div>
    <div class="form-subtitle">We sent a 6-digit code to <strong>{{ $email }}</strong>. Type it below to confirm the address is yours.</div>

    @if (session('status'))
        <div class="status-msg" role="status">{{ session('status') }}</div>
    @endif
    @if ($sendError)
        <div class="field-error" role="alert" style="font-size: 13px; margin-bottom: 12px;">{{ $sendError }}</div>
    @endif

    <form method="POST" action="{{ route('verification.code') }}">
        @csrf
        <div class="field-group">
            <label class="field-label" for="code">6-digit code</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="7"
                   required autofocus placeholder="••••••"
                   class="field-input {{ $errors->has('code') ? 'is-error' : '' }}"
                   style="padding-left: 14px; text-align: center; font-size: 22px; font-weight: 700; letter-spacing: .5em;">
            @error('code')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn-primary">Verify email</button>
    </form>

    {{-- Another code: at most one a minute --}}
    <form method="POST" action="{{ route('verification.send') }}" style="margin-top: 16px; text-align: center;"
          x-data="{ wait: {{ (int) $retryAfter }} }" x-init="const t = setInterval(() => { if (wait > 0) wait--; else clearInterval(t) }, 1000)">
        @csrf
        <span style="font-size: 13px; color: var(--muted);">Did not get it? Check Spam, or</span>
        <button type="submit" class="forgot-link" style="background: none; border: 0; cursor: pointer;" :disabled="wait > 0" :style="wait > 0 && 'opacity:.5;cursor:default'">
            <span x-show="wait === 0">send a new code</span>
            <span x-show="wait > 0" x-cloak>send again in <span x-text="wait"></span>s</span>
        </button>
    </form>

    <div class="divider">
        <div class="divider-line"></div>
        <span class="divider-text">Wrong email?</span>
        <div class="divider-line"></div>
    </div>

    <form method="POST" action="{{ route('verification.email') }}">
        @csrf @method('PATCH')
        <div class="field-group">
            <label class="field-label" for="email">Correct email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="your@email.com"
                   class="field-input {{ $errors->has('email') ? 'is-error' : '' }}" style="padding-left: 14px;">
            @error('email')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn-secondary" style="width: 100%;">Send the code to this email</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" style="margin-top: 16px; text-align: center;">
        @csrf
        <button type="submit" class="forgot-link" style="background: none; border: 0; cursor: pointer;">Sign out</button>
    </form>
</x-guest-layout>
