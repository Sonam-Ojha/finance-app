<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Smart Paisa') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Figtree', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 960px;
            min-height: 560px;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.25);
            display: flex;
            overflow: hidden;
        }

        /* LEFT BRANDING PANEL */
        .auth-left {
            width: 42%;
            background: linear-gradient(160deg, #4f46e5 0%, #7c3aed 60%, #6d28d9 100%);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .auth-left::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
        }
        .auth-left::after {
            content: '';
            position: absolute;
            bottom: -60px; left: -60px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        .brand-logo {
            width: 48px; height: 48px;
            background: rgba(255,255,255,0.2);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; font-weight: 900; color: #fff;
            margin-bottom: 16px;
        }
        .brand-name { font-size: 22px; font-weight: 800; color: #fff; line-height: 1.2; }
        .brand-sub  { font-size: 12px; color: rgba(255,255,255,0.6); margin-top: 3px; }
        .brand-tagline {
            font-size: 26px; font-weight: 800; color: #fff; line-height: 1.35;
            margin: 32px 0 10px;
        }
        .brand-tagline span { color: #c4b5fd; }
        .brand-desc { font-size: 13px; color: rgba(255,255,255,0.65); line-height: 1.6; }
        .feature-item {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 10px;
        }
        .feature-icon {
            width: 32px; height: 32px;
            background: rgba(255,255,255,0.12);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
        }
        .feature-text { font-size: 13px; color: rgba(255,255,255,0.8); font-weight: 500; }
        .brand-pills { display: flex; flex-wrap: wrap; gap: 6px; }
        .brand-pill {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 100px;
            padding: 4px 10px;
            font-size: 11px; color: rgba(255,255,255,0.7);
        }

        /* RIGHT FORM PANEL */
        .auth-right {
            flex: 1;
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #fff;
        }
        .form-title   { font-size: 24px; font-weight: 800; color: #1e1b4b; margin-bottom: 4px; }
        .form-subtitle{ font-size: 13px; color: #94a3b8; margin-bottom: 28px; }

        .field-group  { margin-bottom: 16px; }
        .field-label  {
            display: block; font-size: 11px; font-weight: 700;
            color: #64748b; letter-spacing: .06em; text-transform: uppercase;
            margin-bottom: 6px;
        }
        .field-wrap   { position: relative; }
        .field-input  {
            width: 100%;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 11px 42px 11px 14px;
            font-size: 14px; color: #1e293b;
            outline: none;
            transition: all .2s;
            font-family: 'Figtree', sans-serif;
        }
        .field-input::placeholder { color: #cbd5e1; }
        .field-input:focus {
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }
        .field-icon {
            position: absolute; right: 13px; top: 50%;
            transform: translateY(-50%); color: #94a3b8;
            pointer-events: none;
        }
        .field-input.is-error { border-color: #f87171; }
        .field-error { font-size: 11px; color: #ef4444; margin-top: 4px; }

        .row-between { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .check-label { display: flex; align-items: center; gap: 7px; cursor: pointer; font-size: 13px; color: #64748b; }
        .check-label input { accent-color: #6366f1; width: 15px; height: 15px; }
        .forgot-link { font-size: 13px; color: #6366f1; font-weight: 600; text-decoration: none; }
        .forgot-link:hover { color: #4f46e5; }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            font-size: 14px; font-weight: 700;
            padding: 13px;
            border-radius: 10px;
            border: none; cursor: pointer;
            transition: all .2s;
            letter-spacing: .02em;
            font-family: 'Figtree', sans-serif;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            box-shadow: 0 8px 24px rgba(99,102,241,.35);
            transform: translateY(-1px);
        }
        .btn-primary:active { transform: translateY(0); }

        .divider {
            display: flex; align-items: center; gap: 10px;
            margin: 18px 0;
        }
        .divider-line { flex: 1; height: 1px; background: #e2e8f0; }
        .divider-text { font-size: 11px; color: #94a3b8; font-weight: 600; letter-spacing: .06em; }

        .btn-secondary {
            display: block; width: 100%;
            text-align: center;
            background: #f1f5f9;
            border: 1.5px solid #e2e8f0;
            color: #6366f1; font-size: 13px; font-weight: 700;
            padding: 12px;
            border-radius: 10px;
            text-decoration: none;
            transition: all .2s;
        }
        .btn-secondary:hover { background: #eef2ff; border-color: #c7d2fe; }

        .status-msg {
            background: #ecfdf5; border: 1px solid #bbf7d0;
            border-radius: 10px; padding: 10px 14px;
            font-size: 13px; color: #059669;
            margin-bottom: 16px;
        }

        /* Mobile: hide left panel */
        @media (max-width: 700px) {
            .auth-left  { display: none; }
            .auth-right { padding: 36px 28px; }
            .auth-wrapper { max-width: 420px; }
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">

        {{-- LEFT PANEL --}}
        <div class="auth-left">
            <div style="position:relative; z-index:1">
                <div class="brand-logo">₹</div>
                <div class="brand-name">Smart Paisa</div>
                <div class="brand-sub">Personal Finance Manager</div>

                <div class="brand-tagline">
                    Take full control<br>
                    <span>of your finances</span>
                </div>
                <div class="brand-desc">
                    Income, expenses, loans, investments —<br>manage everything in one place.
                </div>
            </div>

            <div style="position:relative; z-index:1; margin: 24px 0">
                @foreach([['💰','Income & Expense tracking'],['🏦','Bank & Card management'],['📈','Investment portfolio'],['🔔','EMI reminders'],['📊','Reports & PDF export']] as [$ic,$tx])
                <div class="feature-item">
                    <div class="feature-icon">{{ $ic }}</div>
                    <div class="feature-text">{{ $tx }}</div>
                </div>
                @endforeach
            </div>

            <div style="position:relative; z-index:1">
                <div class="brand-pills">
                    <span class="brand-pill">🔒 Secure</span>
                    <span class="brand-pill">📱 Mobile Ready</span>
                    <span class="brand-pill">🇮🇳 Made for India</span>
                </div>
            </div>
        </div>

        {{-- RIGHT PANEL --}}
        <div class="auth-right">
            {{ $slot }}
        </div>

    </div>
</body>
</html>
