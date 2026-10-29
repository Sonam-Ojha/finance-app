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
    <link rel="preload" as="image" type="image/avif" href="/images/login-hero.avif?v=2" media="(min-width: 1024px)">
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{--
        Sign in, sign up and the other signed-out pages. Wide screens: the
        brand side (7 of 12 columns) — a photo under a navy wash, the headline
        and two cards of what the app tells you — and the form (5 of 12).
        Phones: the form only.
        The tracker's colours (tailwind.config.js), always light. Rules sit
        under .auth so they win over the tracker's .btn-primary/.btn-secondary.
        A page can fill the `title` slot and the `footer` slot (the line under
        the form).
    --}}
    <style>
        .auth {
            --navy: #002e62; --navy-hover: #0b3d77; --cyan: #00a7cb; --ink: #1f2937; --muted: #4b5563; --soft: #6b7280;
            --page: #f2f4f7; --card: #ffffff; --line: #e5e7eb; --field: #d1d5db;
            --ring: rgba(0, 46, 98, .14); --error: #dc2626;
        }
        body.auth {
            margin: 0; min-height: 100vh; background: var(--card); color: var(--ink);
            font-family: Inter, system-ui, sans-serif; font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased;
        }
        .auth *, .auth *::before, .auth *::after { box-sizing: border-box; }

        .auth .auth-split { display: grid; grid-template-columns: 7fr 5fr; min-height: 100vh; }

        /* ── Brand side ── */
        .auth .auth-visual {
            position: sticky; top: 0; height: 100vh; overflow: hidden;
            display: flex; flex-direction: column; padding: 44px 56px 40px;
            color: #fff; background: #002e62;
        }
        .auth .visual-photo, .auth .visual-photo img { position: absolute; inset: 0; width: 100%; height: 100%; }
        .auth .visual-photo img { object-fit: cover; object-position: 40% 60%; }
        .auth .auth-visual::after { /* the navy wash: strong behind the words, lighter over the hands, strong again under the cards */
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background:
                linear-gradient(180deg, rgba(0, 46, 98, .94) 0%, rgba(0, 46, 98, .78) 30%, rgba(0, 46, 98, .38) 58%, rgba(0, 27, 61, .9) 100%),
                linear-gradient(90deg, rgba(0, 27, 61, .35), rgba(0, 27, 61, 0) 60%);
        }
        .auth .auth-visual > :not(.visual-photo) { position: relative; z-index: 1; }
        .auth .visual-brand { position: relative; display: flex; align-items: center; gap: 12px; color: #fff; text-decoration: none; }
        .auth .visual-brand img { width: 40px; height: 40px; border-radius: 11px; box-shadow: 0 0 0 1px rgba(255, 255, 255, .14); }
        .auth .visual-brand span { font-family: Poppins, Inter, sans-serif; font-size: 19px; font-weight: 700; }
        .auth .visual-copy { position: relative; margin-top: 48px; max-width: 480px; }
        .auth .visual-copy h2 { margin: 0; font-family: Poppins, Inter, sans-serif; font-size: 40px; font-weight: 700; line-height: 1.15; letter-spacing: -.02em; }
        .auth .visual-copy h2 span { color: var(--cyan); }
        .auth .visual-copy p { margin: 14px 0 0; font-size: 16px; line-height: 1.6; color: rgba(255, 255, 255, .78); }

        .auth .visual-cards { margin-top: auto; display: flex; flex-wrap: wrap; align-items: flex-end; gap: 16px; }
        .auth .visual-credit { margin: 18px 0 0; font-size: 11px; color: rgba(255, 255, 255, .55); }
        .auth .visual-credit a { color: inherit; }

        .auth .float-card {
            width: 252px; padding: 14px 16px;
            background: #fff; color: var(--ink); border-radius: 16px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, .06), 0 24px 48px rgba(0, 18, 40, .35);
        }
        .auth .float-card .fc-head { display: flex; align-items: center; gap: 10px; }
        .auth .float-card .fc-icon { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; }
        .auth .float-card .fc-title { font-size: 13px; font-weight: 600; line-height: 1.3; }
        .auth .float-card .fc-sub { font-size: 12px; color: var(--soft); }
        .auth .float-card .fc-amount { font-family: Poppins, Inter, sans-serif; font-size: 20px; font-weight: 600; color: var(--navy); line-height: 1.2; }
        .auth .float-card .fc-bar { margin-top: 10px; height: 6px; border-radius: 99px; background: #f1f2f4; overflow: hidden; }
        .auth .float-card .fc-bar span { display: block; height: 100%; width: 80%; border-radius: 99px; background: #f59e0b; }
        .auth .float-card .fc-head > div, .auth .float-card .fc-sub { white-space: nowrap; }
        .auth .float-card.is-saved .fc-sub { flex: 1; }
        .auth .float-card .fc-up { flex-shrink: 0; white-space: nowrap; font-size: 12px; font-weight: 600; color: #15803d; background: #ecfdf3; border-radius: 999px; padding: 2px 8px; }

        /* ── Form side ── */
        .auth .auth-main { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 48px 40px 32px; background: var(--card); }
        .auth .auth-form { width: 100%; max-width: 400px; }
        .auth .auth-brand { display: none; align-items: center; gap: 10px; margin-bottom: 28px; text-decoration: none; color: var(--navy); }
        .auth .auth-brand img { width: 36px; height: 36px; border-radius: 10px; }
        .auth .auth-brand span { font-family: Poppins, Inter, sans-serif; font-size: 18px; font-weight: 700; }
        .auth .auth-footer { margin: 24px 0 0; font-size: 14px; color: var(--muted); }
        .auth .auth-footer a { font-weight: 600; color: var(--navy); text-decoration: none; }
        .auth .auth-footer a:hover { text-decoration: underline; }
        .auth .auth-note { margin: 40px 0 0; font-size: 12px; color: var(--soft); }

        .auth .form-title { margin: 0; font-family: Poppins, Inter, sans-serif; font-size: 28px; font-weight: 700; line-height: 1.25; letter-spacing: -.01em; color: var(--navy); }
        .auth .form-subtitle { margin: 6px 0 28px; font-size: 15px; color: var(--muted); }
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

        .auth .row-between { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 4px 0 22px; }
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

        .auth .divider { display: flex; align-items: center; gap: 12px; margin: 22px 0; }
        .auth .divider-line { flex: 1; height: 1px; background: var(--line); }
        .auth .divider-text { font-size: 13px; color: var(--soft); }

        .auth .google-button { display: flex; justify-content: center; min-height: 44px; }

        .auth .status-msg {
            margin-bottom: 18px; padding: 10px 14px; border-radius: 10px; font-size: 14px;
            background: #ecfdf3; border: 1px solid #abefc6; color: #067647;
        }

        /* Medium-wide screens: a smaller phone and copy */
        @media (max-width: 1280px) {
            .auth .auth-visual { padding: 40px 40px 0; }
            .auth .visual-copy h2 { font-size: 34px; }
        }
        /* Tablets and phones: the form alone, on the page grey */
        @media (max-width: 1023px) {
            .auth .auth-split { grid-template-columns: 1fr; }
            .auth .auth-visual { display: none; }
            body.auth, .auth .auth-main { background: var(--page); }
            .auth .auth-main { justify-content: flex-start; padding: 32px 16px; }
            .auth .auth-form { padding: 28px 24px; background: var(--card); border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 12px 32px rgba(16, 24, 40, .06); }
            .auth .auth-brand { display: flex; }
            .auth .auth-footer, .auth .auth-note { text-align: center; }
            .auth .form-title { font-size: 24px; }
        }
    </style>
</head>
<body class="auth">
    <div class="auth-split">

        <aside class="auth-visual">
            <picture class="visual-photo">
                <source srcset="/images/login-hero.avif?v=2" type="image/avif">
                <img src="/images/login-hero.jpg" alt="" width="740" height="493">
            </picture>

            <a href="{{ url('/') }}" class="visual-brand">
                <img src="/icons/icon-192.png" alt="" width="40" height="40">
                <span>Expense Tracker</span>
            </a>

            <div class="visual-copy">
                <h2>Your money, <span>sorted.</span></h2>
                <p>Log what you spend and earn, set budgets, and see where every rupee goes — on the web and on your Android phone.</p>
            </div>

            {{-- Two of the things the app tells you --}}
            <div class="visual-cards" aria-hidden="true">
                <div class="float-card is-alert">
                    <div class="fc-head">
                        <span class="fc-icon" style="background:#fef3c7; color:#b45309">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                        </span>
                        <div>
                            <div class="fc-title">Budget alert</div>
                            <div class="fc-sub">Food &amp; Drink is 80% used</div>
                        </div>
                    </div>
                    <div class="fc-bar"><span></span></div>
                </div>

                <div class="float-card is-saved">
                    <div class="fc-head">
                        <span class="fc-icon" style="background:#ede9fe; color:#6d28d9">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8M15 7h6v6"/></svg>
                        </span>
                        <div class="fc-sub">Saved this month</div>
                        <span class="fc-up">↑ 11%</span>
                    </div>
                    <div class="fc-amount" style="margin-top: 8px">₹87,042</div>
                </div>
            </div>

            {{-- A free Freepik image: their licence asks for this credit --}}
            <p class="visual-credit">Image by <a href="https://www.freepik.com" target="_blank" rel="noopener nofollow">Freepik</a></p>
        </aside>

        <main class="auth-main">
            <div class="auth-form">
                <a href="{{ url('/') }}" class="auth-brand">
                    <img src="/icons/icon-192.png" alt="" width="36" height="36">
                    <span>Expense Tracker</span>
                </a>

                {{ $slot }}

                @isset($footer)
                    <p class="auth-footer">{{ $footer }}</p>
                @endisset
            </div>

            <p class="auth-note">One account for the website and the Android app.</p>
        </main>

    </div>

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
