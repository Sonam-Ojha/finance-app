{{--
    "Continue with Google" (Google Identity Services), at the top of the card
    with "or" under it. Google gives the page an ID token; it is posted, with
    the CSRF token, to auth.google (App\Http\Controllers\Auth\GoogleSignInController).
    The button is drawn as wide as the card. Hidden when no web client id is
    configured (services.google.web_client_id).
    $context: "signin" or "signup".
--}}
@php $googleClientId = config('services.google.web_client_id'); @endphp
@if($googleClientId)
    <div id="google-button" class="google-button" data-text="continue_with"></div>
    @error('google')<div class="field-error" role="alert" style="text-align:center">{{ $message }}</div>@enderror

    <div class="divider">
        <div class="divider-line"></div>
        <span class="divider-text">or</span>
        <div class="divider-line"></div>
    </div>

    <script>
        // Called by Google with the signed-in account's ID token.
        function continueWithGoogle(response) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = @json(route('auth.google'));
            [['_token', document.querySelector('meta[name="csrf-token"]').content], ['credential', response.credential]].forEach(function (pair) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = pair[0];
                input.value = pair[1];
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        }

        window.onGoogleLibraryLoad = function () {
            google.accounts.id.initialize({
                client_id: @json($googleClientId),
                callback: continueWithGoogle,
                context: @json($context ?? 'signin'),
                ux_mode: 'popup',
                itp_support: true,
            });
            var holder = document.getElementById('google-button');
            google.accounts.id.renderButton(holder, {
                type: 'standard', theme: 'outline', size: 'large', shape: 'rectangular',
                text: 'continue_with', logo_alignment: 'center',
                width: Math.max(200, Math.min(400, holder.clientWidth)), // Google's limits
            });
        };
    </script>
    <script src="https://accounts.google.com/gsi/client" async></script>
@endif
