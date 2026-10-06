<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ config('app.name') }}</title>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#faf7fd;--ink:#241533;--muted:#6b5c7d;--ube:#5b2f8a;--lime:#c8e04a}
@media(prefers-color-scheme:dark){:root{--bg:#150d1f;--ink:#f3ecfa;--muted:#b7a6cc;--ube:#b98cf0}}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.55 'DM Sans',system-ui,sans-serif;display:grid;place-items:center;padding:24px}
main{text-align:center;max-width:32rem}
.dot{width:56px;height:56px;border-radius:50% 50% 50% 12px;background:var(--lime);margin:0 auto 20px}
h1{font:800 clamp(34px,8vw,56px)/1.05 'Bricolage Grotesque','DM Sans',sans-serif;margin:0 0 12px;color:var(--ube)}
p{color:var(--muted);margin:0}
</style>
</head>
<body>
<main>
  <div class="dot" aria-hidden="true"></div>
  <h1>{{ config('app.name') }}</h1>
  <p>Kumusta! Lutong bahay, delivered in Riyadh. Our online menu is coming soon.</p>
</main>
</body>
</html>