<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#070d0c">
    {{-- Apply the saved theme before first paint to avoid a flash (dark-first). --}}
    <script nonce="{{ Vite::cspNonce() }}">
        (function () {
            var theme = 'dark';
            try {
                var saved = localStorage.getItem('hunter.theme');
                if (saved === 'light' || saved === 'dark') theme = saved;
            } catch (e) {}
            var root = document.documentElement;
            root.classList.remove('dark', 'light');
            root.classList.add(theme);
            root.style.colorScheme = theme;
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&family=Plus+Jakarta+Sans:wght@500..800&display=swap" rel="stylesheet">
    <title inertia>{{ config('app.name') }}</title>
    @vite(['resources/js/app.ts'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
