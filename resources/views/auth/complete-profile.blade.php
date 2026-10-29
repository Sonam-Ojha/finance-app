<x-guest-layout>

    <div class="form-title">Complete your profile</div>
    <div class="form-subtitle">One last thing before you start: your date of birth.</div>

    <form method="POST" action="{{ route('profile.complete.update') }}">
        @csrf
        @method('PUT')

        <div class="field-group">
            <label class="field-label" for="birthday">Date of Birth</label>
            <div class="field-wrap">
                <input type="date" name="birthday" id="birthday"
                       value="{{ old('birthday') }}"
                       required autofocus autocomplete="bday"
                       min="{{ $oldest }}" max="{{ $youngest }}"
                       style="padding-right:14px"
                       aria-describedby="{{ $errors->has('birthday') ? 'birthday-error' : '' }}"
                       class="field-input {{ $errors->has('birthday') ? 'is-error' : '' }}">
            </div>
            @error('birthday')<div class="field-error" id="birthday-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn-primary">Continue &rarr;</button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <div class="divider">
            <div class="divider-line"></div>
            <span class="divider-text">NOT YOU?</span>
            <div class="divider-line"></div>
        </div>
        <button type="submit" class="btn-secondary" style="cursor:pointer; font-family:inherit">Sign out</button>
    </form>

</x-guest-layout>
