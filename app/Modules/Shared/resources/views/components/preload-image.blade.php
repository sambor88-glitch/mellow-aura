@props(['src', 'srcset' => null, 'sizes' => null])
{{-- The photo that is the page's largest element (LCP), asked for before the stylesheet and the fonts: found only in the <body>,
     on a slow phone it waited over a second behind them (MA-271). srcset and sizes must be the <img>'s own, or the phone
     downloads the photo twice. --}}
<link rel="preload" as="image" href="{{ $src }}" @if ($srcset) imagesrcset="{{ $srcset }}" imagesizes="{{ $sizes }}" @endif fetchpriority="high">
