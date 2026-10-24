@use('App\Modules\Shared\Navigation')
@php
    $instagram = $settings->get('contact.instagram');
    $facebook = $settings->get('contact.facebook_url');
    $phone = $settings->get('contact.phone');
    $whatsapp = $phone ? 'https://wa.me/'.preg_replace('/\D/', '', $phone) : null;
    $email = $settings->get('contact.email');
    $shopLinks = Navigation::footerShop();
    $studioLinks = Navigation::footerStudio();
    $contactUrl = Navigation::url('contact.show');
    $faqUrl = Navigation::url('faq.show');
@endphp
<footer class="ma-footer focus-on-dark">
    <div class="ma-footer__top">
        <div class="ma-footer__brand">
            <div class="ma-footer__logo">mellowaura</div>
            @if ($tagline = $settings->get('texts.footer_tagline'))
                <p class="ma-footer__tagline">{{ $tagline }}</p>
            @endif
            <div class="ma-footer__socials">
                @if ($instagram)
                    <a class="ma-footer__social" href="https://www.instagram.com/{{ $instagram }}/" target="_blank" rel="noopener">Instagram</a>
                @endif
                @if ($facebook)
                    <a class="ma-footer__social" href="{{ $facebook }}" target="_blank" rel="noopener">Facebook</a>
                @endif
                @if ($whatsapp)
                    <a class="ma-footer__social" href="{{ $whatsapp }}" target="_blank" rel="noopener">WhatsApp</a>
                @endif
            </div>
        </div>
        @if ($shopLinks)
            <nav class="ma-footer__col" aria-labelledby="stopka-sklep">
                <h2 id="stopka-sklep" class="ma-footer__heading">Sklep</h2>
                <ul class="ma-footer__links">
                    @foreach ($shopLinks as $link)
                        <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        @if ($studioLinks)
            <nav class="ma-footer__col" aria-labelledby="stopka-pracownia">
                <h2 id="stopka-pracownia" class="ma-footer__heading">Pracownia</h2>
                <ul class="ma-footer__links">
                    @foreach ($studioLinks as $link)
                        <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        <div class="ma-footer__col ma-footer__col--wide">
            <h2 class="ma-footer__heading">Kontakt</h2>
            <ul class="ma-footer__links">
                @if ($whatsapp)
                    <li><a href="{{ $whatsapp }}" target="_blank" rel="noopener">{{ $phone }}</a></li>
                @endif
                @if ($email)
                    <li><a href="mailto:{{ $email }}">{{ $email }}</a></li>
                @endif
                @if ($contactUrl)
                    <li><a href="{{ $contactUrl }}">Napisz do mnie</a></li>
                @endif
                @if ($city = $settings->get('location.short'))
                    <li>{{ $city }}</li>
                @endif
                <li>Pracownia na zapisy</li>
            </ul>
        </div>
    </div>
    <div class="ma-footer__bottom">
        <span>© {{ now()->year }} MellowAura &middot; Katarzyna Samborska</span>
        <div class="ma-footer__bottom-links">
            @if ($faqUrl)
                <a href="{{ $faqUrl }}">FAQ</a>
            @endif
            <span>BLIK &middot; Przelewy24 &middot; karta</span>
        </div>
    </div>
</footer>
