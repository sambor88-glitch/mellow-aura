@use('App\Modules\Shared\Support\DispatchTime')
@use('App\Modules\Shared\Support\Seo')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $title = $category
        ? ($category->seo_title ?: Seo::title($category->name, ' — rękodzieło z Krakowa'))
        : 'Sklep — ceramika i rękodzieło handmade | MellowAura';

    $dispatch = DispatchTime::label($settings, short: true);
    $shipping = $dispatch ? ', wysyłka w '.$dispatch : '';
    // Without a description from the panel a category names what is in it, so no two categories share one.
    $description = Seo::description(match (true) {
        filled($category?->seo_description) => $category->seo_description,
        $category !== null => $category->name.' z pracowni w Krakowie'
            .($products->isNotEmpty() ? ': '.$products->map(fn ($product) => Str::lcfirst($product->name))->join(', ') : '')
            .'. Ręczna robota'.$shipping.'.',
        default => 'Kubki, talerze, wazony, patery, kadzielnice oraz jedwabne scrunchies i opaski. Każda sztuka jedna'.$shipping.', BLIK.',
    });

    // A shortcut shows up once its page has a route.
    $shortcuts = array_filter([
        ['Kubek z Twoim napisem →', 'mug.index'],
        ['Zestawy prezentowe →', 'bundles.index'],
        ['Szukam prezentu →', 'gifts.index'],
        ['Z Twojej apaszki →', 'content.scarf'],
        ['Odcisk Twojej rośliny →', 'content.imprint'],
        ['Zamówienie indywidualne →', 'custom-orders.index'],
    ], fn (array $shortcut) => Route::has($shortcut[1]));
@endphp

