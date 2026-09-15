@props(['title', 'description' => null, 'canonical' => null, 'noindex' => false])
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @if ($canonical)
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    @if ($noindex)
        <meta name="robots" content="noindex">
    @endif
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
</body>
</html>
