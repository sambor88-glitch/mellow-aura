@props(['product', 'delay' => 0, 'eager' => false, 'withVariantCount' => true])
@php
    $image = $product->getFirstMedia('images');
    $variants = $product->variants->count();
    $variantsLabel = in_array($variants % 10, [2, 3, 4], true) && ! in_array($variants % 100, [12, 13, 14], true) ? 'warianty' : 'wariantów';
@endphp
<div class="group relative animate-ma-up" style="animation-delay: {{ $delay }}s">
<a href="{{ route('product.show', $product) }}" class="block text-ink hover:text-ink">
    <div class="relative mb-3.5 overflow-hidden rounded-[6px] bg-line-soft transition-[box-shadow,transform] duration-500 ease-clay group-hover:-translate-y-1 group-hover:shadow-card-hover">
        @if ($image)
            <img src="{{ $image->getAvailableUrl(['card']) }}" alt="{{ $image->getCustomProperty('alt') ?: $product->name }}" @unless ($eager) loading="lazy" @endunless class="block aspect-[4/5] w-full object-cover">
        @else
            <div class="aspect-[4/5] w-full"></div>
        @endif
    </div>
    <div class="mb-[5px] text-[10px] tracking-[0.22em] text-hint uppercase">{{ $product->category->name }}</div>
    <div class="mb-1.5 font-serif text-[20px] leading-[1.25]">{{ $product->name }}</div>
    <div class="mb-3 flex items-baseline gap-[9px] text-[14px] text-lead">
        <span><x-catalog::price-label :product="$product" /></span>
    </div>
    @if ($withVariantCount && $variants > 1)
        <div class="text-[12px] text-hint">{{ $variants }} {{ $variantsLabel }}</div>
    @endif
</a>
<x-catalog::favorite-button :product="$product"
                            class="absolute top-2.5 right-2.5 z-2 size-11 rounded-full bg-cream/92 text-[19px] duration-500 ease-clay group-hover:-translate-y-1 hover:text-navy" />
</div>
