{{-- Shown by the panel's service worker when the phone is offline (MA-328). Kept on the phone, so it is plain:
     no data, no login, no stylesheet from the build — the styles are here, as the build may be gone from the cache. --}}
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#EDE4D8">
    <title>Brak połączenia | MellowAura</title>
    <style>
        html, body { margin: 0; min-height: 100dvh; background: #EDE4D8; color: #2F2620; }
        body { display: grid; place-items: center; padding: 24px max(16px, env(safe-area-inset-right)) 24px max(16px, env(safe-area-inset-left));
               box-sizing: border-box; font: 15px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; text-align: center; }
        main { max-width: 34ch; }
        h1 { margin: 0 0 12px; font: 300 30px/1.1 Georgia, "Times New Roman", serif; }
        p { margin: 0 0 24px; color: #5C5043; }
        a { display: inline-flex; align-items: center; min-height: 48px; padding: 0 28px; border-radius: 999px; background: #2F2620; color: #F7F2EA; text-decoration: none; }
        a:focus-visible { outline: 2px solid #2F2620; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <h1>Brak połączenia</h1>
        <p>Telefon nie ma teraz internetu, więc panel się nie otworzy. Nic nie zginęło — gdy zasięg wróci, spróbuj jeszcze raz.</p>
        <a href="{{ url(\App\Modules\Admin\Http\Controllers\PanelAppController::SCOPE) }}">Spróbuj jeszcze raz</a>
    </main>
</body>
</html>
