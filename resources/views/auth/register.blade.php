<x-guest-layout>
    <x-slot name="title">Create an account – Expense Tracker</x-slot>

    <h1 class="form-title">Create your account</h1>
    <p class="form-subtitle">Free, and ready in a minute.</p>

    @if (session('status'))
        <div class="status-msg" role="status">{{ session('status') }}</div>
    @endif

    @include('auth.partials.google-button', ['context' => 'signup'])

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="field-group">
            <label class="field-label" for="name">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}"
                   required autofocus autocomplete="name" placeholder="Your name"
                   class="field-input {{ $errors->has('name') ? 'is-error' : '' }}">
            @error('name')<div class="field-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="field-group">
            <label class="field-label" for="email">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                   required autocomplete="username" placeholder="you@example.com"
                   class="field-input {{ $errors->has('email') ? 'is-error' : '' }}">
            @error('email')<div class="field-error" role="alert">{{ $message }}</div>@enderror
        </div>

        {{-- Required for a new account (at least 1, at most 120 years old) --}}
        <div class="field-group">
            <label class="field-label" for="birthday">Date of birth</label>
            <input type="date" name="birthday" id="birthday" value="{{ old('birthday') }}"
                   required autocomplete="bday" min="{{ $oldest ?? '' }}" max="{{ $youngest ?? '' }}"
                   class="field-input {{ $errors->has('birthday') ? 'is-error' : '' }}">
            @error('birthday')<div class="field-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="field-group">
            <label class="field-label" for="password">Password</label>
            <div class="field-wrap">
                <input type="password" name="password" id="password"
                       required autocomplete="new-password" placeholder="At least 8 characters"
                       class="field-input {{ $errors->has('password') ? 'is-error' : '' }}">
            </div>
            @error('password')<div class="field-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="field-group" style="margin-bottom: 22px">
            <label class="field-label" for="password_confirmation">Confirm password</label>
            <div class="field-wrap">
                <input type="password" name="password_confirmation" id="password_confirmation"
                       required autocomplete="new-password"
                       class="field-input {{ $errors->has('password_confirmation') ? 'is-error' : '' }}">
            </div>
            @error('password_confirmation')<div class="field-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn-primary">Create account</button>
    </form>

    <x-slot name="footer">Already have an account? <a href="{{ route('login') }}">Sign in</a></x-slot>
</x-guest-layout>
