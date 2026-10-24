@props(['seo', 'image' => null])
@php
    $graph = [$organization->toArray(), ...$seo->schema];
    $jsonLd = json_encode(
        ['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
    );
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo->title }}</title>
    @if ($seo->description !== '')
        <meta name="description" content="{{ $seo->description }}">
    @endif
    <link rel="canonical" href="{{ $seo->canonicalUrl() }}">
    @unless ($seo->indexable)
        <meta name="robots" content="noindex, follow">
    @endunless
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pl_PL">
    <meta property="og:site_name" content="MellowAura">
    <meta property="og:title" content="{{ $seo->title }}">
    @if ($seo->description !== '')
        <meta property="og:description" content="{{ $seo->description }}">
    @endif
    <meta property="og:url" content="{{ $seo->canonicalUrl() }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
    @endif
    <meta name="theme-color" content="#F3EDE4">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script type="application/ld+json">{!! $jsonLd !!}</script>
</head>
<body>
<div class="ma-page">
    @include('shared::partials.header')

    <main id="tresc" class="ma-main">
        {{ $slot }}
    </main>

    @include('shared::partials.footer')
</div>
</body>
</html>
