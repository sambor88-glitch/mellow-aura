@props(['seo', 'cartCount' => 0])
{{-- Page frame from MellowAura.dc.html: sticky header, content, dark footer. --}}
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-shared::seo :seo="$seo" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body>
<a href="#tresc" class="ma-skip-link">Przejdź do treści</a>
<div class="ma-page">
    <x-shared::header :cart-count="$cartCount" />

    <main id="tresc" class="ma-main" tabindex="-1">
        {{ $slot }}
    </main>

    <x-shared::footer />
</div>
</body>
</html>
