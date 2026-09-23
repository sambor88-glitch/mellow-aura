@props(['product', 'delay' => 0, 'eager' => false, 'withVariantCount' => true])
@php
    $photos = $product->getMedia('images');
    $image = $photos->first();
    // A second photo spills over the first in a circle on hover (mellowaura-design, „Karta produktu”).
    $second = $photos->get(1);
    // One tile is about 290 px wide on a computer and fills the phone screen minus the page padding.
    $srcset = $image ? \App\Modules\Catalog\Support\ProductPhoto::srcset($image) : null;
    $variants = $product->variants->count();
    $variantsLabel = in_array($variants % 10, [2, 3, 4], true) && ! in_array($variants % 100, [12, 13, 14], true) ? 'warianty' : 'wariantów';
@endphp
<div data-shelf-card class="group relative animate-ma-up" style="animation-delay: {{ $delay }}s">
<a href="{{ route('product.show', $product) }}" class="block text-ink hover:text-ink">
    <div data-clay class="relative isolate mb-3.5 overflow-hidden rounded-[18px] bg-linen transition-[box-shadow,transform] duration-500 ease-clay group-hover:-translate-y-1 group-hover:shadow-card-hover">
        @if ($image)
            <img src="{{ $image->getAvailableUrl(['card']) }}" alt="{{ $image->getCustomProperty('alt') ?: $product->name }}" @unless ($eager) loading="lazy" @endunless
                 @if ($srcset) srcset="{{ $srcset }}" sizes="(min-width: 640px) 400px, calc(100vw - 56px)" @endif
                 width="1200" height="1500" class="block aspect-[4/5] w-full object-cover saturate-[.8] transition-[transform,filter] duration-[1.1s] ease-clay group-hover:scale-[1.06] group-hover:saturate-100">
            @if ($second)
                <span class="absolute inset-0">
                    <img src="{{ $second->getAvailableUrl(['card']) }}" alt="" loading="lazy" width="1200" height="1500"
                         class="size-full object-cover [clip-path:circle(0%_at_50%_60%)] transition-[clip-path] duration-900 ease-clay group-hover:[clip-path:circle(75%_at_50%_60%)] group-focus-within:[clip-path:circle(75%_at_50%_60%)]">
                </span>
            @endif
        @else
            <div class="aspect-[4/5] w-full"></div>
        @endif
        <span class="glass absolute bottom-3 left-3 z-2 flex items-baseline gap-2 rounded-full px-3.5 py-2 text-[14px] text-ink"><x-catalog::price-label :product="$product" /></span>
    </div>
    <div class="flex items-baseline justify-between gap-3">
        <span class="font-serif text-[22px] leading-[1.15] font-light">{{ $product->name }}</span>
        <span class="text-[10px] tracking-[0.22em] whitespace-nowrap text-label uppercase">{{ $product->category->name }}</span>
    </div>
    @if ($withVariantCount && $variants > 1)
        <div class="mt-1 text-[12px] text-label">{{ $variants }} {{ $variantsLabel }}</div>
    @endif
</a>
<x-catalog::favorite-button :product="$product"
                            class="glass absolute top-2.5 right-2.5 z-2 size-11 rounded-full text-[19px] duration-500 ease-clay group-hover:-translate-y-1 hover:text-navy" />
</div>
