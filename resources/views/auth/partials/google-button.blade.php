{{--
    "Continue with Google" (Google Identity Services). Google gives the page an
    ID token; it is posted, with the CSRF token, to auth.google
    (App\Http\Controllers\Auth\GoogleSignInController). Hidden when no web
    client id is configured (services.google.web_client_id).
    $context: "signin" or "signup".
--}}
@php $googleClientId = config('services.google.web_client_id'); @endphp
@if($googleClientId)
    <div class="divider">
        <div class="divider-line"></div>
        <span class="divider-text">OR</span>
        <div class="divider-line"></div>
    </div>

    <div id="g_id_onload"
         data-client_id="{{ $googleClientId }}"
         data-context="{{ $context ?? 'signin' }}"
         data-ux_mode="popup"
         data-callback="continueWithGoogle"
         data-auto_prompt="false"
         data-itp_support="true"></div>
    <div style="display:flex; justify-content:center; min-height:44px">
        <div class="g_id_signin"
             data-type="standard"
             data-shape="rectangular"
             data-theme="outline"
             data-text="continue_with"
             data-size="large"
             data-logo_alignment="left"></div>
    </div>
    @error('google')<div class="field-error" role="alert" style="text-align:center; margin-top:8px">{{ $message }}</div>@enderror

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
    </script>
    <script src="https://accounts.google.com/gsi/client" async></script>
@endif
