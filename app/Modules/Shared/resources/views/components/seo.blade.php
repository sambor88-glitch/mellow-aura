@props(['seo'])
@inject('business', 'App\Modules\Shared\Seo\BusinessSchema')
<title>{{ $seo->title }}</title>
@if ($seo->description !== '')
    <meta name="description" content="{{ $seo->description }}">
@endif
@if ($seo->robots)
    <meta name="robots" content="{{ $seo->robots }}">
@endif
<link rel="canonical" href="{{ $seo->canonical() }}">
<meta property="og:title" content="{{ $seo->title }}">
@if ($seo->description !== '')
    <meta property="og:description" content="{{ $seo->description }}">
@endif
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:locale" content="pl_PL">
<meta property="og:url" content="{{ $seo->canonical() }}">
<meta property="og:site_name" content="MellowAura">
@foreach ([$business->toArray(), ...$seo->schema] as $block)
    {{-- JSON_HEX_TAG keeps "</script>" in shop data from closing the tag. --}}
    <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
