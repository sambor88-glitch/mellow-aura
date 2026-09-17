@php
    // A link shows up once its page has a route, so the header never points at a missing page.
    $links = [
        ['Sklep', 'shop.index', 'shop.*'],
        ['Prezenty', 'gifts.index', 'gifts.*'],
        ['Warsztaty', 'workshops.index', 'workshops.*'],
        ['O mnie', 'content.about', 'content.about'],
        ['Dziennik', 'journal.index', 'journal.*'],
        ['Kontakt', 'content.contact', 'content.contact'],
    ];
@endphp
{{-- On a phone only the row with the cart stays on screen; the logo and menu scroll away (resources/js/header.js). --}}
<div x-data="stickyHeader" x-bind:style="{ top: offset ? -offset + 'px' : null }" class="sticky top-0 z-60 border-b border-divider bg-sand/94 backdrop-blur-[14px] print:hidden">
    <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-5 px-7 py-4">
        <a href="{{ url('/') }}" x-ref="logo" class="flex flex-col gap-0.5 text-ink hover:text-ink">
            <span class="font-serif text-[25px] leading-none tracking-[0.16em] uppercase">mellowaura</span>
            <span class="text-[9px] tracking-[0.3em] text-label uppercase">ceramika &middot; rękodzieło &middot; kraków</span>
        </a>
        <nav aria-label="Główne menu" class="flex min-w-0 flex-[1_1_200px] flex-wrap gap-5 text-[13.5px] tracking-[0.02em]">
            @foreach ($links as [$label, $route, $pattern])
                @if (Route::has($route))
                    <a href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif class="text-graphite transition-colors duration-250 hover:text-navy">{{ $label }}</a>
                @endif
            @endforeach
        </nav>
        {{-- The catalogue brings the favourites link and the cart module its own button. --}}
        <div x-ref="actions" class="ml-auto flex items-center gap-2.5">
            @includeIf('catalog::favorites.header-link')
            @includeIf('cart::button')
        </div>
    </div>
</div>
