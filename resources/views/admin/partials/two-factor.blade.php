{{-- Admin › Settings › Two-factor authentication (App\Http\Controllers\Admin\TwoFactorController). --}}
@php
    $me = auth()->user();
    $on = $me->hasTwoFactor();
    $settingUp = \App\Support\TwoFactor::pending($me);
    $tf = $errors->getBag('twoFactor');
    $newCodes = session('two_factor_recovery_codes');
@endphp
<section id="two-factor" class="adm-card lg:col-span-2" aria-labelledby="two-factor-title">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="two-factor-title" class="adm-h2">Two-factor authentication</h2>
        @if($on)
            <span class="adm-badge bg-status-success text-white">On</span>
        @elseif($settingUp)
            <span class="adm-badge-medium">Setting up</span>
        @else
            <span class="adm-badge-low">Off</span>
        @endif
    </div>

    @if($on)
        <p class="adm-caption mt-1">
            On since {{ $me->two_factor_confirmed_at->timezone(config('app.display_timezone'))->format('j M Y') }}.
            After your password, signing in on the website asks for the 6-digit code from your authenticator app.
        </p>

        @if($newCodes)
            <div class="mt-4 rounded-card border border-surface-field p-4" role="region" aria-label="Your recovery codes">
                <p class="text-sm font-semibold">Save these recovery codes now. They will not be shown again.</p>
                <p class="adm-caption mt-1">If you lose your phone, each code signs you in once instead of an authenticator code. Keep them somewhere safe, away from your password.</p>
                <ul class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 font-mono text-sm sm:grid-cols-4">
                    @foreach($newCodes as $code)
                        <li>{{ $code }}</li>
                    @endforeach
                </ul>
            </div>
        @else
            <p class="mt-3 text-sm">
                {{ \App\Support\TwoFactor::recoveryCodesLeft($me) }} of {{ \App\Support\TwoFactor::RECOVERY_CODES }} recovery codes left.
            </p>
        @endif

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <details class="rounded-card border border-surface-field p-4" @if($tf->has('recovery_code_check')) open @endif>
                <summary class="cursor-pointer text-sm font-semibold">Make new recovery codes</summary>
                <form method="POST" action="{{ route('admin.two-factor.recovery-codes') }}" class="mt-3 flex flex-col gap-3">
                    @csrf
                    <div>
                        <label for="recovery-code-check" class="adm-label">Code from your authenticator app</label>
                        <input id="recovery-code-check" name="recovery_code_check" type="text" inputmode="numeric" autocomplete="one-time-code" class="adm-input w-full" placeholder="123456"
                               @if($tf->has('recovery_code_check')) aria-invalid="true" aria-describedby="recovery-code-check-error" @endif>
                        @if($tf->has('recovery_code_check'))
                            <p id="recovery-code-check-error" class="mt-1 text-[13px] text-status-error">{{ $tf->first('recovery_code_check') }}</p>
                        @endif
                    </div>
                    <p class="adm-caption">The codes you have now stop working.</p>
                    <button type="submit" class="adm-btn-outline self-start">Make new codes</button>
                </form>
            </details>

            <details class="rounded-card border border-surface-field p-4" @if($tf->has('password') || $tf->has('disable_code')) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-status-error">Turn off</summary>
                <form method="POST" action="{{ route('admin.two-factor.destroy') }}" class="mt-3 flex flex-col gap-3"
                      data-confirm-title="Turn off two-factor authentication?"
                      data-confirm-message="Signing in will take only your password."
                      data-confirm-button="Turn off">
                    @csrf @method('DELETE')
                    @if($me->has_password)
                        <div>
                            <label for="two-factor-password" class="adm-label">Current password</label>
                            <input id="two-factor-password" name="password" type="password" autocomplete="current-password" class="adm-input w-full"
                                   @if($tf->has('password')) aria-invalid="true" aria-describedby="two-factor-password-error" @endif>
                            @if($tf->has('password'))
                                <p id="two-factor-password-error" class="mt-1 text-[13px] text-status-error">{{ $tf->first('password') }}</p>
                            @endif
                        </div>
                    @endif
                    <div>
                        <label for="disable-code" class="adm-label">Authenticator or recovery code</label>
                        <input id="disable-code" name="disable_code" type="text" autocomplete="one-time-code" class="adm-input w-full" placeholder="123456"
                               @if($tf->has('disable_code')) aria-invalid="true" aria-describedby="disable-code-error" @endif>
                        @if($tf->has('disable_code'))
                            <p id="disable-code-error" class="mt-1 text-[13px] text-status-error">{{ $tf->first('disable_code') }}</p>
                        @endif
                    </div>
                    <button type="submit" class="adm-btn-danger self-start">Turn off</button>
                </form>
            </details>
        </div>

    @elseif($settingUp)
        <p class="adm-caption mt-1">Two steps, then signing in asks for a code from your phone after your password.</p>

        <div class="mt-4 flex flex-wrap items-start gap-6">
            {{-- Drawn in the browser (window.qrSvg, resources/js/app.js) from the otpauth:// address. --}}
            <div class="h-[188px] w-[188px] flex-shrink-0 rounded-card bg-white p-2"
                 role="img" aria-label="QR code to scan with your authenticator app"
                 x-data x-init="$el.innerHTML = window.qrSvg(@js(\App\Support\TwoFactor::uri($me)))"></div>
            <div class="min-w-0 flex-1 basis-[260px]">
                <p class="text-sm"><span class="font-semibold">1.</span> In Google Authenticator (or any authenticator app), add an account and scan this QR code.</p>
                <p class="adm-caption mt-2">Cannot scan? Choose "Enter a setup key" and type this key, as a time-based key:</p>
                <p class="mt-1 break-all font-mono text-sm font-semibold tracking-wider">{{ trim(chunk_split($me->two_factor_secret, 4, ' ')) }}</p>

                <form method="POST" action="{{ route('admin.two-factor.confirm') }}" class="mt-4">
                    @csrf
                    <label for="two-factor-code" class="adm-label"><span class="font-semibold">2.</span> Enter the 6-digit code the app shows</label>
                    <div class="flex flex-wrap items-start gap-2">
                        <input id="two-factor-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required
                               class="adm-input w-40 font-mono tracking-widest" placeholder="123456"
                               @if($tf->has('code')) aria-invalid="true" aria-describedby="two-factor-code-error" @endif>
                        <button type="submit" class="adm-btn-primary !h-11">Confirm and turn on</button>
                    </div>
                    @if($tf->has('code'))
                        <p id="two-factor-code-error" class="mt-1 text-[13px] text-status-error">{{ $tf->first('code') }}</p>
                    @endif
                </form>
                <form method="POST" action="{{ route('admin.two-factor.destroy') }}" class="mt-3">
                    @csrf @method('DELETE')
                    <button type="submit" class="adm-link">Cancel</button>
                </form>
            </div>
        </div>

    @else
        <p class="adm-caption mt-1">Off. Signing in takes only your password.</p>
        <p class="mt-3 text-sm">Add a second step: after your password, a 6-digit code from an authenticator app on your phone, such as Google Authenticator. Recommended for every admin.</p>
        <form method="POST" action="{{ route('admin.two-factor.store') }}" class="mt-4">
            @csrf
            <button type="submit" class="adm-btn-primary">Turn on</button>
        </form>
    @endif
</section>
