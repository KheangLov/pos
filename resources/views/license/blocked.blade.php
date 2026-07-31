<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Licence required — {{ config('license.vendor') }}</title>
    {{-- Styles are inline on purpose: this page has to render correctly even
         if the frontend build is missing or the panel theme fails to load. --}}
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #f4f4f5;
            color: #18181b;
        }
        .card {
            width: 100%;
            max-width: 34rem;
            background: #fff;
            border: 1px solid #e4e4e7;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.08);
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        h1 { margin: 1rem 0 0.5rem; font-size: 1.375rem; line-height: 1.3; }
        p { margin: 0 0 1rem; line-height: 1.6; color: #3f3f46; }
        dl { margin: 1.5rem 0 0; border-top: 1px solid #e4e4e7; padding-top: 1rem; font-size: 0.875rem; }
        .row { display: flex; gap: 1rem; padding: 0.375rem 0; }
        dt { flex: 0 0 9rem; color: #71717a; margin: 0; }
        dd { margin: 0; flex: 1; min-width: 0; }
        code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.75rem;
            word-break: break-all;
            background: #f4f4f5;
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
        }
        .contact { margin-top: 1.5rem; border-top: 1px solid #e4e4e7; padding-top: 1rem; font-size: 0.875rem; }
        a { color: #b45309; }
        @media (prefers-color-scheme: dark) {
            body { background: #18181b; color: #fafafa; }
            .card { background: #27272a; border-color: #3f3f46; }
            p { color: #d4d4d8; }
            dt { color: #a1a1aa; }
            dl, .contact { border-color: #3f3f46; }
            code { background: #3f3f46; }
            .badge { background: #450a0a; color: #fca5a5; }
        }
    </style>
</head>
<body>
    <main class="card">
        <span class="badge">{{ $status->label() }}</span>

        <h1>This copy of {{ config('license.vendor') }} needs a valid licence</h1>

        <p>{{ $message }}</p>

        <p>Your data is untouched — nothing has been deleted, and everything returns to normal the moment a valid licence is installed.</p>

        <dl>
            @if ($license)
                <div class="row"><dt>Licensed to</dt><dd>{{ $license->customer }}</dd></div>
                <div class="row"><dt>Licence ID</dt><dd><code>{{ $license->id }}</code></dd></div>
                @if ($license->expiresAt)
                    <div class="row"><dt>Expired</dt><dd>{{ $license->expiresAt->toFormattedDateString() }}</dd></div>
                @endif
            @endif
            <div class="row"><dt>Machine fingerprint</dt><dd><code>{{ $fingerprint }}</code></dd></div>
        </dl>

        <div class="contact">
            Contact <strong>{{ config('license.vendor') }}</strong> with the fingerprint above to get a licence for this machine.
            @if (config('license.support_email'))
                <br>Email: <a href="mailto:{{ config('license.support_email') }}">{{ config('license.support_email') }}</a>
            @endif
            @if (config('license.support_phone'))
                <br>Phone: {{ config('license.support_phone') }}
            @endif
        </div>
    </main>
</body>
</html>
