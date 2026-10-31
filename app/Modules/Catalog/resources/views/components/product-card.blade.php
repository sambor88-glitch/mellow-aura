@props(['product', 'delay' => 0, 'eager' => false, 'withVariantCount' => true, 'phoneColumns' => 2, 'withButton' => true])
@php
    $photos = $product->getMedia('images');
    $image = $photos->first();
    // A second photo spills over the first in a circle on hover (mellowaura-design, „Karta produktu”).
    $second = $photos->get(1);
    // One tile is about 290 px wide on a computer and half the phone screen, two to a row.
    $srcset = $image ? \App\Modules\Catalog\Support\ProductPhoto::srcset($image) : null;
    $variants = $product->variants->count();
    // „3 rozmiary”, „3 kwoty” — the panel says what the variants differ in.
    $kind = $product->variant_kind ?? \App\Modules\Catalog\Enums\VariantKind::Options;
    $variantsLabel = $kind->count($variants);
    // One button in the shop and in the gift finder (MA-259). A single size goes straight to the cart and the customer
    // stays on the shelf; several sizes lead to the product page — the card never guesses.
    // A voucher asks for the name and dedication to print on its product page, so it never skips there (MA-296).
    $only = $variants === 1 && ! $product->isVoucher() ? $product->variants->first() : null;
    $soldOut = $product->variants->every(fn ($variant) => ! $variant->isInStock());
@endphp
<div data-shelf-card @class(['group relative flex animate-ma-up flex-col', 'h-full' => $withButton]) style="animation-delay: {{ $delay }}s">
<a href="{{ route('product.show', $product) }}" class="block text-ink hover:text-ink">
    <div class="relative isolate mb-3.5 overflow-hidden rounded-[18px] bg-linen transition-[box-shadow,transform] duration-500 ease-clay group-hover:-translate-y-1 group-hover:shadow-card-hover">
        @if ($image)
            {{-- data-vt-photo: on the way to the product page this photo grows into the product photo (resources/js/transitions.js). --}}
            <img data-vt-photo src="{{ $image->getAvailableUrl(['card']) }}" alt="{{ $image->getCustomProperty('alt') ?: $product->name }}" @unless ($eager) loading="lazy" @endunless
                 @if ($srcset) srcset="{{ $srcset }}" sizes="{{ \App\Modules\Catalog\Support\ProductPhoto::cardSizes($phoneColumns) }}" @endif
                 width="1200" height="1500" style="{{ \App\Modules\Shared\Support\FocalPoint::style($image) }}" class="block aspect-[4/5] w-full object-cover saturate-[.8] transition-[transform,filter] duration-[1.1s] ease-clay group-hover:scale-[1.06] group-hover:saturate-100">
            @if ($second)
                {{-- Asked for only when a mouse comes over the card or the keyboard reaches it (resources/js/hover-photos.js):
                     downloaded with the page, it shared the first screen's line with the first photo, and a phone never shows it (MA-271). --}}
                <span class="absolute inset-0">
                    <img data-hover-src="{{ $second->getAvailableUrl(['card']) }}" alt="" width="1200" height="1500" style="{{ \App\Modules\Shared\Support\FocalPoint::style($second) }}"
                         @if ($secondSrcset = \App\Modules\Catalog\Support\ProductPhoto::srcset($second)) data-hover-srcset="{{ $secondSrcset }}" sizes="{{ \App\Modules\Catalog\Support\ProductPhoto::cardSizes($phoneColumns) }}" @endif
                         class="size-full object-cover [clip-path:circle(0%_at_50%_60%)] transition-[clip-path] duration-900 ease-clay group-hover:[clip-path:circle(75%_at_50%_60%)] group-focus-within:[clip-path:circle(75%_at_50%_60%)]">
                </span>
            @endif
        @else
            <div class="aspect-[4/5] w-full"></div>
        @endif
        <span class="glass absolute bottom-2.5 left-2.5 z-2 flex items-baseline gap-2 rounded-full px-3 py-1.5 text-[13px] text-ink sm:bottom-3 sm:left-3 sm:px-3.5 sm:py-2 sm:text-[14px]"><x-catalog::price-label :product="$product" /></span>
    </div>
    {{-- The category above the name, one line: side by side both broke into two on a phone. Tighter letters on a phone fit „Kadzielnice i podstawki”. --}}
    {{-- A category not written in English has no name on /en/: no empty line then. --}}
    @if (filled($product->category->name))
        <div class="mb-1 truncate text-[10px] tracking-[0.2em] text-label uppercase max-sm:tracking-[0.06em]">{{ $product->category->name }}</div>
    @endif
    <div class="font-serif text-[length:clamp(18px,4.8vw,22px)] leading-[1.15] font-light text-balance">{{ $product->name }}</div>
    @if ($withVariantCount && $variants > 1)
        <div class="mt-1 text-[12px] text-label">{{ $variantsLabel }}</div>
    @endif
</a>
@if ($withButton && ! $soldOut)
    {{-- At the bottom of the card, so the buttons in a row line up when a name takes two lines. --}}
    {{-- Below 360 px a smaller font and padding keep „Wybierz rozmiar” on one line in a 125 px card (MA-305). --}}
    <div class="mt-auto pt-3">
    @if ($only)
        <form method="post" action="{{ route('cart.store') }}" x-data x-on:submit.prevent="$store.cart.send($el)">
            @csrf
            <input type="hidden" name="variant_id" value="{{ $only->id }}">
            <button class="min-h-11 w-full rounded-full border border-line-strong px-[18px] py-[9px] text-[13px] text-ink max-[359px]:px-2.5 max-[359px]:text-[12px] transition duration-300 hover:border-ink hover:bg-ink hover:text-linen active:scale-[.97] sm:w-auto">
                {{ __('catalog::shop.card_add') }}<span class="sr-only">: {{ $product->name }}</span>
            </button>
        </form>
    @else
        <a href="{{ route('product.show', $product) }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-full border border-line-strong px-[18px] py-[9px] text-[13px] text-ink max-[359px]:px-2.5 max-[359px]:text-[12px] transition duration-300 hover:border-ink hover:bg-ink hover:text-linen active:scale-[.97] sm:w-auto">
            {{ $variants === 1 ? __('catalog::shop.card_voucher') : $kind->choose() }}<span class="sr-only">: {{ $product->name }}</span>
        </a>
    @endif
    </div>
@endif
<x-catalog::favorite-button :product="$product"
                            class="glass absolute top-2.5 right-2.5 z-2 size-11 rounded-full text-[19px] duration-500 ease-clay group-hover:-translate-y-1 hover:text-navy" />
</div>
