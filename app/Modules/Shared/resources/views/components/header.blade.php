@props(['cartCount' => 0])
@php
    $links = [
        ['/sklep', 'Sklep'],
        ['/prezenty', 'Prezenty'],
        ['/warsztaty-ceramiczne-krakow', 'Warsztaty'],
        ['/o-mnie', 'O mnie'],
        ['/dziennik', 'Dziennik'],
        ['/kontakt', 'Kontakt'],
    ];
@endphp
<header class="ma-header">
    <div class="ma-header__inner">
        <a href="{{ url('/') }}" class="ma-logo" @if (request()->is('/')) aria-current="page" @endif>
            <span class="ma-logo__name">mellowaura</span>
            <span class="ma-logo__tagline">ceramika &middot; rękodzieło &middot; kraków</span>
        </a>
        <nav class="ma-nav" aria-label="Główne menu">
            @foreach ($links as [$path, $label])
                <a href="{{ url($path) }}" @if (request()->is(ltrim($path, '/'), ltrim($path, '/').'/*')) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="ma-header__actions">
            {{-- Becomes the cart drawer in the Cart module; until then a plain link to checkout. --}}
            <a href="{{ url('/zamowienie') }}" class="ma-cart-button" aria-label="Koszyk, produktów: {{ $cartCount }}">
                <span aria-hidden="true">Koszyk</span>
                <span class="ma-cart-button__count" aria-hidden="true">{{ $cartCount }}</span>
            </a>
        </div>
    </div>
</header>
