@inject('settings', 'App\Modules\Settings\Settings')
@php
    // A link shows up once its page has a route; an empty setting is not shown.
    $link = fn (string $label, string $route, mixed $parameters = []) => Route::has($route) ? [$label, route($route, $parameters)] : null;

    $shopLinks = array_filter([
        $link('Wszystkie produkty', 'shop.index'),
        $link('Kubek z napisem', 'mug.index'),
        $link('Szukam prezentu', 'gifts.index'),
        $link('Zestawy prezentowe', 'bundles.index'),
        $link('Vouchery na warsztaty', 'vouchers.index') ?? $link('Vouchery na warsztaty', 'shop.category', 'vouchery'),
        $link('Wysyłka i zwroty', 'content.faq'),
    ]);

    $studioLinks = array_filter([
        $link('Warsztaty i cennik', 'workshops.index'),
        $link('Zamówienia indywidualne', 'custom-orders.index'),
        $link('Z Twojej apaszki', 'content.scarf'),
        $link('Odcisk Twojej rośliny', 'content.imprint'),
        $link('Dla kawiarni i restauracji', 'content.b2b'),
        $link('Wypały na zlecenie', 'firing.index'),
    ]);

    $tagline = $settings->get('text_footer_tagline');
    $instagram = $settings->get('instagram_handle');
    $phone = $settings->get('contact_phone');
    $email = $settings->get('contact_email');
    $city = $settings->get('footer_city');
    $nip = $settings->get('company_nip');
    $whatsApp = $phone ? 'https://wa.me/'.preg_replace('/\D+/', '', $phone) : null;

    $social = array_filter([
        'Instagram' => $instagram ? 'https://www.instagram.com/'.ltrim($instagram, '@').'/' : null,
        'Facebook' => $settings->get('facebook_url'),
        'WhatsApp' => $whatsApp,
    ]);
@endphp
<footer class="focus-on-dark mt-auto overflow-hidden rounded-t-[36px] bg-ink text-on-dark print:hidden">
    <div class="mx-auto flex max-w-[1280px] flex-wrap gap-12 px-7 pt-16 pb-[30px]">
        <div class="min-w-0 flex-[1_1_280px]">
            <div class="mb-3.5 font-serif text-[25px] tracking-[0.16em] text-sand uppercase">mellowaura</div>
            @if ($tagline)
                <p lang="en" class="mb-5 max-w-[34ch] text-[14.5px] leading-[1.7] text-on-dark-muted">{{ $tagline }}</p>
            @endif
            @if ($social)
                <div class="flex flex-wrap gap-3">
                    @foreach ($social as $label => $href)
                        <a href="{{ $href }}" target="_blank" rel="noopener" class="rounded-full border border-line-dark px-[17px] py-[9px] text-[12.5px] text-sand hover:border-rose hover:text-rose">{{ $label }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($shopLinks)
            <div class="flex-[0_1_170px]">
                <div class="mb-4 text-[10.5px] tracking-[0.24em] text-label-dark uppercase">Sklep</div>
                <div class="flex flex-col gap-[11px] text-[14.5px]">
                    @foreach ($shopLinks as [$label, $href])
                        <a href="{{ $href }}" class="text-on-dark hover:text-rose">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($studioLinks || Route::has('admin.dashboard'))
            <div class="flex-[0_1_170px]">
                <div class="mb-4 text-[10.5px] tracking-[0.24em] text-label-dark uppercase">Pracownia</div>
                <div class="flex flex-col gap-[11px] text-[14.5px]">
                    @foreach ($studioLinks as [$label, $href])
                        <a href="{{ $href }}" class="text-on-dark hover:text-rose">{{ $label }}</a>
                    @endforeach
                    @if (Route::has('admin.dashboard'))
                        <a href="{{ route('admin.dashboard') }}" class="text-label-dark hover:text-rose">Mój panel</a>
                    @endif
                </div>
            </div>
        @endif

        <div class="flex-[0_1_200px]">
            <div class="mb-4 text-[10.5px] tracking-[0.24em] text-label-dark uppercase">Kontakt</div>
            <div class="flex flex-col gap-[11px] text-[14.5px] text-on-dark-muted">
                @if ($whatsApp)
                    <a href="{{ $whatsApp }}" target="_blank" rel="noopener" class="text-on-dark hover:text-rose">{{ $phone }}</a>
                @endif
                @if ($email)
                    <a href="mailto:{{ $email }}" class="text-on-dark [overflow-wrap:anywhere] hover:text-rose">{{ $email }}</a>
                @endif
                @if (Route::has('content.contact'))
                    <a href="{{ route('content.contact') }}" class="text-on-dark hover:text-rose">Napisz do mnie</a>
                @endif
                @if ($city)
                    <span>{{ $city }}</span>
                @endif
                <span>Pracownia na zapisy</span>
            </div>
        </div>
    </div>

    {{-- The word mark bleeding off the bottom; its letters rise with the scroll (resources/js/aura.js). --}}
    <div data-aura-giant aria-hidden="true" class="overflow-hidden pb-[.06em] text-center font-serif text-[clamp(80px,19.5vw,330px)] leading-[.8] font-light tracking-[-0.045em] whitespace-nowrap text-sand">
        @foreach (mb_str_split('Mellow') as $letter)<span class="inline-block">{{ $letter }}</span>@endforeach<em class="text-rose">@foreach (mb_str_split('Aura') as $letter)<span class="inline-block">{{ $letter }}</span>@endforeach</em>
    </div>

    <div class="mx-auto flex max-w-[1280px] flex-wrap justify-between gap-x-7 gap-y-4 border-t border-divider-dark px-7 pt-[22px] pb-10 text-[12.5px] text-label-dark">
        <span>© {{ now()->year }} MellowAura &middot; Katarzyna Samborska{{ $nip ? ' · NIP '.$nip : '' }}</span>
        <div class="flex flex-wrap gap-[22px]">
            @if (Route::has('content.faq'))
                <a href="{{ route('content.faq') }}" class="text-label-dark hover:text-rose">FAQ</a>
            @endif
            @foreach (array_filter([$link('Regulamin', 'content.terms'), $link('Polityka prywatności', 'content.privacy'), $link('Odstąp od umowy tutaj', 'withdrawal.create')]) as [$label, $href])
                <a href="{{ $href }}" class="text-label-dark hover:text-rose">{{ $label }}</a>
            @endforeach
            @if (Route::has('consent.edit'))
                {{-- With the banner on the page, the link opens it in place. --}}
                <a href="{{ route('consent.edit') }}" data-consent-open class="text-label-dark hover:text-rose">Ustawienia cookies</a>
            @endif
            <span>BLIK &middot; Przelewy24 &middot; karta</span>
        </div>
    </div>
</footer>
