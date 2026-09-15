@use('App\Modules\Shared\Support\Money')
@props(['product', 'delay' => 0, 'eager' => false])
@php
    $image = $product->getFirstMedia('images');
    $lowest = $product->variants->min('price_gross');
    $highest = $product->variants->max('price_gross');
    $variants = $product->variants->count();
    $variantsLabel = in_array($variants % 10, [2, 3, 4], true) && ! in_array($variants % 100, [12, 13, 14], true) ? 'warianty' : 'wariantów';
@endphp
<div class="animate-ma-up" style="animation-delay: {{ $delay }}s">
    <div class="relative mb-3.5 overflow-hidden rounded-[6px] bg-line-soft transition-[box-shadow,transform] duration-500 ease-clay hover:-translate-y-1 hover:shadow-[0_26px_50px_-30px_rgba(47,38,32,.5)]">
        @if ($image)
            <img src="{{ $image->getUrl() }}" alt="{{ $image->getCustomProperty('alt') ?: $product->name }}" @unless ($eager) loading="lazy" @endunless class="block aspect-[4/5] w-full object-cover">
        @else
            <div class="aspect-[4/5] w-full"></div>
        @endif
    </div>
    <div class="mb-[5px] text-[10px] tracking-[0.22em] text-hint uppercase">{{ $product->category->name }}</div>
    <div class="mb-1.5 font-serif text-[20px] leading-[1.25]">{{ $product->name }}</div>
    <div class="mb-3 flex items-baseline gap-[9px] text-[14px] text-lead">
        <span>{{ $lowest === $highest ? Money::format($lowest) : 'od '.Money::format($lowest) }}</span>
    </div>
    @if ($variants > 1)
        <div class="text-[12px] text-hint">{{ $variants }} {{ $variantsLabel }}</div>
    @endif
</div>
