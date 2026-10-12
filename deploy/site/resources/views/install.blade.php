<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Setup | {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #111827; margin: 0; padding: 24px 16px; }
        main { max-width: 640px; margin: 0 auto; }
        h1 { font-size: 24px; margin: 0 0 4px; }
        h2 { font-size: 17px; margin: 0 0 10px; }
        .card { background: #fff; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 18px; margin-top: 16px; }
        .muted { color: #6b7280; font-size: 14px; }
        ul { list-style: none; padding: 0; margin: 0; }
        li { display: flex; gap: 10px; padding: 8px 0; border-top: 1px solid #f1f1f1; font-size: 15px; }
        li:first-child { border-top: 0; }
        .ok { color: #15803d; font-weight: 700; } .bad { color: #b91c1c; font-weight: 700; }
        .hint { display: block; color: #6b7280; font-size: 13px; word-break: break-word; }
        .msg { border-radius: 10px; padding: 10px 12px; margin-top: 16px; font-size: 15px; }
        .msg.ok { background: #dcfce7; color: #166534; font-weight: 500; } .msg.error { background: #fee2e2; color: #991b1b; font-weight: 500; }
        pre { background: #111827; color: #e5e7eb; padding: 12px; border-radius: 10px; overflow: auto; font-size: 12px; max-height: 260px; }
        label { display: block; font-size: 14px; font-weight: 600; margin: 10px 0 4px; }
        input[type=text], input[type=email], input[type=password] { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 16px; }
        button { margin-top: 12px; background: #15803d; color: #fff; border: 0; border-radius: 8px; padding: 11px 18px; font-size: 15px; font-weight: 600; cursor: pointer; }
        button:disabled { background: #9ca3af; cursor: not-allowed; }
        .done { background: #ecfdf5; border: 1px solid #a7f3d0; }
        code { background: #f3f4f6; padding: 1px 5px; border-radius: 4px; }
    </style>
</head>
<body>
<main>
    <h1>{{ config('app.name') }}: setup</h1>
    <p class="muted">One-time setup for the hosted website. Follow the steps from top to bottom.</p>

    @if ($message)
        <div class="msg {{ $message[0] }}">{{ $message[1] }}</div>
    @endif

    <section class="card">
        <h2>1. Checks</h2>
        <ul>
            @foreach ($checks as [$label, $ok, $hint])
                <li>
                    <span class="{{ $ok ? 'ok' : 'bad' }}">{{ $ok ? 'OK' : 'X' }}</span>
                    <span>{{ $label }} @unless ($ok)<span class="hint">{{ $hint }}</span>@endunless</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="card">
        <h2>2. Set up database</h2>
        <p class="muted">Creates all the tables. Safe to press more than once; it only adds what is missing.</p>
        <form method="POST" action="{{ url('/install/migrate') }}">
            <input type="hidden" name="key" value="{{ $key }}">
            <button type="submit" @disabled(! $dbOk)>{{ $tablesOk ? 'Run again (check for updates)' : 'Set up database' }}</button>
        </form>
        @if ($output)
            <pre>{{ $output }}</pre>
        @endif
    </section>

    <section class="card">
        <h2>3. Admin account</h2>
        @if (! $tablesOk)
            <p class="muted">Do step 2 first.</p>
        @elseif ($admins > 0)
            <p class="muted">{{ $admins }} admin {{ $admins === 1 ? 'account exists' : 'accounts exist' }}. You can add another below if needed.</p>
        @else
            <p class="muted">Create the account you will use to log in to the admin dashboard.</p>
        @endif

        @if ($tablesOk)
            <form method="POST" action="{{ url('/install/admin') }}" autocomplete="off">
                <input type="hidden" name="key" value="{{ $key }}">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
                <label for="password">Password (at least 8 characters)</label>
                <input type="password" id="password" name="password" minlength="8" required>
                <button type="submit">Create admin account</button>
            </form>
        @endif
    </section>

    <section class="card {{ $tablesOk && $admins > 0 ? 'done' : '' }}">
        <h2>4. Finish</h2>
        <p class="muted" style="color:#111827">
            When steps 1 to 3 are done, open <code>.env</code> in the File Manager and <strong>delete the line <code>INSTALL_KEY=...</code></strong>.
            This setup page then turns off. Then open <a href="{{ url('/') }}">your website</a> and <a href="{{ url('/admin/login') }}">log in</a>.
        </p>
    </section>
</main>
</body>
</html>