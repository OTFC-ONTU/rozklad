<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Розклад') }}</title>
    <style>
        :root { --bg: #f8fafc; --fg: #0f172a; --muted: #64748b; --accent: #2563eb; --card: #ffffff; --border: #e2e8f0; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0b1120; --fg: #e2e8f0; --muted: #94a3b8; --accent: #60a5fa; --card: #111827; --border: #1f2937; }
        }
        * { box-sizing: border-box; margin: 0; }
        body {
            min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 24px 16px; background: var(--bg); color: var(--fg);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.5;
        }
        main {
            width: 100%; max-width: 480px; padding: 40px 32px; text-align: center;
            background: var(--card); border: 1px solid var(--border); border-radius: 16px;
        }
        h1 { font-size: 2rem; margin-bottom: 8px; }
        p { color: var(--muted); margin-bottom: 28px; }
        a.btn {
            display: inline-block; padding: 10px 22px; border-radius: 10px;
            background: var(--accent); color: #fff; text-decoration: none; font-weight: 600;
        }
        a.btn:hover { opacity: .9; }
        footer { margin-top: 24px; font-size: .85rem; color: var(--muted); }
    </style>
</head>
<body>
    <main>
        <h1>{{ config('app.name', 'Розклад') }}</h1>
        <p>Розклад занять. Сайт у розробці — незабаром тут з'явиться розклад.</p>
        <a class="btn" href="{{ url('/admin') }}">Увійти</a>
    </main>
    <footer>&copy; {{ date('Y') }} {{ config('app.name', 'Розклад') }}</footer>
</body>
</html>
