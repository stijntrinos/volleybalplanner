{{--
    Error pages (404, 500, onderhoud, ...). Deliberately standalone: inline
    CSS and no Vite, routes or database (only the web font is external), so they still render when the
    build is missing, the app is down for maintenance or the error came
    from the database.
--}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · Volleybalplanner</title>
    {{-- The site font when it loads; the system font otherwise. --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,600,700&display=swap" rel="stylesheet">
    <style>
        :root { --surface:#f3f6f8; --raised:#fff; --line:#d5dee4; --ink:#10293b; --muted:#4a5d6b; --faint:#7a8893; --brand:#0b7f8a; --brand-strong:#08646d; --brand-soft:#e3f2f3; }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin:0; min-height:100vh; min-height:100dvh; display:flex; flex-direction:column; background:var(--surface); color:var(--ink); font-family:'Outfit','Segoe UI',system-ui,-apple-system,sans-serif; font-size:16px; line-height:1.5; -webkit-font-smoothing:antialiased; }
        header { padding:20px 16px; }
        .wrap { width:100%; max-width:1080px; margin:0 auto; }
        .brand { display:inline-flex; align-items:center; gap:10px; color:var(--ink); font-weight:700; font-size:19px; text-decoration:none; letter-spacing:-.01em; }
        main { flex:1; display:grid; place-items:center; padding:24px 16px 64px; }
        .card { width:100%; max-width:520px; background:var(--raised); border:1px solid var(--line); border-radius:14px; padding:36px 32px; box-shadow:0 1px 2px rgba(16,41,59,.05); }
        .code { display:inline-block; font-size:13px; font-weight:600; letter-spacing:.04em; color:var(--brand-strong); background:var(--brand-soft); border-radius:999px; padding:3px 10px; margin-bottom:14px; }
        h1 { font-size:28px; line-height:1.2; margin:0 0 10px; letter-spacing:-.015em; }
        p { margin:0 0 24px; color:var(--muted); }
        .actions { display:flex; flex-wrap:wrap; gap:10px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:0 18px; border-radius:8px; font:inherit; font-weight:600; font-size:15px; text-decoration:none; border:1px solid transparent; cursor:pointer; }
        .btn-primary { background:var(--brand); color:#fff; }
        .btn-primary:hover { background:var(--brand-strong); color:#fff; }
        .btn-outline { background:var(--raised); color:var(--ink); border-color:var(--line); }
        .btn-outline:hover { border-color:var(--faint); }
        footer { padding:20px 16px; color:var(--faint); font-size:14px; text-align:center; }
        @media (max-width: 480px) { .card { padding:28px 20px; } h1 { font-size:24px; } .btn { flex:1 1 auto; } }
    </style>
</head>
<body>
    <header>
        <div class="wrap">
            <a href="{{ url('/') }}" class="brand">
                <svg viewBox="0 0 48 48" width="28" height="28" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" fill="#10293B"></rect><g transform="translate(36 12)" fill="none" stroke="#0B7F8A" stroke-width="3" stroke-linecap="round"><circle r="8.5"></circle><path d="M0 0 Q3.98 -3.34 0 -8.5"></path><path d="M0 0 Q0.9 5.12 7.36 4.25"></path><path d="M0 0 Q-4.89 -1.78 -7.36 4.25"></path></g><rect x="2" y="26" width="20" height="20" rx="5" fill="#10293B"></rect><rect x="26" y="26" width="20" height="20" rx="5" fill="#10293B"></rect></svg>
                <span>Volleybalplanner</span>
            </a>
        </div>
    </header>
    <main>
        <div class="card" role="alert">
            <span class="code">@yield('code')</span>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <div class="actions">
                @yield('actions')
            </div>
        </div>
    </main>
    <footer>Volleybalplanner</footer>
</body>
</html>
