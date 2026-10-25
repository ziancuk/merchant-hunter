<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('title') · {{ config('app.name') }}</title>
    {{-- Self-contained (no scripts, no external requests): must render even when the app bundle is unavailable. --}}
    <style>
        :root { color-scheme: dark light; --bg: #070d0c; --surface: #0f1917; --line: rgb(190 230 215 / .14); --text: #e8f0eb; --muted: #9fb3aa; --focus: #7de3c4; --hot: #ff6a2b; }
        @media (prefers-color-scheme: light) {
            :root { --bg: #f3f1ea; --surface: #ffffff; --line: rgb(16 32 27 / .16); --text: #10201b; --muted: #4a5c55; --focus: #0a7a63; --hot: #c23b00; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100dvh; display: grid; place-items: center; padding: 16px; background: var(--bg); color: var(--text); font-family: "Instrument Sans", system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.5; }
        main { width: 100%; max-width: 30rem; padding: 32px 24px; border: 1px solid var(--line); border-radius: 20px; background: var(--surface); text-align: center; }
        .brand { margin: 0 0 20px; font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }
        .code { margin: 0; font-family: "Bricolage Grotesque", system-ui, sans-serif; font-size: 4.5rem; font-weight: 700; line-height: 1; color: var(--hot); font-variant-numeric: tabular-nums; }
        h1 { margin: 12px 0 8px; font-family: "Bricolage Grotesque", system-ui, sans-serif; font-size: 1.5rem; }
        p.msg { margin: 0 0 24px; color: var(--muted); }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        a.btn { display: inline-block; min-height: 40px; padding: 8px 16px; border: 1px solid var(--line); border-radius: 12px; color: var(--text); text-decoration: none; font-weight: 600; }
        a.btn.primary { background: var(--focus); border-color: var(--focus); color: var(--bg); }
        a.btn:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
    </style>
</head>
<body>
    <main role="main">
        <p class="brand">{{ config('app.name') }}</p>
        <p class="code" aria-hidden="true">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="msg">@yield('message')</p>
        <div class="actions">
            <a class="btn primary" href="{{ url('/') }}">Kembali ke peta</a>
            @hasSection('extra')
                @yield('extra')
            @endif
        </div>
    </main>
</body>
</html>
