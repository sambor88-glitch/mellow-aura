{{--
    Error pages stand on their own: no database, no settings from the panel and no built stylesheet, because the error
    may be exactly that one of them is broken. Colours and type follow resources/css/app.css; the site's typefaces load
    only when the build is in place. No error codes on screen — the code goes to the log, not to the customer.
--}}
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | MellowAura</title>
    @php
        try {
            echo app(\Illuminate\Foundation\Vite::class)->fonts();
        } catch (\Throwable) {
            // Without the build the page keeps the system typefaces.
        }
    @endphp
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; background: #F3EDE4; color: #2F2620; font-family: 'Instrument Sans', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: #855F3D; text-decoration: none; transition: color .3s, background-color .3s, border-color .3s; }
        a:hover { color: #24417E; }
        :focus-visible { outline: 2px solid #24417E; outline-offset: 2px; }
        .page { display: flex; min-height: 100vh; flex-direction: column; }
        .wrap { max-width: 1280px; margin: 0 auto; padding: 0 28px; }
        header { border-bottom: 1px solid #E2D7C7; }
        header .wrap { padding-top: 16px; padding-bottom: 16px; }
        .brand, .brand:hover { display: inline-flex; flex-direction: column; gap: 2px; color: #2F2620; }
        .brand-name { font-family: Newsreader, serif; font-size: 25px; line-height: 1; letter-spacing: .16em; text-transform: uppercase; }
        .brand-tagline { font-size: 9px; letter-spacing: .3em; text-transform: uppercase; color: #726456; }
        main { flex: 1; padding: 72px 0 84px; }
        .eyebrow { display: flex; align-items: center; gap: 12px; margin: 0 0 26px; font-size: 10.5px; letter-spacing: .3em; text-transform: uppercase; color: #855F3D; }
        .eyebrow::before { content: ''; width: 34px; height: 1px; flex: none; background: #B98E64; }
        h1 { max-width: 15ch; margin: 0; font-family: Newsreader, serif; font-size: clamp(42px, 6.2vw, 84px); font-weight: 300; line-height: .98; letter-spacing: -.02em; text-wrap: pretty; }
        .lead { max-width: 46ch; margin: 26px 0 0; font-size: 17.5px; line-height: 1.68; color: #5C5043; text-wrap: pretty; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 34px; }
        .button { display: inline-flex; min-height: 44px; align-items: center; border-radius: 999px; padding: 16px 32px; font-size: 14px; letter-spacing: .04em; }
        .button-primary, .button-primary:hover { background: #2F2620; color: #F7F2EA; }
        .button-primary:hover { background: #D6A39C; color: #2F2620; }
        .button-secondary, .button-secondary:hover { border: 1px solid #C6B8A5; color: #2F2620; }
        .button-secondary:hover { border-color: #2F2620; background: #EDE4D8; }
        @media (prefers-reduced-motion: reduce) { a { transition: none; } }
    </style>
</head>
<body>
    <div class="page">
        <header>
            <div class="wrap">
                <a href="{{ url('/') }}" class="brand">
                    <span class="brand-name">mellowaura</span>
                    <span class="brand-tagline">ceramika &middot; rękodzieło &middot; kraków</span>
                </a>
            </div>
        </header>
        <main>
            <div class="wrap">
                <p class="eyebrow">@yield('eyebrow')</p>
                <h1>@yield('heading')</h1>
                <p class="lead">@yield('lead')</p>
                <div class="actions">
                    @yield('actions')
                </div>
            </div>
        </main>
    </div>
</body>
</html>
