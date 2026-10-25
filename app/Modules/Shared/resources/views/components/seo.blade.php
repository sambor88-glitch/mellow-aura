@props(['title', 'description' => null, 'canonical' => null, 'noindex' => false, 'type' => 'website', 'image' => null])
@use('App\Modules\Shared\Support\BusinessStructuredData')
@inject('settings', 'App\Modules\Settings\Settings')
@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\Seo')
@inject('pageSeo', 'App\Modules\Shared\Support\PageSeo')
@php
    $alternates = Locales::alternates(request());
    // A fixed page takes its title and description from the panel („Google”) where they are filled in.
    $routeName = request()->route()?->getName();
    $pageSeo->rememberComposed($routeName, $title, $description);
    $custom = $pageSeo->custom($routeName);
    $title = $custom['title'] ?? $title;
    $description = $custom['description'] !== null ? Seo::description($custom['description']) : $description;
@endphp
<title>{{ $title }}</title>
@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
@if ($canonical)
    <link rel="canonical" href="{{ $canonical }}">
@endif
{{-- Only pages with a twin: a Polish-only page has no other language to point to. --}}
@foreach ($alternates as $locale => $url)
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}">
@endforeach
@if ($alternates)
    <link rel="alternate" hreflang="x-default" href="{{ $alternates[Locales::default()] }}">
@endif
@if ($noindex)
    <meta name="robots" content="noindex">
@endif
<meta property="og:title" content="{{ $title }}">
@if ($description)
    <meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:type" content="{{ $type }}">
<meta property="og:locale" content="{{ Locales::ogLocale() }}">
@foreach (array_keys($alternates) as $locale)
    @continue($locale === Locales::current())
    <meta property="og:locale:alternate" content="{{ Locales::ogLocale($locale) }}">
@endforeach
@if ($canonical)
    <meta property="og:url" content="{{ $canonical }}">
@endif
@if ($image)
    <meta property="og:image" content="{{ $image }}">
@endif
{!! BusinessStructuredData::for($settings) !!}
