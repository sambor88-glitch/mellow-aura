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
    <div class="flex min-h-screen flex-col bg-sand">
        <x-shared::header />

        <main class="flex-1">
            {{ $slot }}
        </main>

        <x-shared::footer />
    </div>

    @includeIf('cart::drawer')
    @includeIf('consent::banner')
</body>
</html>
