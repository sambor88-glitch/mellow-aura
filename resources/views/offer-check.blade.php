@use('App\Modules\Shared\Support\Money')
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>MellowAura — oferta w bazie</title>
    <style>
        body { margin: 0; padding: 32px 16px; background: #F7F2EA; color: #2F2620; font: 15px/1.5 system-ui, sans-serif; }
        main { max-width: 860px; margin: 0 auto; }
        h1 { font-weight: 400; font-size: 26px; margin: 0 0 4px; }
        p { color: #6B5D4F; margin: 0 0 24px; }
        table { width: 100%; border-collapse: collapse; background: #FCF9F4; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #E2D7C7; vertical-align: top; }
        th { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #726456; font-weight: 500; }
        .draft { color: #726456; }
        .old { text-decoration: line-through; color: #726456; }
    </style>
</head>
<body>
<main>
    <h1>Oferta w bazie</h1>
    <p>Strona techniczna z tygodnia 1. Produktów: {{ $products->count() }}, opublikowanych: {{ $products->where('is_published', true)->count() }}.</p>
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
</main>
</body>
</html>
