@use('App\Modules\Shared\Support\Seo')
@php
    $title = $category
        ? ($category->seo_title ?: Seo::title($category->name, ' — rękodzieło z Krakowa'))
        : 'Sklep — ceramika i rękodzieło handmade | MellowAura';

    $description = Seo::description($category?->seo_description
        ?: 'Kubki, talerze, wazony, patery, kadzielnice oraz jedwabne scrunchies i opaski. Każda sztuka jedna, wysyłka w 3–5 dni, BLIK.');

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

    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <nav aria-label="Okruszki" class="mb-[22px] text-[12px] text-hint">
            <a href="{{ url('/') }}" class="text-hint hover:text-navy">Strona główna</a>
            / @if ($category) <a href="{{ route('shop.index') }}" class="text-hint hover:text-navy">Produkty</a> / @endif<span class="text-lead">{{ $category->name ?? 'Produkty' }}</span>
        </nav>

        <h1 class="mb-3.5 font-serif text-[length:clamp(38px,5vw,66px)] leading-[normal] font-light tracking-[-0.02em]">{{ $category->name ?? 'Sklep' }}</h1>

        @unless ($category)
            <p class="mb-[26px] max-w-[58ch] text-[16.5px] text-muted">Wszystko, co stoi teraz na półce w pracowni. Ceramika jest wypalona i gotowa do wysyłki — jeśli czegoś nie ma, znaczy, że już pojechało do kogoś.</p>
        @endunless

        @if ($shortcuts)
            <div class="mb-10 flex flex-wrap gap-2.5">
                @foreach ($shortcuts as [$label, $route])
                    <a href="{{ route($route) }}" class="rounded-full border border-line bg-sand-dark px-[18px] py-2.5 text-[13px] text-ink transition-[border-color,background-color,transform] duration-300 hover:border-ink hover:text-ink active:scale-[.98]">{{ $label }}</a>
                @endforeach
            </div>
        @endif

        <div class="mb-[34px] flex flex-wrap items-center justify-between gap-[18px] border-b border-divider pb-5">
            <div class="flex flex-wrap items-center gap-2.5">
                <form method="get" action="{{ $baseUrl }}" role="search" class="flex min-w-0 flex-[0_1_240px] items-center gap-2 rounded-full border border-line bg-cream px-3.5 py-2 focus-within:border-ink">
                    <span aria-hidden="true" class="text-[13px] text-hint">⌕</span>
                    <input type="text" name="q" value="{{ $search }}" enterkeyhint="search" placeholder="Szukaj — kubek, wazon, jedwab" aria-label="Szukaj produktów" class="min-w-0 flex-1 border-0 bg-transparent py-0.5 text-[13.5px] text-ink focus:outline-none">
                    @if ($sort !== '')
                        <input type="hidden" name="sort" value="{{ $sort }}">
                    @endif
                    @if ($search !== '')
                        <a href="{{ $clearSearchUrl }}" aria-label="Wyczyść szukanie" class="px-0.5 text-[15px] leading-none text-hint hover:text-ink">×</a>
                    @endif
                </form>

                @foreach ($chips as $chip)
                    <a href="{{ $chip['url'] }}" @if ($chip['active']) aria-current="page" @endif @class([
                        'rounded-full border px-5 py-2.5 text-[13.5px] tracking-[0.03em]',
                        'border-ink bg-ink text-linen hover:text-linen' => $chip['active'],
                        'border-line-strong text-lead hover:text-navy' => ! $chip['active'],
                    ])>{{ $chip['label'] }}</a>
                @endforeach
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

        <div class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-x-[26px] gap-y-[30px]">
            @foreach ($products as $product)
                <x-catalog::product-card :product="$product" :delay="min($loop->index, 11) * 0.06" :eager="$loop->index < 4" />
            @endforeach
        </div>

        <div class="mt-11 text-[13px] text-hint">{{ $products->count() }} z {{ $liveCount }} produktów na półce</div>
    </div>
</x-shared::layout>
