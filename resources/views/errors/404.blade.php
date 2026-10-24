@use('App\Modules\Shared\Seo\Seo')
<x-shared::layout :seo="(new Seo('Nie ma takiej strony | MellowAura'))->noindex()">
    <div class="ma-error ma-view">
        <x-shared::eyebrow>błąd 404</x-shared::eyebrow>
        <h1 class="ma-h1 ma-h1--page">Tu nic nie stoi</h1>
        <p class="ma-lead">Ten adres prowadzi donikąd — może rzecz już pojechała do kogoś, a może w linku jest literówka.</p>
        <a href="{{ route('shop.index') }}" class="ma-btn ma-btn--primary">Zobacz, co jest na półce</a>
    </div>
</x-shared::layout>
