<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} – {{ config('app.name') }}</title>
    <style>
        :root{--bg:#faf7fd;--ink:#241533;--muted:#6b5c7d;--ube:#5b2f8a;--lime:#c8e04a}
        @media(prefers-color-scheme:dark){:root{--bg:#150d1f;--ink:#f3ecfa;--muted:#b7a6cc;--ube:#b98cf0}}
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:var(--bg);color:var(--ink);font:16px/1.55 'DM Sans',system-ui,sans-serif;text-align:center}
        .code{font:800 clamp(64px,18vw,120px)/1 system-ui,sans-serif;color:var(--ube)}
        h1{margin:8px 0;font-size:28px}
        p{color:var(--muted);max-width:42ch;margin:8px auto 24px}
        a{display:inline-block;background:var(--lime);color:#241533;font-weight:700;text-decoration:none;border-radius:999px;padding:14px 26px}
    </style>
</head>
<body>
    <main>
        <div class="code">{{ $code }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ url('/') }}">Back to the menu</a>
    </main>
</body>
</html>