@use('App\Modules\Shared\Support\Money')
@use('App\Modules\Shared\Seo\Seo')
{{-- Temporary week-1 check page inside the real layout; replaced by the home page from the prototype. --}}
<x-shared::layout :seo="new Seo(
    title: 'MellowAura — ceramika i rękodzieło z jedwabiu, Kraków',
    description: 'Ręcznie formowana ceramika i jedwabne dodatki z drugiego obiegu. Kubki z wbijanym napisem, warsztaty w kameralnej pracowni w Krakowie. Płatność BLIK.',
    path: '/',
    robots: 'noindex, nofollow',
)">
    <x-slot:head>
        <style>
            .offer-check { max-width: 1280px; margin: 0 auto; padding: 44px 28px 96px; }
            .offer-check h1 { font-family: var(--font-serif); font-weight: 300; font-size: clamp(32px, 4.4vw, 52px); line-height: 1.04; margin: 0 0 8px; }
            .offer-check p { color: var(--color-muted); margin: 0 0 24px; }
            .offer-check__scroll { overflow-x: auto; }
            .offer-check table { width: 100%; border-collapse: collapse; background: var(--color-cream); border: 1px solid var(--color-line-card); border-radius: 4px; font-size: 14px; }
            .offer-check th, .offer-check td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--color-line-section); vertical-align: top; }
            .offer-check th { font-size: 11.5px; letter-spacing: .14em; text-transform: uppercase; color: var(--color-label); font-weight: 500; }
            .offer-check .draft { color: var(--color-label); }
            .offer-check .old { text-decoration: line-through; color: var(--color-label); }
        </style>
    </x-slot:head>

    <section class="offer-check">
        <h1>Oferta w bazie</h1>
        <p>Strona techniczna z tygodnia 1. Produktów: {{ $products->count() }}, opublikowanych: {{ $products->where('is_published', true)->count() }}.</p>
        <div class="offer-check__scroll">
            <table>
                <thead>
                    <tr><th>Produkt</th><th>Kategoria</th><th>Warianty</th><th>Stan</th></tr>
                </thead>
                <tbody>
                @foreach ($products as $product)
                    <tr @class(['draft' => ! $product->is_published])>
                        <td>{{ $product->name }}@unless ($product->is_published) (szkic)@endunless</td>
                        <td>{{ $product->category?->name }}</td>
                        <td>
                            @foreach ($product->variants as $variant)
                                {{ $variant->label }} — {{ Money::format($variant->price_gross) }}
                                @if ($variant->isDiscounted())<span class="old">{{ Money::format($variant->compare_at_price) }}</span>@endif
                                <br>
                            @endforeach
                        </td>
                        <td>{{ $product->variants->sum('stock') }} szt.</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-shared::layout>