<x-shared::layout :title="$title" :description="$description" :canonical="$baseUrl" :noindex="$search !== ''">
    @isset($structuredData)
        <x-slot:head>{!! $structuredData !!}</x-slot:head>
    @endisset

    {{-- #ulubione shows only the cards saved with a heart; the list stays in the browser (see resources/js/favorites.js). --}}
    <div x-data="{ favorites: location.hash === '#ulubione', ids: @js($products->pluck('id')), get savedHere() { return this.ids.filter((id) => $store.favorites.has(id)).length } }"
         x-on:hashchange.window="favorites = location.hash === '#ulubione'"
         class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <nav aria-label="Okruszki" class="mb-[22px] text-[12px] text-hint">
            <a href="{{ url('/') }}" class="text-hint hover:text-navy">Strona główna</a>
            / @if ($category) <a href="{{ route('shop.index') }}" class="text-hint hover:text-navy">Produkty</a> / @endif<span class="text-lead">{{ $category->name ?? 'Produkty' }}</span>
        </nav>

        <h1 class="mb-3.5 font-serif text-[length:clamp(38px,5vw,66px)] leading-[normal] font-light tracking-[-0.02em]">
            <span x-show="! favorites">{{ $category->name ?? 'Sklep' }}</span><span x-cloak x-show="favorites">Ulubione</span>
        </h1>

        <p x-cloak x-show="favorites" class="mb-[26px] max-w-[58ch] text-[16.5px] text-muted">Rzeczy zapisane sercem. Lista zostaje tylko w tej przeglądarce — nie wysyłam jej nigdzie.</p>
        @unless ($category)
            <p x-show="! favorites" class="mb-[26px] max-w-[58ch] text-[16.5px] text-muted">Wszystko, co stoi teraz na półce w pracowni. Ceramika jest wypalona i gotowa do wysyłki — jeśli czegoś nie ma, znaczy, że już pojechało do kogoś.</p>
        @endunless

        @if ($shortcuts)
            <div x-show="! favorites" class="mb-10 flex flex-wrap gap-2.5">
                @foreach ($shortcuts as [$label, $route])
                    <a href="{{ route($route) }}" class="rounded-full border border-line bg-sand-dark px-[18px] py-2.5 text-[13px] text-ink transition-[border-color,background-color,transform] duration-300 hover:border-ink hover:text-ink active:scale-[.98]">{{ $label }}</a>
                @endforeach
            </div>
        @endif

        <div class="mb-[34px] flex flex-wrap items-center justify-between gap-[18px] border-b border-divider pb-5">
            <div class="flex flex-wrap items-center gap-2.5">
                <form method="get" action="{{ $baseUrl }}" role="search" class="flex min-w-0 flex-[0_1_240px] items-center gap-2 rounded-full border border-line bg-cream px-3.5 py-2 focus-within:border-ink">
                    <span aria-hidden="true" class="text-[13px] text-hint">⌕</span>
                    <input type="text" name="q" value="{{ $search }}" enterkeyhint="search" placeholder="Szukaj — kubek, wazon, jedwab" aria-label="Szukaj produktów" class="min-w-0 flex-1 border-0 bg-transparent py-0.5 text-[13.5px] text-ink focus:outline-none pointer-coarse:text-[16px]">
                    @if ($sort !== '')
                        <input type="hidden" name="sort" value="{{ $sort }}">
                    @endif
                    @if ($search !== '')
                        <a href="{{ $clearSearchUrl }}" aria-label="Wyczyść szukanie" class="px-0.5 text-[15px] leading-none text-hint hover:text-ink">×</a>
                    @endif
                </form>

                @foreach ($chips as $chip)
                    <a href="{{ $chip['url'] }}" @if ($chip['active']) aria-current="page" x-bind:aria-current="favorites ? 'false' : 'page'" x-bind:class="favorites && 'border-line-strong! bg-transparent! text-lead!'" @endif @class([
                        'rounded-full border px-5 py-2.5 text-[13.5px] tracking-[0.03em]',
                        'border-ink bg-ink text-linen hover:text-linen' => $chip['active'],
                        'border-line-strong text-lead hover:text-navy' => ! $chip['active'],
                    ])>{{ $chip['label'] }}</a>
                @endforeach
                <a href="#ulubione" x-cloak x-show="$store.favorites.count > 0 || favorites" x-bind:aria-current="favorites ? 'page' : 'false'"
                   x-bind:class="favorites ? 'border-ink bg-ink text-linen hover:text-linen' : 'border-line-strong text-lead hover:text-navy'"
                   class="rounded-full border px-5 py-2.5 text-[13.5px] tracking-[0.03em]">♥ Ulubione (<span x-text="savedHere"></span>)</a>
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                <span class="mr-1.5 text-[12px] tracking-[0.12em] text-hint uppercase">Sortuj</span>
                @foreach ($sorts as $option)
                    <a href="{{ $option['url'] }}" @if ($option['active']) aria-current="true" @endif @class([
                        'rounded-full px-3.5 py-2 text-[13px] hover:text-navy',
                        'bg-sand-dark text-ink' => $option['active'],
                        'text-label' => ! $option['active'],
                    ])>{{ $option['label'] }}</a>
                @endforeach
            </div>
        </div>

        @if ($products->isEmpty())
            <div class="rounded-[4px] bg-sand-dark px-9 py-11 text-center">
                <div class="mb-2 font-serif text-[26px]">Nic takiego teraz nie stoi na półce</div>
                <p class="mb-[22px] text-[15.5px] text-lead">Zmień hasło albo kategorię — albo napisz do mnie, zrobię to na zamówienie.</p>
                @if (Route::has('custom-orders.index'))
                    <a href="{{ route('custom-orders.index') }}" class="inline-block rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen hover:bg-navy hover:text-linen">Napisz, czego szukasz</a>
                @endif
            </div>
        @endif

        <div x-cloak x-show="favorites && savedHere === 0" class="rounded-[4px] bg-sand-dark px-9 py-11 text-center">
            <div class="mb-2 font-serif text-[26px]">Tu jeszcze nic nie ma</div>
            <p class="mb-[22px] text-[15.5px] text-lead">Kliknij serce przy produkcie, a zapiszę go tutaj. Rzeczy, których już nie ma na półce, tu się nie pokazują.</p>
            <a href="{{ $baseUrl }}" class="inline-block rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen hover:bg-navy hover:text-linen">Zobacz, co jest w pracowni</a>
        </div>

        <div class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-x-[26px] gap-y-[30px]">
            @foreach ($products as $product)
                <div x-show="! favorites || $store.favorites.has({{ $product->id }})" class="min-w-0">
                    <x-catalog::product-card :product="$product" :delay="min($loop->index, 11) * 0.06" :eager="$loop->index < 2" />
                </div>
            @endforeach
        </div>

        <div x-show="! favorites" class="mt-11 text-[13px] text-hint">{{ $products->count() }} z {{ $liveCount }} produktów na półce</div>
    </div>
</x-shared::layout>
