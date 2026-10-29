<x-admin-layout>
    <x-slot name="title">App control</x-slot>
    <x-slot name="subtitle">Switches for the whole service. Every change is written to the activity log.</x-slot>

    @if($maintenance)
        <div class="adm-strip" role="status">
            <x-icon name="warning" class="mt-0.5 h-5 w-5 flex-shrink-0 text-status-error" />
            <p><span class="font-semibold text-status-error">Maintenance mode is on.</span>
                Only admins can use the website; the app shows your message. Turn it off below when you are done.</p>
        </div>
    @endif

    <div class="grid items-start gap-4 lg:grid-cols-2">
        {{-- Maintenance mode --}}
        <section class="adm-card flex flex-col gap-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="maintenance-label" class="adm-h2">Maintenance mode</h2>
                    <p class="adm-caption mt-1">{{ $maintenance ? 'On · only admins get in' : 'Off · open for everyone' }}</p>
                </div>
                <form method="POST" action="{{ route('admin.app-control.maintenance') }}"
                      data-confirm-title="{{ $maintenance ? 'Turn maintenance mode off?' : 'Turn maintenance mode on?' }}"
                      data-confirm-message="{{ $maintenance ? 'The app and the website open for everyone again.' : 'Everyone but admins is shut out: the website shows the maintenance page and the app shows your message until you turn it off.' }}"
                      data-confirm-button="{{ $maintenance ? 'Turn off' : 'Turn on' }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="enabled" value="{{ $maintenance ? 0 : 1 }}">
                    <button type="submit" role="switch" aria-checked="{{ $maintenance ? 'true' : 'false' }}" aria-labelledby="maintenance-label" class="adm-switch"><span></span></button>
                </form>
            </div>
            <ul class="flex flex-col gap-2 text-sm">
                <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-navy" /> The website shows a maintenance page (503) to everyone who is not an admin. Admins keep full access.</li>
                <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-navy" /> <span>The app's requests are answered with your message (503, <code class="font-mono text-[13px]">maintenance</code>); nothing can be saved meanwhile.</span></li>
                <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-navy" /> Signing in stays open, so admins can always get back in to turn it off.</li>
            </ul>
            <form method="POST" action="{{ route('admin.app-control.maintenance') }}" class="flex flex-col gap-3 border-t border-surface-page pt-4"
                  x-data="{ message: @js(old('message', $maintenanceMessage ?? '')) }">
                @csrf @method('PUT')
                <div>
                    <label for="message" class="adm-label">Message people see</label>
                    <textarea id="message" name="message" rows="3" maxlength="300" x-model="message"
                              placeholder="{{ \App\Support\AppControl::DEFAULT_MAINTENANCE_MESSAGE }}" class="adm-input h-auto w-full py-2"></textarea>
                    <p class="adm-caption mt-1 flex justify-between gap-2"><span>Empty uses the default shown in grey.</span><span><span x-text="message.length"></span>/300</span></p>
                    @error('message')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="adm-btn-outline self-start">Save message</button>
            </form>
        </section>

        {{-- App versions --}}
        <section class="adm-card flex flex-col gap-4">
            <div>
                <h2 class="adm-h2">App versions</h2>
                <p class="adm-caption mt-1">
                    @if($minVersion)
                        Apps older than {{ $minVersion }} must update first.
                    @else
                        No version is blocked.
                    @endif
                    @if($latestVersion) Latest: {{ $latestVersion }}. @endif
                </p>
            </div>
            <ul class="flex flex-col gap-2 text-sm">
                <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-navy" /> <span><span class="font-semibold">Minimum:</span> an older app gets "update required" (426, <code class="font-mono text-[13px]">update_required</code>) on every request, and can only sign out.</span></li>
                <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-navy" /> <span><span class="font-semibold">Latest:</span> an older app may suggest updating, without blocking anything.</span></li>
                <li class="flex gap-2"><x-icon name="info" class="mt-0.5 h-4 w-4 flex-shrink-0 text-ink-muted" /> <span class="text-ink-muted">The app tells the server its version with each request. Builds made before it did (1.1.0 and older) cannot be blocked.</span></li>
            </ul>
            <form method="POST" action="{{ route('admin.app-control.versions') }}" class="flex flex-col gap-3 border-t border-surface-page pt-4"
                  data-confirm-title="Save app versions?"
                  data-confirm-message="If you raised the minimum, anyone on an older app has to update before they can carry on."
                  data-confirm-button="Save">
                @csrf @method('PUT')
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="min_version" class="adm-label">Minimum version</label>
                        <input id="min_version" name="min_version" type="text" maxlength="20" value="{{ old('min_version', $minVersion) }}" placeholder="e.g. 1.2.0" class="adm-input w-full font-mono">
                        @error('min_version')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="latest_version" class="adm-label">Latest version</label>
                        <input id="latest_version" name="latest_version" type="text" maxlength="20" value="{{ old('latest_version', $latestVersion) }}" placeholder="e.g. 1.3.0" class="adm-input w-full font-mono">
                        @error('latest_version')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="update_message" class="adm-label">Update message <span class="font-normal text-ink-muted">(optional)</span></label>
                    <textarea id="update_message" name="update_message" rows="2" maxlength="300"
                              placeholder="{{ \App\Support\AppControl::DEFAULT_UPDATE_MESSAGE }}" class="adm-input h-auto w-full py-2">{{ old('update_message', $updateMessage) }}</textarea>
                    @error('update_message')<p class="mt-1 text-[13px] text-status-error">{{ $message }}</p>@enderror
                </div>
                <p class="adm-caption">Leave a version empty to switch that check off.</p>
                <button type="submit" class="adm-btn-primary self-start">Save versions</button>
            </form>
        </section>

        {{-- Email verification in the app --}}
        <section class="adm-card flex flex-col gap-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="email-label" class="adm-h2">Require a verified email in the app</h2>
                    <p class="adm-caption mt-1">
                        {{ $emailRequired ? 'On' : 'Off' }} ·
                        {{ $emailSetHere ? 'set here' : 'following the server setting (EMAIL_VERIFICATION_REQUIRED_FOR_API)' }}
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.app-control.email-verification') }}"
                      data-confirm-title="{{ $emailRequired ? 'Stop requiring a verified email?' : 'Require a verified email?' }}"
                      data-confirm-message="{{ $emailRequired ? 'People can use the app without confirming their email.' : 'People who have not confirmed their email can only type in the code we send them until they do. Use it once the app in the stores can ask for the code.' }}"
                      data-confirm-button="{{ $emailRequired ? 'Make it optional' : 'Require it' }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="required" value="{{ $emailRequired ? 0 : 1 }}">
                    <button type="submit" role="switch" aria-checked="{{ $emailRequired ? 'true' : 'false' }}" aria-labelledby="email-label" class="adm-switch"><span></span></button>
                </form>
            </div>
            <ul class="flex flex-col gap-2 text-sm">
                <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-navy" /> <span>On: an account that has not typed in its 6-digit code gets "verify your email" (403, <code class="font-mono text-[13px]">email_unverified</code>) for everything but the code, its profile and signing out.</span></li>
                <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-navy" /> Off: the app works without it. The website always asks for the code.</li>
            </ul>
            @if($emailSetHere)
                <form method="POST" action="{{ route('admin.app-control.email-verification') }}" class="border-t border-surface-page pt-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="required" value="default">
                    <button type="submit" class="adm-link">Go back to the server setting ({{ $emailEnvDefault ? 'required' : 'not required' }})</button>
                </form>
            @endif
        </section>

        {{-- What the app reads --}}
        <section class="adm-card flex flex-col gap-3">
            <div>
                <h2 class="adm-h2">What the app reads</h2>
                <p class="adm-caption mt-1">The app checks this when it opens: <code class="font-mono">GET /api/v1/app-status</code></p>
            </div>
            <pre class="overflow-x-auto rounded-card bg-surface-page p-4 font-mono text-[13px] leading-relaxed text-ink">{{ json_encode(['data' => $status], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </section>
    </div>
</x-admin-layout>
