<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Expense Tracker' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|poppins:500,600,700&display=swap" rel="stylesheet">
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{--
        Sign in, sign up and the other signed-out pages. The colours are the
        tracker's (tailwind.config.js: brand.navy, brand.cyan, surface.*,
        night.*), light or dark as the device is set. Rules sit under .auth so
        they win over the tracker's own .btn-primary / .btn-secondary.
    --}}
    <style>
        :root {
            --page: #f2f4f7; --card: #ffffff; --ink: #1f2937; --muted: #4b5563; --line: #e5e7eb; --field: #d1d5db;
            --brand: #002e62; --brand-hover: #0b3d77; --cyan: #00a7cb; --tint: #f0fafc;
            --heading: #002e62; --link: #002e62; --on-primary: #ffffff; --primary: #002e62; --primary-hover: #0b3d77;
            --ring: rgba(0, 46, 98, .16); --error: #dc2626;
            --ok-bg: #ecfdf3; --ok-line: #abefc6; --ok-ink: #067647;
            color-scheme: light;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --page: #0b1220; --card: #131c2e; --ink: #e5e7eb; --muted: #9ca3af; --line: #243047; --field: #3a4763;
                --tint: rgba(255, 255, 255, .05); --heading: #e5e7eb; --link: #00a7cb;
                --primary: #00a7cb; --primary-hover: #33b9d5; --on-primary: #002e62;
                --ring: rgba(0, 167, 203, .25); --error: #f87171;
                --ok-bg: rgba(22, 163, 74, .12); --ok-line: rgba(22, 163, 74, .35); --ok-ink: #86efac;
                color-scheme: dark;
            }
        }

        body.auth {
            margin: 0; min-height: 100vh; padding: 24px;
            display: flex; align-items: center; justify-content: center;
            background: var(--page); color: var(--ink);
            font-family: Inter, system-ui, sans-serif; font-size: 14px; line-height: 1.45;
            -webkit-font-smoothing: antialiased;
        }
        .auth *, .auth *::before, .auth *::after { box-sizing: border-box; }

        .auth .auth-wrapper {
            width: 100%; max-width: 1040px; min-height: 620px;
            display: grid; grid-template-columns: 44% 1fr;
            background: var(--card); border: 1px solid var(--line); border-radius: 20px; overflow: hidden;
            box-shadow: 0 1px 2px rgba(16, 24, 40, .05), 0 16px 40px rgba(16, 24, 40, .08);
        }

        /* ── Brand panel ── */
        .auth .auth-left {
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; gap: 28px;
            padding: 40px; background: var(--brand); color: #fff;
        }
        .auth .auth-left::after { /* a soft cyan glow, top right */
            content: ''; position: absolute; top: -140px; right: -140px; width: 320px; height: 320px; border-radius: 50%;
            background: radial-gradient(closest-side, rgba(0, 167, 203, .35), rgba(0, 167, 203, 0));
            pointer-events: none;
        }
        .auth .brand { position: relative; display: flex; align-items: center; gap: 12px; }
        .auth .brand img { width: 44px; height: 44px; border-radius: 12px; box-shadow: 0 0 0 1px rgba(255, 255, 255, .12); }
        .auth .brand-name { font-family: Poppins, Inter, sans-serif; font-size: 20px; font-weight: 700; line-height: 1.2; }
        .auth .brand-eyebrow { margin-top: 2px; font-size: 11px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--cyan); }
        .auth .brand-tagline { position: relative; font-family: Poppins, Inter, sans-serif; font-size: 30px; font-weight: 600; line-height: 1.2; margin: 0; }
        .auth .brand-desc { position: relative; margin: 10px 0 0; font-size: 15px; color: rgba(255, 255, 255, .78); max-width: 34ch; }

        /* A small, decorative "this month" card, like the app's home */
        .auth .preview {
            position: relative; padding: 18px; border-radius: 16px;
            background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .12);
        }
        .auth .preview-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 14px; }
        .auth .preview-label { font-size: 12px; color: rgba(255, 255, 255, .7); }
        .auth .preview-total { font-family: Poppins, Inter, sans-serif; font-size: 22px; font-weight: 600; }
        .auth .preview-row { display: grid; grid-template-columns: 32px 1fr auto; align-items: center; gap: 10px; }
        .auth .preview-row + .preview-row { margin-top: 12px; }
        .auth .preview-emoji { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; background: rgba(255, 255, 255, .1); font-size: 15px; }
        .auth .preview-name { font-size: 13px; font-weight: 600; }
        .auth .preview-bar { margin-top: 5px; height: 6px; border-radius: 99px; background: rgba(255, 255, 255, .12); overflow: hidden; }
        .auth .preview-bar span { display: block; height: 100%; border-radius: 99px; background: var(--cyan); }
        .auth .preview-amount { font-size: 13px; font-weight: 600; font-variant-numeric: tabular-nums; }

        .auth .features { position: relative; margin: 0; padding: 0; list-style: none; display: grid; gap: 10px; }
        .auth .features li { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; color: rgba(255, 255, 255, .88); }
        .auth .features svg { flex-shrink: 0; margin-top: 1px; color: var(--cyan); }
        .auth .brand-foot { position: relative; margin-top: auto; font-size: 12px; color: rgba(255, 255, 255, .6); }

        /* ── Form panel ── */
        .auth .auth-right { display: flex; align-items: center; justify-content: center; padding: 48px; }
        .auth .auth-form { width: 100%; max-width: 400px; }
        .auth .form-title { font-family: Poppins, Inter, sans-serif; font-size: 26px; font-weight: 600; line-height: 1.25; color: var(--heading); }
        .auth .form-subtitle { margin: 6px 0 28px; font-size: 14px; color: var(--muted); }
        .auth .form-subtitle strong { color: var(--ink); }

        .auth .field-group { margin-bottom: 18px; }
        .auth .field-label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--ink); }
        .auth .field-wrap { position: relative; }
        .auth .field-input {
            display: block; width: 100%; height: 48px; padding: 0 44px 0 14px;
            background: var(--card); color: var(--ink); font: inherit; font-size: 15px;
            border: 1px solid var(--field); border-radius: 12px; outline: none; box-shadow: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .auth .field-input::placeholder { color: var(--muted); opacity: .7; }
        .auth .field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
        .auth .field-input.is-error { border-color: var(--error); }
        .auth .field-icon { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; display: flex; }
        .auth .field-error { margin-top: 6px; font-size: 13px; color: var(--error); }

        .auth .row-between { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: -2px 0 22px; }
        .auth .check-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: var(--muted); }
        .auth .check-label input { width: 16px; height: 16px; accent-color: var(--primary); }
        .auth .forgot-link { font-size: 14px; font-weight: 600; color: var(--link); text-decoration: none; }
        .auth .forgot-link:hover { text-decoration: underline; }

        .auth .btn-primary, .auth .btn-secondary {
            display: flex; width: 100%; min-height: 48px; align-items: center; justify-content: center; gap: 8px;
            padding: 0 20px; border-radius: 12px; font: inherit; font-size: 15px; font-weight: 600;
            text-decoration: none; cursor: pointer; transition: background-color .15s, border-color .15s;
        }
        .auth .btn-primary { border: 0; background: var(--primary); color: var(--on-primary); }
        .auth .btn-primary:hover { background: var(--primary-hover); }
        .auth .btn-secondary { border: 2px solid var(--primary); background: transparent; color: var(--link); }
        .auth .btn-secondary:hover { background: var(--tint); }
        @media (prefers-color-scheme: dark) { .auth .btn-secondary { border-color: var(--field); color: var(--ink); } }
        .auth .btn-primary:focus-visible, .auth .btn-secondary:focus-visible, .auth .forgot-link:focus-visible { outline: 3px solid var(--ring); outline-offset: 2px; }

        .auth .divider { display: flex; align-items: center; gap: 12px; margin: 22px 0; }
        .auth .divider-line { flex: 1; height: 1px; background: var(--line); }
        .auth .divider-text { font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }

        .auth .status-msg {
            margin-bottom: 18px; padding: 12px 14px; border-radius: 12px; font-size: 14px;
            background: var(--ok-bg); border: 1px solid var(--ok-line); color: var(--ok-ink);
        }

        /* Phones and narrow windows: one column, the brand as a short header */
        @media (max-width: 860px) {
            body.auth { padding: 16px; align-items: flex-start; }
            .auth .auth-wrapper { grid-template-columns: 1fr; max-width: 480px; min-height: 0; border-radius: 16px; }
            .auth .auth-left { gap: 14px; padding: 24px; }
            .auth .brand-tagline { font-size: 22px; }
            .auth .brand-desc { font-size: 14px; margin-top: 6px; }
            .auth .preview, .auth .features, .auth .brand-foot { display: none; }
            .auth .auth-right { padding: 28px 24px 32px; }
            .auth .form-title { font-size: 24px; }
        }
    </style>
