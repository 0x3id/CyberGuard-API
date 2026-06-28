@php
    $pageTitle = $pageTitle ?? 'CyberGuard';
    $emailGreeting = $emailGreeting ?? 'CyberGuard Notification';
    $userName = $userName ?? 'there';
    $headerIcon = $headerIcon ?? 'envelope';
    $actionUrl = $actionUrl ?? null;
    $actionText = $actionText ?? null;
    $expiryText = $expiryText ?? null;
    $fallbackUrl = $fallbackUrl ?? $actionUrl;
    $showFallback = $showFallback ?? filled($fallbackUrl);
    $footerNote = $footerNote ?? 'If you did not expect this message, you can safely ignore this email.';
    $supportEmail = $supportEmail ?? 'support@cyberguard.pro';
    $frontendUrl = $frontendUrl ?? rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://cyberguard-pro-eta.vercel.app/')), '/');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $pageTitle }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            background-color: #0a0e17;
            min-height: 100vh;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-image: radial-gradient(ellipse 70% 50% at 30% 0%, rgba(59,130,246,0.12) 0%, transparent 60%),
                              radial-gradient(ellipse 60% 40% at 70% 100%, rgba(56,189,248,0.06) 0%, transparent 60%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        body::-webkit-scrollbar { display: none; }
        .floating-shapes {
            position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 0;
        }
        .shape {
            position: absolute; border-radius: 50%; background: rgba(59,130,246,0.05);
            animation: float 6s ease-in-out infinite;
        }
        .shape:nth-child(1) { width: 90px; height: 90px; top: 15%; left: 8%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 140px; height: 140px; top: 55%; right: 8%; animation-delay: 2s; }
        .shape:nth-child(3) { width: 65px; height: 65px; bottom: 18%; left: 18%; animation-delay: 4s; }
        .shape:nth-child(4) { width: 50px; height: 50px; top: 30%; right: 20%; animation-delay: 1s; }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-22px) rotate(180deg); }
        }
        .card-wrapper {
            position: relative; z-index: 1; width: 100%; max-width: 440px;
            animation: slideUp 0.8s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .email-card {
            background: #111827; border: 1px solid rgba(148,163,184,0.15);
            border-radius: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.45);
            overflow: hidden; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
        }
        .card-header {
            background: linear-gradient(135deg, #1f2937 0%, rgba(59,130,246,0.15) 100%);
            border-bottom: 1px solid rgba(148,163,184,0.15);
            padding: 1.25rem 1.5rem; text-align: center; position: relative; overflow: hidden;
        }
        .card-header::after {
            content: ''; position: absolute; bottom: 0; left: 50%;
            transform: translateX(-50%); width: 80%; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(59,130,246,0.5), transparent);
        }
        .logo-area {
            display: flex; align-items: center; justify-content: center;
            gap: 0.6rem; margin-bottom: 0.25rem; position: relative;
        }
        .shield-icon {
            width: 38px; height: 38px; display: flex; align-items: center;
            justify-content: center; flex-shrink: 0;
        }
        .shield-icon svg { width: 38px; height: 38px; }
        .brand-name { display: flex; flex-direction: column; align-items: flex-start; }
        .brand-name .cyber {
            color: #1d4ed8; font-size: 1.25rem; font-weight: 800;
            letter-spacing: -0.5px; line-height: 1;
        }
        .brand-name .guard {
            color: #f8fafc; font-size: 1.25rem; font-weight: 800;
            letter-spacing: -0.5px; line-height: 1;
        }
        .brand-name .tagline {
            font-size: 0.55rem; font-weight: 600; letter-spacing: 0.18em;
            color: #64748b; text-transform: uppercase; margin-top: 1px;
        }
        .verify-icon-wrap { margin-top: 1rem; display: flex; justify-content: center; position: relative; }
        .verify-icon-circle {
            width: 56px; height: 56px; border-radius: 50%;
            background: rgba(59,130,246,0.15); border: 2px solid rgba(59,130,246,0.4);
            display: flex; align-items: center; justify-content: center; position: relative;
        }
        .verify-icon-circle::before {
            content: ''; position: absolute; inset: -6px; border-radius: 50%;
            border: 1px dashed rgba(59,130,246,0.35);
            animation: rotateSlow 12s linear infinite;
        }
        @keyframes rotateSlow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .verify-icon-circle svg { width: 26px; height: 26px; fill: #3b82f6; }
        .card-body { padding: 1.5rem 2rem 1.25rem; }
        .email-greeting {
            font-size: 1.3rem; font-weight: 800; color: #f8fafc;
            margin-bottom: 0.25rem; letter-spacing: -0.5px; line-height: 1.2;
        }
        .hello-line {
            font-size: 0.9375rem; color: #94a3b8; margin-bottom: 0.75rem; font-weight: 400;
        }
        .hello-line span { color: #3b82f6; font-weight: 600; }
        .message-body {
            font-size: 0.875rem; color: #94a3b8; line-height: 1.6; margin-bottom: 1.25rem;
        }
        .message-body strong { color: #3b82f6; font-weight: 700; }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(148,163,184,0.25), transparent);
            margin: 0 -2rem 1.25rem;
        }
        .cta-wrap { text-align: center; margin-bottom: 1rem; }
        .btn-verify {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #000000 !important; font-family: 'Inter', sans-serif;
            font-size: 0.875rem; font-weight: 700; letter-spacing: 0.1em;
            text-transform: uppercase; text-decoration: none;
            padding: 0.8rem 2rem; border-radius: 0.75rem; border: none;
            cursor: pointer; width: 100%; position: relative; overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1); box-shadow: none;
        }
        .btn-verify::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transform: translateX(-100%); transition: transform 0.5s ease;
        }
        .btn-verify:hover {
            transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,255,255,0.15);
        }
        .btn-verify:hover::before { transform: translateX(100%); }
        .btn-verify:active { transform: translateY(0); }
        .btn-icon {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem;
        }
        .btn-icon svg { width: 18px; height: 18px; fill: currentColor; flex-shrink: 0; }
        .expiry-notice { text-align: center; margin-bottom: 1rem; }
        .expiry-notice p {
            font-size: 0.75rem; color: #64748b; font-style: italic; font-weight: 300;
        }
        .expiry-notice strong {
            color: #94a3b8; font-weight: 600; font-style: normal;
        }
        .fallback-section {
            background: #1f2937; border: 1px solid rgba(148,163,184,0.15);
            border-radius: 0.75rem; padding: 0.75rem 1rem; margin-bottom: 1rem;
        }
        .fallback-section p {
            font-size: 0.8125rem; color: #94a3b8; margin-bottom: 0.5rem;
        }
        .fallback-link {
            font-size: 0.75rem; color: #1d4ed8; word-break: break-all;
            font-family: Consolas, Monaco, 'Courier New', monospace;
            background: rgba(59,130,246,0.08); padding: 0.25rem 0.5rem;
            border-radius: 0.375rem; display: inline-block;
        }
        .security-badges {
            display: flex; align-items: center; justify-content: center;
            gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;
        }
        .badge {
            display: flex; align-items: center; gap: 0.3rem;
            font-size: 0.65rem; font-weight: 600; color: #64748b;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .badge svg { width: 12px; height: 12px; fill: #10b981; }
        .card-footer {
            background: #1f2937; border-top: 1px solid rgba(148,163,184,0.15);
            padding: 1rem 2rem; text-align: center;
        }
        .footer-text {
            font-size: 0.75rem; color: #64748b; line-height: 1.5;
        }
        .footer-text a {
            color: #3b82f6; text-decoration: none; font-weight: 500;
        }
        .footer-text a:hover { text-decoration: underline; }
        .back-link { text-align: center; margin-top: 1.2rem; }
        .back-link a {
            color: #94a3b8; font-size: 0.875rem; text-decoration: none;
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-weight: 500; padding: 0.6rem 1.25rem; border-radius: 9999px;
            background: rgba(255,255,255,0.02); border: 1px solid rgba(148,163,184,0.15);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); backdrop-filter: blur(10px);
        }
        .back-link a:hover {
            color: #f8fafc; border-color: rgba(59,130,246,0.3);
            background: rgba(59,130,246,0.08); transform: translateY(-1.5px);
        }
        .back-link svg { width: 14px; height: 14px; fill: currentColor; }
        .back-link a:hover svg { transform: translateX(-3px); }
        @media (max-width: 520px) {
            .card-body { padding: 1.75rem 1.5rem 1.5rem; }
            .card-header { padding: 1.75rem 1.5rem 2rem; }
            .card-footer { padding: 1.1rem 1.5rem; }
            .email-greeting { font-size: 1.3rem; }
            .divider { margin: 0 -1.5rem 1.5rem; }
            .security-badges { gap: 0.75rem; }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="card-wrapper">
        <div class="email-card">
            <div class="card-header">
                @include('mail.partials.brand-header', ['headerIcon' => $headerIcon])
            </div>

            <div class="card-body">
                <h1 class="email-greeting">{{ $emailGreeting }}</h1>
                <p class="hello-line">Hello, <span>{{ $userName }}</span></p>

                <div class="message-body">
                    @yield('message')
                </div>

                <div class="divider"></div>

                @if ($actionUrl && $actionText)
                    <div class="cta-wrap">
                        <a href="{{ $actionUrl }}" class="btn-verify">
                            <span class="btn-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                </svg>
                                {{ $actionText }}
                            </span>
                        </a>
                    </div>
                @endif

                @if ($expiryText)
                    <div class="expiry-notice">
                        <p>{!! $expiryText !!}</p>
                    </div>
                @endif

                @if ($showFallback && $fallbackUrl)
                    <div class="fallback-section">
                        <p>Button not working? Copy and paste the link below into your browser:</p>
                        <span class="fallback-link">{{ $fallbackUrl }}</span>
                    </div>
                @endif

                <div class="security-badges">
                    <div class="badge">
                        <svg viewBox="0 0 24 24"><path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3z"/></svg>
                        End-to-End Encrypted
                    </div>
                    <div class="badge">
                        <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        2-Factor Protected
                    </div>
                    <div class="badge">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        Verified Secure
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <p class="footer-text">
                    {{ $footerNote }}
                    <br />
                    Need help?
                    <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                    &nbsp;·&nbsp;
                    <a href="{{ $frontendUrl }}">Back to CyberGuard</a>
                </p>
            </div>
        </div>

        <div class="back-link">
            <a href="{{ $frontendUrl }}">
                <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Back to CyberGuard
            </a>
        </div>
    </div>
</body>
</html>
