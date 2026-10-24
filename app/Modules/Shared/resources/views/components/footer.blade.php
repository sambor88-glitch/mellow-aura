@inject('settings', 'App\Modules\Settings\Settings')
@php
    $instagram = ltrim((string) $settings->get('contact.instagram', ''), '@');
    $facebook = $settings->get('contact.facebook_url');
    $phone = $settings->get('contact.phone');
    $whatsapp = $phone ? 'https://wa.me/'.preg_replace('/\D/', '', $phone) : null;
    $email = $settings->get('contact.email');
    $tagline = $settings->get('texts.footer_tagline');
    $city = $settings->get('location.short');
@endphp
<footer class="ma-footer focus-on-dark">
    <div class="ma-footer__inner">
        <div class="ma-footer__brand">
            <div class="ma-footer__logo">mellowaura</div>
            @if ($tagline)
                <p class="ma-footer__tagline">{{ $tagline }}</p>
            @endif
            <div class="ma-footer__socials">
                @if ($instagram !== '')
                    <a href="https://www.instagram.com/{{ $instagram }}/" target="_blank" rel="noopener" class="ma-footer__social">Instagram</a>
                @endif
                @if ($facebook)
                    <a href="{{ $facebook }}" target="_blank" rel="noopener" class="ma-footer__social">Facebook</a>
                @endif
                @if ($whatsapp)
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="ma-footer__social">WhatsApp</a>
                @endif
            </div>
        </div>

        <nav class="ma-footer__column" aria-labelledby="footer-shop">
            <h2 id="footer-shop" class="ma-footer__heading">Sklep</h2>
            <div class="ma-footer__links">
                <a href="{{ url('/sklep') }}">Wszystkie produkty</a>
                <a href="{{ url('/kubek-z-napisem') }}">Kubek z napisem</a>
                <a href="{{ url('/prezenty') }}">Szukam prezentu</a>
                <a href="{{ url('/zestawy-prezentowe') }}">Zestawy prezentowe</a>
                <a href="{{ url('/sklep') }}">Ceramika</a>
                <a href="{{ url('/sklep') }}">Rękodzieło</a>
                <a href="{{ url('/voucher-na-warsztaty-ceramiczne') }}">Vouchery na warsztaty</a>
                <a href="{{ url('/wysylka-i-pielegnacja') }}">Wysyłka i zwroty</a>
            </div>
        </nav>

        <nav class="ma-footer__column" aria-labelledby="footer-studio">
            <h2 id="footer-studio" class="ma-footer__heading">Pracownia</h2>
            <div class="ma-footer__links">
                <a href="{{ url('/warsztaty-ceramiczne-krakow') }}">Warsztaty i cennik</a>
                <a href="{{ url('/zamowienia-indywidualne') }}">Zamówienia indywidualne</a>
                <a href="{{ url('/z-twojej-apaszki') }}">Z Twojej apaszki</a>
                <a href="{{ url('/odcisk-twojej-rosliny') }}">Odcisk Twojej rośliny</a>
                <a href="{{ url('/ceramika-dla-gastronomii') }}">Dla kawiarni i restauracji</a>
                <a href="{{ url('/wypal-ceramiki-krakow') }}">Wypały na zlecenie</a>
                <a href="{{ url('/panel') }}" class="ma-footer__link--quiet" rel="nofollow">Mój panel</a>
            </div>
        </nav>

        <div class="ma-footer__column ma-footer__column--wide">
            <h2 class="ma-footer__heading">Kontakt</h2>
            <div class="ma-footer__links">
                @if ($whatsapp)
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener">{{ $phone }}</a>
                @endif
                @if ($email)
                    <a href="mailto:{{ $email }}">{{ $email }}</a>
                @endif
                <a href="{{ url('/kontakt') }}">Napisz do mnie</a>
                @if ($city)
                    <span>{{ $city }}</span>
                @endif
                <span>Pracownia na zapisy</span>
            </div>
        </div>
    </div>
    <div class="ma-footer__bottom">
        <span>© {{ now()->year }} MellowAura &middot; Katarzyna Samborska</span>
        <div class="ma-footer__legal">
            <a href="{{ url('/wysylka-i-pielegnacja') }}">FAQ</a>
            <span>Regulamin</span>
            <span>Polityka prywatności</span>
            <span>BLIK &middot; Przelewy24 &middot; karta</span>
        </div>
    </div>
</footer>
