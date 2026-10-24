@use('App\Modules\Shared\Support\Plural')
@props(['product', 'delay' => 0, 'heading' => 'h3'])
@php
    $photos = $product->photos();
    $url = route('product.show', $product);
@endphp
<article class="ma-card" style="animation-delay: {{ $delay }}s">
    <a href="{{ $url }}" class="ma-card__media" tabindex="-1" aria-hidden="true"
       x-data="cardGallery({{ $photos->count() }})" @mouseenter="start()" @mouseleave="stop()">
        @forelse ($photos as $photo)
            <img src="{{ $photo->getUrl() }}" alt="{{ $loop->first ? $product->name : '' }}" loading="lazy"
                 @if ($photos->count() > 1) :style="{ opacity: index === {{ $loop->index }} ? 1 : 0 }" @endif
                 @if (! $loop->first) style="opacity: 0" @endif>
        @empty
            <span class="ma-card__empty">Zdjęcie w drodze z pracowni</span>
        @endforelse
        @if ($photos->count() > 1)
            <span class="ma-card__dots">
                @foreach ($photos as $photo)
                    <span class="ma-card__dot" :data-active="index === {{ $loop->index }} ? '' : null" @if ($loop->first) data-active @endif></span>
                @endforeach
            </span>
        @endif
        @if ($product->discountedVariant())
            <span class="ma-badge">promocja</span>
        @endif
    </a>
    @if ($product->category)
        <div class="ma-card__category">{{ $product->category->name }}</div>
    @endif
    <{{ $heading }} class="ma-card__title"><a href="{{ $url }}" class="ma-card__name">{{ $product->name }}</a></{{ $heading }}>
    <x-catalog::price :product="$product" />
    @if ($product->variants->count() > 1)
        <div class="ma-card__meta">{{ Plural::count($product->variants->count(), 'wariant', 'warianty', 'wariantów') }}</div>
    @endif
</article>
