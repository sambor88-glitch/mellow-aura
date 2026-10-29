{{--
    The missing page inside the usual layout (MA-259): the header with the menu and the cart and the footer stay, so the
    customer can carry on from here. Needs the database like every page — resources/views/errors/404.blade.php takes
    over when it fails. No error code on screen; the status and noindex tell search engines.
--}}
@php
    $button = 'fill-btn inline-flex min-h-[52px] items-center gap-2.5 rounded-full px-[30px] text-[14px] font-medium tracking-[0.04em] active:scale-[.97]';
@endphp
<x-shared::layout title="Nie ma tej strony" :noindex="true">
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-[clamp(56px,10vw,120px)] pb-[clamp(72px,12vw,140px)]">
        <div class="mb-6 flex items-center gap-3 text-[10.5px] tracking-[0.3em] text-brown uppercase before:h-px before:w-[34px] before:flex-none before:bg-gold">nie ma tej strony</div>
        <h1 class="max-w-[15ch] font-serif text-[length:clamp(42px,6.2vw,84px)] leading-[.98] font-light tracking-[-0.02em] text-balance">Tej strony tu nie ma</h1>
        <p class="mt-[26px] max-w-[46ch] text-[17.5px] leading-[1.68] text-lead text-pretty">Adres mógł się zmienić albo w linku zabrakło litery. Zajrzyj do sklepu albo zacznij od strony głównej.</p>
        <div class="mt-[34px] flex flex-wrap gap-3">
            <a href="{{ route('shop.index') }}" class="{{ $button }} aura-btn-main bg-ink text-linen transition-[background-color,color] duration-300 [--fill:var(--color-rose)] hover:bg-rose hover:text-ink">Przejdź do sklepu</a>
            <a href="{{ route('home') }}" class="{{ $button }} aura-btn-glass glass text-ink transition-[border-color,color] duration-300 [--fill:var(--color-cream)] hover:border-ink hover:text-ink">Strona główna</a>
        </div>
    </div>
</x-shared::layout>
