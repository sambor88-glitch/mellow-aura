@use('App\Modules\Catalog\Enums\ShopSort')
@use('App\Modules\Shared\Navigation')
@use('App\Modules\Shared\Support\Plural')
@php
    $baseUrl = $category ? route('shop.category', $category) : route('shop.index');
    $withQuery = fn (array $changes) => $baseUrl.(($query = http_build_query(array_filter([
        'q' => $search,
        'sort' => $sort === ShopSort::Recommended ? null : $sort->value,
        ...$changes,
    ]))) ? '?'.$query : '');
    $quickLinks = array_filter([
        ['mug.show', 'Kubek z Twoim napisem →'],
        ['bundles.index', 'Zestawy prezentowe →'],
        ['gifts.index', 'Szukam prezentu →'],
        ['scarf.show', 'Z Twojej apaszki →'],
        ['imprint.show', 'Odcisk Twojej rośliny →'],
        ['custom-orders.show', 'Zamówienie indywidualne →'],
    ], fn (array $link) => Navigation::url($link[0]) !== null);
    $customOrderUrl = Navigation::url('custom-orders.show') ?? Navigation::url('contact.show');
@endphp
<x-shared::layout :seo="$seo">
    <div class="ma-shop ma-view">
        <x-shared::breadcrumbs :trail="$breadcrumbs" />

        <h1 class="ma-h1 ma-h1--page">{{ $category?->name ?? 'Sklep' }}</h1>
        @if ($category)
            @if (filled($category->intro))
                <p class="ma-intro">{{ $category->intro }}</p>
            @endif
        @else
            <p class="ma-intro">Wszystko, co stoi teraz na półce w pracowni. Ceramika jest wypalona i gotowa do wysyłki — jeśli czegoś nie ma, znaczy, że już pojechało do kogoś.</p>
        @endif

        @if ($quickLinks)
            <nav class="ma-quick-links" aria-label="Na zamówienie i na prezent">
                @foreach ($quickLinks as [$route, $label])
                    <a href="{{ route($route) }}" class="ma-chip ma-chip--sand">{{ $label }}</a>
                @endforeach
            </nav>
        @endif

        <div class="ma-toolbar">
            <div class="ma-toolbar__filters">
                <form method="get" action="{{ $baseUrl }}" role="search" class="ma-search">
                    <span class="ma-search__icon" aria-hidden="true">⌕</span>
                    <input type="search" name="q" value="{{ $search }}" maxlength="80"
                           placeholder="Szukaj — kubek, wazon, jedwab" aria-label="Szukaj produktów">
                    @if ($sort !== ShopSort::Recommended)
                        <input type="hidden" name="sort" value="{{ $sort->value }}">
                    @endif
                    @if ($search !== '')
                        <a href="{{ $withQuery(['q' => null]) }}" class="ma-search__clear" aria-label="Wyczyść szukanie">×</a>
                    @endif
                </form>
                <nav aria-label="Rodzaje przedmiotów" class="ma-toolbar__categories">
                    <a href="{{ route('shop.index') }}" class="ma-chip" @if (! $category) aria-current="page" @endif>Wszystkie ({{ $totalAvailable }})</a>
                    @foreach ($categories as $item)
                        <a href="{{ route('shop.category', $item) }}" class="ma-chip" @if ($item->is($category)) aria-current="page" @endif>{{ $item->name }} ({{ $item->available_count }})</a>
                    @endforeach
                </nav>
            </div>
            <div class="ma-toolbar__sort">
                <span class="ma-label">Sortuj</span>
                @foreach (ShopSort::cases() as $option)
                    <a href="{{ $withQuery(['sort' => $option === ShopSort::Recommended ? null : $option->value]) }}"
                       class="ma-sort" rel="nofollow" @if ($option === $sort) aria-current="true" @endif>{{ $option->label() }}</a>
                @endforeach
            </div>
        </div>

        @if ($products->isEmpty())
            <div class="ma-empty">
                <h2 class="ma-empty__title">Nic takiego teraz nie stoi na półce</h2>
                <p>Zmień hasło albo kategorię — albo napisz do mnie, zrobię to na zamówienie.</p>
                @if ($customOrderUrl)
                    <a href="{{ $customOrderUrl }}" class="ma-btn ma-btn--primary">Napisz, czego szukasz</a>
                @elseif ($search !== '' || $category)
                    <a href="{{ route('shop.index') }}" class="ma-btn ma-btn--primary">Zobacz wszystkie produkty</a>
                @endif
            </div>
        @else
            <div class="ma-grid ma-grid--shop">
                @foreach ($products as $product)
                    <x-catalog::product-card :product="$product" :delay="min($loop->index, 11) * 0.06" heading="h2" />
                @endforeach
            </div>
        @endif

        <p class="ma-count">{{ $products->count() }} z {{ Plural::count($totalAvailable, 'produktu', 'produktów', 'produktów') }} na półce</p>
    </div>
</x-shared::layout>