</head>
<body class="auth">
    <div class="auth-wrapper">

        <aside class="auth-left">
            <div class="brand">
                <img src="/icons/icon-192.png" alt="" width="44" height="44">
                <div>
                    <div class="brand-name">Expense Tracker</div>
                    <div class="brand-eyebrow">Web and Android</div>
                </div>
            </div>

            <div>
                <p class="brand-tagline">Your money, sorted.</p>
                <p class="brand-desc">Log what you spend and earn, set budgets, and see where every rupee goes.</p>
            </div>

            {{-- Decoration only: not real data --}}
            <div class="preview" aria-hidden="true">
                <div class="preview-head">
                    <span class="preview-label">Spent this month</span>
                    <span class="preview-total">₹8,935</span>
                </div>
                @foreach([['🍔', 'Food & Drink', '₹4,280', 86], ['🛒', 'Groceries', '₹3,165', 64], ['🚗', 'Transport', '₹1,490', 30]] as [$emoji, $name, $amount, $share])
                    <div class="preview-row">
                        <span class="preview-emoji">{{ $emoji }}</span>
                        <div>
                            <div class="preview-name">{{ $name }}</div>
                            <div class="preview-bar"><span style="width: {{ $share }}%"></span></div>
                        </div>
                        <span class="preview-amount">{{ $amount }}</span>
                    </div>
                @endforeach
            </div>

            <ul class="features">
                @foreach([
                    'Expenses and income in one place',
                    'Budgets that warn you before you overspend',
                    'Recurring bills and salary, added for you',
                    'Savings goals, and splitting costs with friends',
                    'A monthly PDF report and a CSV export',
                ] as $feature)
                    <li>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ $feature }}
                    </li>
                @endforeach
            </ul>

            <p class="brand-foot">The same account works on this website and in the Android app.</p>
        </aside>

        <main class="auth-right">
            <div class="auth-form">
                {{ $slot }}
            </div>
        </main>

    </div>
</body>
</html>
