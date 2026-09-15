@props(['title', 'description' => null, 'canonical' => null, 'noindex' => false, 'type' => 'website', 'image' => null])
@use('App\Modules\Shared\Support\BusinessStructuredData')
@inject('settings', 'App\Modules\Settings\Settings')
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
<meta property="og:title" content="{{ $title }}">
@if ($description)
    <meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:type" content="{{ $type }}">
<meta property="og:locale" content="pl_PL">
@if ($canonical)
    <meta property="og:url" content="{{ $canonical }}">
@endif
@if ($image)
    <meta property="og:image" content="{{ $image }}">
@endif
{!! BusinessStructuredData::for($settings) !!}
