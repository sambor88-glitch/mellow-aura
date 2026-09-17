@props(['title', 'description' => null, 'canonical' => null, 'noindex' => false, 'type' => 'website', 'image' => null])
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-shared::seo :$title :$description :$canonical :$noindex :$type :$image />
    @includeIf('consent::head')
    @isset($head)
        {{ $head }}
    @endisset
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{-- The first thing a keyboard reaches: past the logo and the menu straight to the page. --}}
    <a href="#tresc" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-70 focus:rounded-full focus:bg-ink focus:px-5 focus:py-3 focus:text-[14px] focus:text-linen">Przejdź do treści</a>
    <div class="flex min-h-screen flex-col bg-sand">
        <x-shared::header />

        <main id="tresc" tabindex="-1" class="flex-1 outline-none">
            {{ $slot }}
        </main>

        <x-shared::footer />
    </div>

    @includeIf('cart::drawer')
    @includeIf('consent::banner')
</body>
</html>
