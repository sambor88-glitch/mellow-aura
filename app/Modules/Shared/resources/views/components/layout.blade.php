@props(['title', 'description' => null, 'canonical' => null, 'noindex' => false, 'type' => 'website', 'image' => null])
@use('App\Modules\Localization\Support\Locales')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-currency="{{ Locales::currency() }}">
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
    <a href="#tresc" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-70 focus:rounded-full focus:bg-ink focus:px-5 focus:py-3 focus:text-[14px] focus:text-linen">{{ __('shared::header.skip') }}</a>
    {{-- Aura: drifting glow and paper grain behind every page, a rose glow after the mouse (mellowaura-design, „Atmosfera”). --}}
    <div class="aura-bg print:hidden" aria-hidden="true"><div class="aura-orb aura-orb-1"></div><div class="aura-orb aura-orb-2"></div><div class="aura-orb aura-orb-3"></div></div>
    <div class="aura-grain print:hidden" aria-hidden="true"></div>
    <div class="aura-cursor print:hidden" aria-hidden="true"></div>
    <svg width="0" height="0" class="absolute" aria-hidden="true" focusable="false">
        <filter id="clay" x="-8%" y="-8%" width="116%" height="116%">
            <feTurbulence id="clay-noise" type="fractalNoise" baseFrequency="0.011 0.017" numOctaves="2" seed="4" result="noise"/>
            <feDisplacementMap id="clay-map" in="SourceGraphic" in2="noise" scale="0" xChannelSelector="R" yChannelSelector="G"/>
        </filter>
    </svg>
    <div class="relative z-2 flex min-h-screen flex-col">
        <x-localization::hint />
        <x-shared::header />
        <x-localization::notice />

        <main id="tresc" tabindex="-1" class="flex-1 outline-none">
            {{ $slot }}
        </main>

        <x-shared::footer />
    </div>

    @includeIf('cart::drawer')
    @includeIf('consent::banner')
</body>
</html>
