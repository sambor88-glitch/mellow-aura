@use('App\Modules\Catalog\Support\ShopShortcuts')
@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\DispatchTime')
@use('App\Modules\Shared\Support\Seo')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $title = $category
        ? ($category->seo_title ?: Seo::title($category->name, __('catalog::shop.category_title_suffix')))
        : __('catalog::shop.title');

    $dispatch = DispatchTime::label($settings, short: true);
    $shipping = $dispatch ? __('catalog::shop.shipping', ['dispatch' => $dispatch]) : '';
    // Without a description from the panel a category names what is in it, so no two categories share one.
    $description = Seo::description(match (true) {
        filled($category?->seo_description) => $category->seo_description,
        $category !== null => __('catalog::shop.category_description', [
            'category' => $category->name,
            'items' => $products->isNotEmpty() ? ': '.$products->map(fn ($product) => Str::lcfirst($product->name))->join(', ') : '',
            'shipping' => $shipping,
        ]),
        default => __('catalog::shop.description', ['shipping' => $shipping]),
    });

    $shortcuts = array_filter(ShopShortcuts::ROUTES, fn (string $route) => Locales::has($route, Locales::current()));
@endphp

<x-shared::layout :title="$title" :description="$description" :canonical="$baseUrl" :noindex="$search !== ''">
    @isset($structuredData)
        <x-slot:head>{!! $structuredData !!}</x-slot:head>
    @endisset

    {{-- #ulubione shows only the cards saved with a heart; the list stays in the browser (see resources/js/favorites.js). --}}
    <div x-data="{ favorites: location.hash === '#ulubione', ids: @js($products->pluck('id')), get savedHere() { return this.ids.filter((id) => $store.favorites.has(id)).length } }"
         x-on:hashchange.window="favorites = location.hash === '#ulubione'"
         class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <nav aria-label="{{ __('catalog::shop.breadcrumb') }}" class="mb-[22px] text-[12px] text-hint">
            <a href="{{ route('home') }}" class="text-hint hover:text-navy">{{ __('catalog::shop.home') }}</a>
            / @if ($category) <a href="{{ route('shop.index') }}" class="text-hint hover:text-navy">{{ __('catalog::shop.products') }}</a> / @endif<span class="text-lead">{{ $category->name ?? __('catalog::shop.products') }}</span>
        </nav>

        <h1 class="mb-3.5 font-serif text-[length:clamp(38px,5vw,66px)] leading-[normal] font-light tracking-[-0.02em]">
            <span x-show="! favorites">{{ $category->name ?? __('catalog::shop.heading') }}</span><span x-cloak x-show="favorites">{{ __('catalog::shop.favorites') }}</span>
        </h1>

        <p x-cloak x-show="favorites" class="mb-[26px] max-w-[58ch] text-[16.5px] text-muted">{{ __('catalog::shop.favorites_intro') }}</p>
        @if (! $category)
            <p x-show="! favorites" class="mb-[26px] max-w-[58ch] text-[16.5px] text-muted">{{ __('catalog::shop.intro') }}</p>
        @elseif (filled($category->intro))
            <p x-show="! favorites" class="mb-[26px] max-w-[58ch] text-[16.5px] text-muted">{{ $category->intro }}</p>
        @endif

        @if ($shortcuts)
            {{-- On a phone both rows below swipe sideways in one line, so the first products show on the first screen. --}}
            <section x-show="! favorites" aria-labelledby="shop-shortcuts" class="mb-8 md:mb-10">
                <h2 id="shop-shortcuts" class="mb-2.5 text-[11px] tracking-[0.14em] text-hint uppercase">{{ __('catalog::shop.shortcuts_heading') }}</h2>
                <div class="-mx-7 flex snap-x snap-mandatory scroll-px-7 gap-2.5 overflow-x-auto px-7 pb-1 [scrollbar-width:none] md:mx-0 md:flex-wrap md:overflow-visible md:px-0 md:pb-0 [&::-webkit-scrollbar]:hidden">
                    @foreach ($shortcuts as $label => $route)
                        @php
                            $note = $settings->get(ShopShortcuts::noteKey($label, Locales::current()));
                        @endphp
                        <a href="{{ route($route) }}" class="flex w-[156px] shrink-0 snap-start flex-col rounded-[18px] border border-line bg-cream/60 px-3.5 py-3 text-ink transition-[border-color,background-color,transform] duration-300 hover:border-ink hover:text-ink active:scale-[.98] md:w-[178px]">
                            <span class="text-[14px] leading-[1.25] font-medium">{{ __('catalog::shop.shortcut_'.$label) }}@if (! $note)&nbsp;<span aria-hidden="true" class="font-normal text-lead">→</span>@endif</span>
                            @if ($note)
                                <span class="mt-1 text-[12.5px] leading-[1.3] text-muted">{{ $note }}&nbsp;<span aria-hidden="true" class="text-lead">→</span></span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="mb-[34px] flex flex-wrap items-center justify-between gap-[18px] border-b border-divider pb-5">
            <div class="flex min-w-0 basis-full flex-wrap items-center gap-2.5 md:basis-auto">
                <form method="get" action="{{ $baseUrl }}" role="search" class="flex min-w-0 flex-[1_1_100%] items-center gap-2 rounded-full border border-line bg-cream px-3.5 py-2 focus-within:border-ink md:flex-[0_1_240px]">
                    <span aria-hidden="true" class="text-[13px] text-hint">⌕</span>
                    <input type="text" name="q" value="{{ $search }}" enterkeyhint="search" placeholder="{{ __('catalog::shop.search_placeholder') }}" aria-label="{{ __('catalog::shop.search_label') }}" class="min-w-0 flex-1 border-0 bg-transparent py-0.5 text-[13.5px] text-ink focus:outline-none pointer-coarse:text-[16px]">
                    @if ($sort !== '')
                        <input type="hidden" name="sort" value="{{ $sort }}">
                    @endif
                    @if ($search !== '')
                        <a href="{{ $clearSearchUrl }}" aria-label="{{ __('catalog::shop.search_clear') }}" class="px-0.5 text-[15px] leading-none text-hint hover:text-ink">×</a>
                    @endif
                </form>

                {{-- On a wide screen md:contents drops this box, so the chips wrap next to the search as before. --}}
                <div x-init="const chip = $el.querySelector('[aria-current=page]'); if (chip && chip.offsetLeft + chip.offsetWidth > $el.clientWidth) $el.scrollLeft = chip.offsetLeft - 28"
                     class="relative -mx-7 flex w-[calc(100%+56px)] snap-x snap-mandatory scroll-px-7 gap-2 overflow-x-auto px-7 pb-1 [scrollbar-width:none] md:contents [&::-webkit-scrollbar]:hidden">
                    @foreach ($chips as $chip)
                        <a href="{{ $chip['url'] }}" @if ($chip['active']) aria-current="page" x-bind:aria-current="favorites ? 'false' : 'page'" x-bind:class="favorites && 'border-line-strong! bg-transparent! text-lead!'" @endif @class([
                            'shrink-0 snap-start rounded-full border px-5 py-2.5 text-[13.5px] tracking-[0.03em] whitespace-nowrap',
                            'border-ink bg-ink text-linen hover:text-linen' => $chip['active'],
                            'border-line-strong text-lead hover:text-navy' => ! $chip['active'],
                        ])>{{ $chip['label'] }} <span class="ml-0.5 text-[12px] opacity-85">{{ $chip['count'] }}</span></a>
                    @endforeach
                    <a href="#ulubione" x-cloak x-show="$store.favorites.count > 0 || favorites" x-bind:aria-current="favorites ? 'page' : 'false'"
                       x-bind:class="favorites ? 'border-ink bg-ink text-linen hover:text-linen' : 'border-line-strong text-lead hover:text-navy'"
                       class="shrink-0 snap-start rounded-full border px-5 py-2.5 text-[13.5px] tracking-[0.03em] whitespace-nowrap">♥ {{ __('catalog::shop.favorites_chip') }} <span class="ml-0.5 text-[12px] opacity-85" x-text="savedHere"></span></a>
                </div>
            </div>

            <div class="-mx-7 flex w-[calc(100%+56px)] items-center gap-1.5 overflow-x-auto px-7 [scrollbar-width:none] md:mx-0 md:w-auto md:flex-wrap md:overflow-visible md:px-0 [&::-webkit-scrollbar]:hidden">
                <span class="mr-1.5 shrink-0 text-[12px] tracking-[0.12em] text-hint uppercase">{{ __('catalog::shop.sort') }}</span>
                @foreach ($sorts as $option)
                    <a href="{{ $option['url'] }}" @if ($option['active']) aria-current="true" @endif @class([
                        'shrink-0 rounded-full px-3.5 py-2 text-[13px] whitespace-nowrap hover:text-navy',
                        'bg-sand-dark text-ink' => $option['active'],
                        'text-label' => ! $option['active'],
                    ])>{{ $option['label'] }}</a>
                @endforeach
            </div>
        </div>

        @if ($products->isEmpty())
            <div class="rounded-[20px] bg-sand-dark px-9 py-11 text-center">
                <div class="mb-2 font-serif text-[26px]">{{ __('catalog::shop.empty_title') }}</div>
                <p class="mb-[22px] text-[15.5px] text-lead">{{ __('catalog::shop.empty_text') }}</p>
                @if (Locales::has('custom-orders.index', Locales::current()))
                    <a href="{{ route('custom-orders.index') }}" class="inline-block rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen hover:bg-rose hover:text-ink">{{ __('catalog::shop.empty_cta') }}</a>
                @endif
            </div>
        @endif

        <div x-cloak x-show="favorites && savedHere === 0" class="rounded-[20px] bg-sand-dark px-9 py-11 text-center">
            <div class="mb-2 font-serif text-[26px]">{{ __('catalog::shop.favorites_empty_title') }}</div>
            <p class="mb-[22px] text-[15.5px] text-lead">{{ __('catalog::shop.favorites_empty_text') }}</p>
            <a href="{{ $baseUrl }}" class="inline-block rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen hover:bg-rose hover:text-ink">{{ __('catalog::shop.favorites_empty_cta') }}</a>
        </div>

        <div class="grid grid-cols-2 gap-x-3.5 gap-y-8 sm:grid-cols-[repeat(auto-fill,minmax(240px,1fr))] sm:gap-x-[26px] sm:gap-y-[30px]">
            @foreach ($products as $product)
                <div x-show="! favorites || $store.favorites.has({{ $product->id }})" class="min-w-0">
                    <x-catalog::product-card :product="$product" :delay="min($loop->index, 11) * 0.06" :eager="$loop->index < 2" />
                </div>
            @endforeach
        </div>

        <div x-show="! favorites" class="mt-11 text-[13px] text-hint">{{ __('catalog::shop.count', ['shown' => $products->count(), 'total' => $liveCount]) }}</div>
    </div>
</x-shared::layout>
