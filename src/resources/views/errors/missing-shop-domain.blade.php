<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop required — {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f6f6f7;
            --card: #ffffff;
            --text: #202223;
            --muted: #6d7175;
            --border: #e1e3e5;
            --accent: #008060;
            --code-bg: #f1f2f3;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 1.5rem;
        }
        main {
            width: min(40rem, 100%);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.75rem 1.5rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / 4%);
        }
        h1 { margin: 0 0 0.5rem; font-size: 1.35rem; }
        p { margin: 0 0 1rem; color: var(--muted); line-height: 1.5; }
        code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.9em;
            background: var(--code-bg);
            padding: 0.15em 0.4em;
            border-radius: 4px;
        }
        ol { margin: 0; padding-left: 1.25rem; color: var(--text); line-height: 1.6; }
        li { margin-bottom: 0.5rem; }
        .hint {
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            font-size: 0.9rem;
            color: var(--muted);
        }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <main>
        <h1>Shop domain required</h1>
        <p>
            Shopify OAuth needs a store domain. Opening
            <code>/authenticate</code> without <code>?shop=</code> (or an authenticated session) cannot start install.
        </p>
        <ol>
            <li>
                Use your HTTPS tunnel URL with a shop, for example:<br>
                <code>{{ rtrim((string) config('app.url'), '/') }}/authenticate?shop=YOUR_STORE.myshopify.com</code>
            </li>
            <li>Or install/open the app from Shopify Admin on a development store.</li>
        </ol>
        <p class="hint">
            Local <code>http://localhost</code> alone is fine for health checks; Partners App URL and OAuth must use the tunnel.
        </p>
    </main>
</body>
</html>
