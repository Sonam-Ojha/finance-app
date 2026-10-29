<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? 'Expense Tracker' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|poppins:600,700&display=swap" rel="stylesheet">
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{--
        Sign in, sign up and the other signed-out pages: one white card on the
        page grey, in the tracker's colours (tailwind.config.js: brand.navy,
        ink, surface.*). Always light — the brand's look before anyone has
        picked a theme. Rules sit under .auth so they win over the tracker's
        own .btn-primary / .btn-secondary. A page can fill the `footer` slot
        (the line under the card) and the `title` slot.
    --}}
    <style>
        .auth {
            --navy: #002e62; --navy-hover: #0b3d77; --ink: #1f2937; --muted: #4b5563; --soft: #6b7280;
            --page: #f2f4f7; --card: #ffffff; --line: #e5e7eb; --field: #d1d5db;
            --ring: rgba(0, 46, 98, .14); --error: #dc2626;
        }
        body.auth {
            margin: 0; min-height: 100vh;
            background: radial-gradient(900px 420px at 50% -120px, rgba(0, 46, 98, .09), rgba(0, 46, 98, 0)), var(--page);
            color: var(--ink); font-family: Inter, system-ui, sans-serif; font-size: 14px; line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        .auth *, .auth *::before, .auth *::after { box-sizing: border-box; }

        .auth .auth-shell { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 16px; }
        .auth .auth-brand { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 24px; text-decoration: none; color: var(--navy); }
        .auth .auth-brand img { width: 36px; height: 36px; border-radius: 10px; }
        .auth .auth-brand span { font-family: Poppins, Inter, sans-serif; font-size: 18px; font-weight: 700; letter-spacing: -.01em; }

        .auth .auth-card {
            width: 100%; max-width: 420px; padding: 32px;
            background: var(--card); border: 1px solid var(--line); border-radius: 16px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 12px 32px rgba(16, 24, 40, .06);
        }
        .auth .auth-footer { margin: 20px 0 0; font-size: 14px; color: var(--muted); text-align: center; }
        .auth .auth-footer a { font-weight: 600; color: var(--navy); text-decoration: none; }
        .auth .auth-footer a:hover { text-decoration: underline; }
        .auth .auth-note { margin: 28px 0 0; font-size: 12px; color: var(--soft); text-align: center; }

        .auth .form-title { margin: 0; font-family: Poppins, Inter, sans-serif; font-size: 22px; font-weight: 600; line-height: 1.3; color: var(--navy); text-align: center; }
        .auth .form-subtitle { margin: 6px 0 24px; font-size: 14px; color: var(--muted); text-align: center; }
        .auth .form-subtitle strong { color: var(--ink); font-weight: 600; }

        .auth .field-group { margin-bottom: 16px; }
        .auth .field-label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--ink); }
        .auth .field-wrap { position: relative; }
        .auth .field-input {
            display: block; width: 100%; height: 46px; padding: 0 14px !important;
            background: var(--card); color: var(--ink); font: inherit; font-size: 15px;
            border: 1px solid var(--field); border-radius: 10px; outline: none; box-shadow: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .auth .field-input::placeholder { color: #9ca3af; }
        .auth .field-input:focus { border-color: var(--navy); box-shadow: 0 0 0 3px var(--ring); }
        .auth .field-input.is-error { border-color: var(--error); }
        .auth .field-input.is-error:focus { box-shadow: 0 0 0 3px rgba(220, 38, 38, .12); }
        .auth .field-wrap .field-input.has-toggle { padding-right: 64px !important; }
        .auth .field-icon { display: none; }
        .auth .field-toggle {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            height: 34px; padding: 0 10px; border: 0; border-radius: 8px; background: transparent;
            font: inherit; font-size: 13px; font-weight: 600; color: var(--navy); cursor: pointer;
        }
        .auth .field-toggle:hover { background: #f0f4f9; }
        .auth .field-error { margin-top: 6px; font-size: 13px; color: var(--error); }

        .auth .row-between { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 4px 0 20px; }
        .auth .check-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: var(--muted); }
        .auth .check-label input { width: 16px; height: 16px; margin: 0; accent-color: var(--navy); }
        .auth .forgot-link { font-size: 14px; font-weight: 600; color: var(--navy); text-decoration: none; }
        .auth .forgot-link:hover { text-decoration: underline; }

        .auth .btn-primary, .auth .btn-secondary {
            display: flex; width: 100%; height: 46px; align-items: center; justify-content: center; gap: 8px;
            padding: 0 20px; border-radius: 10px; font: inherit; font-size: 15px; font-weight: 600;
            text-decoration: none; cursor: pointer; transition: background-color .15s, border-color .15s;
        }
        .auth .btn-primary { border: 0; background: var(--navy); color: #fff; }
        .auth .btn-primary:hover { background: var(--navy-hover); }
        .auth .btn-secondary { border: 1px solid var(--field); background: var(--card); color: var(--ink); }
        .auth .btn-secondary:hover { background: #f9fafb; border-color: #9ca3af; }
        .auth .btn-primary:focus-visible, .auth .btn-secondary:focus-visible, .auth a:focus-visible, .auth .field-toggle:focus-visible { outline: 3px solid var(--ring); outline-offset: 2px; }

        .auth .divider { display: flex; align-items: center; gap: 12px; margin: 20px 0; }
        .auth .divider-line { flex: 1; height: 1px; background: var(--line); }
        .auth .divider-text { font-size: 13px; color: var(--soft); }

        .auth .google-button { display: flex; justify-content: center; min-height: 44px; }

        .auth .status-msg {
            margin-bottom: 18px; padding: 10px 14px; border-radius: 10px; font-size: 14px;
            background: #ecfdf3; border: 1px solid #abefc6; color: #067647;
        }

        @media (max-width: 480px) {
            .auth .auth-shell { justify-content: flex-start; padding: 32px 16px; }
            .auth .auth-card { padding: 24px 20px; }
        }
    </style>
</head>
<body class="auth">
    <main class="auth-shell">
        <a href="{{ url('/') }}" class="auth-brand">
            <img src="/icons/icon-192.png" alt="" width="36" height="36">
            <span>Expense Tracker</span>
        </a>

        <div class="auth-card">
            {{ $slot }}
        </div>

        @isset($footer)
            <p class="auth-footer">{{ $footer }}</p>
        @endisset

        <p class="auth-note">One account for the website and the Android app.</p>
    </main>

    <script>
        // A "Show" button inside each password box.
        document.querySelectorAll('.auth .field-wrap input[type="password"]').forEach(function (input) {
            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'field-toggle';
            toggle.textContent = 'Show';
            toggle.setAttribute('aria-label', 'Show password');
            toggle.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.textContent = show ? 'Hide' : 'Show';
                toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
            input.classList.add('has-toggle');
            input.parentNode.appendChild(toggle);
        });
    </script>
</body>
</html>
