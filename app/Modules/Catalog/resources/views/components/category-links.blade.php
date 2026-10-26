@props(['slugs', 'heading'])
@use('App\Modules\Catalog\Models\Category')
@use('App\Modules\Localization\Support\Locales')
{{-- Descriptive links from a service page into the shop (MA-116), by the categories' Polish slugs. A category that
     Kasia removed, or one not written in the language of this page, drops out. --}}
@php
    $categories = Category::query()->whereIn('slug', $slugs)->with('translations')->get()
        ->filter(fn (Category $category) => $category->hasTranslation(Locales::current()))
        ->sortBy(fn (Category $category) => array_search($category->getRawOriginal('slug'), $slugs, true));
@endphp
@if ($categories->isNotEmpty())
    <nav aria-label="{{ $heading }}" {{ $attributes->merge(['class' => 'min-w-0']) }}>
        <h2 class="mb-3 text-[11.5px] tracking-[0.16em] text-label uppercase">{{ $heading }}</h2>
        <ul class="flex flex-wrap gap-2.5">
            @foreach ($categories as $category)
                <li><a href="{{ route('shop.category', $category) }}" class="inline-block rounded-full border border-line bg-sand-dark px-[18px] py-2.5 text-[13px] text-ink transition-[border-color,background-color] duration-300 hover:border-ink hover:text-ink">{{ __('catalog::shop.category_link', ['category' => $category->name]) }}</a></li>
            @endforeach
        </ul>
    </nav>
@endif
