{{-- Ustawienia (MA-321): one part at a time in tabs; every form keeps its #id, so saving and links to a field open its tab. --}}
@use('App\Modules\Shared\Support\Money')
@php
    // Three forms on one page: each card saves on its own and keeps its own mistakes.
    $shippingErrors = $errors->getBag('dostawa');

    $moneyRows = [
        ['free_shipping_threshold', 'free_shipping_threshold', 'Darmowa wysyłka od', old('free_shipping_threshold', $freeFrom === null ? null : Money::input((int) $freeFrom)), 'zł'],
        ...$shippingMethods->flatMap(fn (array $method) => [
            [
                'shipping['.$method['code'].']',
                'shipping.'.$method['code'],
                $method['label'] ?? $method['code'],
                old('shipping.'.$method['code'], Money::input((int) ($method['price_gross'] ?? 0))),
                'zł',
            ],
            // The same delivery on the English checkout; an empty field leaves it out there.
            [
                'shipping_eur['.$method['code'].']',
                'shipping_eur.'.$method['code'],
                ($method['label'] ?? $method['code']).' — w angielskiej kasie',
                old('shipping_eur.'.$method['code'], is_numeric($method['price_eur'] ?? null) ? Money::input((int) $method['price_eur']) : null),
                '€',
            ],
        ])->all(),
        ['gift_wrap_price', 'gift_wrap_price', 'Pakowanie na prezent', old('gift_wrap_price', $giftWrapPrice === null ? null : Money::input((int) $giftWrapPrice)), 'zł'],
    ];

    $card = 'scroll-mt-24 rounded-[4px] border border-line bg-cream px-[18px] py-5 md:p-[22px]';
    $heading = 'mb-3 text-[11.5px] tracking-[0.16em] text-label uppercase';
    $alert = 'mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text';
    $hint = 'mt-3.5 text-[12.5px] leading-[1.6] text-label';
    $button = 'mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]';
@endphp
<x-admin::layout title="Ustawienia" lead="Dostawa, dane pracowni i firmy, karta produktu, statystyki i prośba o opinię. Zmiany widać na stronie od razu.">
  <x-admin::tabs :tabs="['dostawa' => 'Dostawa i opłaty', 'pracownia' => 'Pracownia', 'firma' => 'Firma', 'karta-produktu' => 'Karta produktu', 'statystyki' => 'Statystyki', 'opinie' => 'Opinie']" label="Części ustawień">
    <x-admin::tab-panel name="dostawa" class="max-w-[880px]">
        <form id="dostawa" method="post" action="{{ route('admin.settings.shipping') }}" novalidate class="{{ $card }}">
            @csrf
            @method('PUT')
            <h2 class="{{ $heading }}">Dostawa i opłaty</h2>
            @if ($shippingErrors->any())
                <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
            @endif
            <div class="grid gap-3">
                @foreach ($moneyRows as [$name, $key, $label, $value, $unit])
                    @php($id = 'dostawa-'.str_replace(['.', '_'], '-', $key))
                    <div>
                        <div class="flex items-center justify-between gap-3.5">
                            <label for="{{ $id }}" class="min-w-0 flex-1 text-[14px] text-graphite">{{ $label }}</label>
                            <span class="flex items-center gap-1.5">
                                <input id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" inputmode="decimal"
                                       @if ($shippingErrors->has($key)) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                                       @class([
                                           'min-h-11 w-[84px] min-w-0 rounded-[4px] border bg-white px-2.5 text-right text-[14px] text-ink focus:border-ink',
                                           'border-error' => $shippingErrors->has($key),
                                           'border-line' => ! $shippingErrors->has($key),
                                       ])>
                                <span aria-hidden="true" class="w-3 text-[13px] text-label">{{ $unit }}</span>
                            </span>
                        </div>
                        @if ($shippingErrors->has($key))
                            <p id="{{ $id }}-error" class="mt-1.5 text-[13px] text-error">{{ $shippingErrors->first($key) }}</p>
                        @endif
                    </div>
                @endforeach
                <fieldset class="min-w-0 border-t border-sand-dark pt-3">
                    <legend class="float-left mb-2 w-full text-[14px] text-graphite">Wysyłka rzeczy z półki</legend>
                    <div class="clear-both flex flex-wrap items-center gap-2 text-[13.5px] text-label">
                        @foreach (['dispatch_days_min' => 'od', 'dispatch_days_max' => 'do'] as $key => $word)
                            <label for="dostawa-{{ str_replace('_', '-', $key) }}">{{ $word }}</label>
                            <input id="dostawa-{{ str_replace('_', '-', $key) }}" name="{{ $key }}" value="{{ old($key, $dispatchDays[$key]) }}" inputmode="numeric"
                                   @if ($shippingErrors->has($key)) aria-invalid="true" aria-describedby="dostawa-dni-error" @endif
                                   @class([
                                       'min-h-11 w-[60px] min-w-0 rounded-[4px] border bg-white px-2.5 text-right text-[14px] text-ink focus:border-ink',
                                       'border-error' => $shippingErrors->has($key),
                                       'border-line' => ! $shippingErrors->has($key),
                                   ])>
                        @endforeach
                        <span>dni roboczych</span>
                    </div>
                    @if ($shippingErrors->has('dispatch_days_min') || $shippingErrors->has('dispatch_days_max'))
                        <p id="dostawa-dni-error" class="mt-1.5 text-[13px] text-error">{{ $shippingErrors->first('dispatch_days_min') ?: $shippingErrors->first('dispatch_days_max') }}</p>
                    @endif
                </fieldset>
            </div>
            <p class="{{ $hint }}">Próg darmowej wysyłki od razu zmienia pasek w koszyku i dopiski „gratis od” na stronie. Puste pole wyłącza darmową wysyłkę, a puste pakowanie znika ze strony zestawów. Czas wysyłki widać na karcie produktu, na stronie głównej, po zamówieniu i w danych dla Google.</p>
            <p class="{{ $hint }}">Ceny „w angielskiej kasie” płaci klient z angielskiej wersji sklepu, w euro. Dostawa bez ceny w euro nie pokazuje się tam wcale. Darmowa wysyłka działa tylko w złotych.</p>
            <button class="{{ $button }}">Zapisz dostawę</button>
        </form>
    </x-admin::tab-panel>
    <x-admin::tab-panel name="pracownia" class="max-w-[880px]">
        <form id="pracownia" method="post" action="{{ route('admin.settings.studio') }}" novalidate class="{{ $card }}">
            @csrf
            @method('PUT')
            <h2 class="{{ $heading }}">Dane pracowni</h2>
            @if ($errors->hasBag('pracownia'))
                <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
            @endif
            <div class="grid gap-3">
                <x-shared::field name="contact_phone" id="pracownia-telefon" type="tel" bag="pracownia" label="Telefon (WhatsApp)"
                                 :value="old('contact_phone', $studio['contact_phone'])" placeholder="np. 600 100 200" />
                <x-shared::field name="contact_email" id="pracownia-email" type="email" bag="pracownia" label="E-mail — puste pole nie pokaże się na stronie"
                                 :value="old('contact_email', $studio['contact_email'])" />
                <x-shared::field name="instagram_handle" id="pracownia-instagram" bag="pracownia" label="Instagram — nazwa profilu"
                                 :value="old('instagram_handle', $studio['instagram_handle'])" placeholder="np. mellowaura" />
                <x-shared::field name="facebook_url" id="pracownia-facebook" type="url" bag="pracownia" label="Facebook — adres strony"
                                 :value="old('facebook_url', $studio['facebook_url'])" placeholder="https://www.facebook.com/…" />
                <x-shared::field name="google_business_profile_url" id="pracownia-google" type="url" bag="pracownia" label="Wizytówka Google — link, gdy już będzie"
                                 :value="old('google_business_profile_url', $studio['google_business_profile_url'])" />
                <x-shared::field name="location_description" id="pracownia-okolica" bag="pracownia" label="Pracownia — okolica na stronie, bez adresu"
                                 :value="old('location_description', $studio['location_description'])" placeholder="np. Kraków, okolice Błoń Krakowskich" />
                <x-shared::field name="studio_address" id="pracownia-adres" bag="pracownia" label="Dokładny adres — tylko do maila po zapisie"
                                 :value="old('studio_address', $studio['studio_address'])" />
                <x-shared::field name="parcel_locker_code" id="pracownia-paczkomat" bag="pracownia" label="Twój Paczkomat — kod dla przesyłek od klientów"
                                 :value="old('parcel_locker_code', $studio['parcel_locker_code'])" placeholder="np. KRA01M" />
                <x-shared::field name="footer_city" id="pracownia-miasto" bag="pracownia" label="Miasto w stopce"
                                 :value="old('footer_city', $studio['footer_city'])" placeholder="np. Kraków, Polska" />
            </div>
            <p class="{{ $hint }}">Telefon, e-mail i profile podmieniają się w stopce, na stronie kontaktu, w formularzach i w danych dla Google. Dokładny adres nie pokazuje się nigdzie na stronie — trafia tylko do maila po zapisie na warsztat.</p>
            <button class="{{ $button }}">Zapisz dane pracowni</button>
        </form>
    </x-admin::tab-panel>
    <x-admin::tab-panel name="firma" class="max-w-[880px]">
    <form id="firma" method="post" action="{{ route('admin.settings.company') }}" novalidate class="{{ $card }}">
        @csrf
        @method('PUT')
        <h2 class="{{ $heading }}">Dane firmy — do regulaminu i dla operatora płatności</h2>
        @if ($errors->hasBag('firma'))
            <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif
        <p class="mb-3.5 text-[13.5px] leading-[1.6] text-label">Wpisz je tak, jak są w CEIDG. Regulamin, polityka prywatności, strona kontaktu i stopka biorą je stąd. Operator płatności sprawdza, czy NIP na stronie zgadza się z wnioskiem.</p>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,260px),1fr))] gap-3">
            <x-shared::field name="company_name" id="firma-nazwa" bag="firma" label="Firma z CEIDG"
                             :value="old('company_name', $company['company_name'])" placeholder="np. MellowAura Katarzyna Samborska" />
            <x-shared::field name="company_nip" id="firma-nip" bag="firma" label="NIP" inputmode="numeric"
                             :value="old('company_nip', $company['company_nip'])" />
            <x-shared::field name="company_regon" id="firma-regon" bag="firma" label="REGON" inputmode="numeric"
                             :value="old('company_regon', $company['company_regon'])" />
            <x-shared::field name="company_bank_account" id="firma-rachunek" bag="firma" label="Numer rachunku do przelewów" inputmode="numeric"
                             :value="old('company_bank_account', $company['company_bank_account'])" placeholder="26 cyfr" />
            <x-shared::field name="company_address" id="firma-adres" bag="firma" label="Adres firmy i do doręczeń"
                             :value="old('company_address', $company['company_address'])" hint="Ten z CEIDG, np. wirtualnego biura — pokaże się publicznie" />
            <x-shared::field name="return_address" id="firma-zwroty" bag="firma" label="Adres do zwrotów albo Paczkomat"
                             :value="old('return_address', $company['return_address'])" hint="Tu klienci odsyłają rzeczy po odstąpieniu od umowy" />
            <x-shared::field name="payment_operator" id="firma-operator" bag="firma" label="Operator płatności — pełna nazwa"
                             :value="old('payment_operator', $company['payment_operator'])" placeholder="np. PayPro S.A. (Przelewy24)" />
            <x-shared::field name="company_vat_note" id="firma-vat" bag="firma" label="Informacja o VAT"
                             :value="old('company_vat_note', $company['company_vat_note'])" hint="Zdanie od księgowej, np. o zwolnieniu z VAT" />
        </div>
        <p class="{{ $hint }}">Nie wpisuj tu adresu domowej pracowni — pokazujemy tylko adres z CEIDG. Puste pole zostaje w regulaminie jako miejsce do uzupełnienia.</p>
        <button class="{{ $button }}">Zapisz dane firmy</button>
    </form>
    </x-admin::tab-panel>
    <x-admin::tab-panel name="karta-produktu" class="max-w-[880px]">
    <form id="karta-produktu" method="post" action="{{ route('admin.settings.product-card') }}" novalidate class="{{ $card }}">
        @csrf
        @method('PUT')
        <h2 class="{{ $heading }}">Karta produktu — wspólne dla wszystkich</h2>
        @if ($errors->hasBag('karta-produktu'))
            <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif
        <p class="mb-3.5 max-w-[70ch] text-[13.5px] leading-[1.6] text-label">Pokażą się na karcie każdego produktu, który nie ma własnych. Własne wpisujesz w Produktach.</p>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,260px),1fr))] gap-3">
            <x-shared::field name="size_tolerance" id="karta-produktu-roznica" bag="karta-produktu" label="Dopuszczalna różnica wymiarów"
                             :value="old('size_tolerance', $productCard['size_tolerance'])" placeholder="np. 0,5 cm"
                             hint="Przy wymiarach: „mogą różnić się do 0,5 cm”. Tę samą wartość podaje regulamin w §4." />
            <x-shared::field name="care_rule_ceramics" id="karta-produktu-pielegnacja" bag="karta-produktu" label="Pielęgnacja ceramiki"
                             :value="old('care_rule_ceramics', $productCard['care_rule_ceramics'])" placeholder="np. Zmywarka tak, złoto tylko ręcznie"
                             hint="Dla ceramiki bez własnego zdania o pielęgnacji" />
        </div>
        <button class="{{ $button }}">Zapisz kartę produktu</button>
    </form>
    </x-admin::tab-panel>
    <x-admin::tab-panel name="statystyki" class="max-w-[880px]">
    <form id="statystyki" method="post" action="{{ route('admin.settings.analytics') }}" novalidate class="{{ $card }}">
        @csrf
        @method('PUT')
        <h2 class="{{ $heading }}">Statystyki — Google Analytics</h2>
        <p class="mb-3.5 max-w-[70ch] text-[13.5px] leading-[1.6] text-label">
            Po wpisaniu identyfikatora strona pokaże okienko zgód na pliki cookies. Analytics ruszy tylko u osób, które się zgodzą — bez zgody żaden skrypt Google się nie wczyta.
        </p>
        <div class="max-w-[420px]">
            <x-shared::field name="google_analytics_id" id="statystyki-id" bag="statystyki" label="Identyfikator pomiaru GA4"
                             :value="old('google_analytics_id', $analyticsId)" placeholder="np. G-AB12CD34EF" autocapitalize="characters"
                             hint="Google Analytics → Administracja → Strumienie danych. Puste pole wyłącza statystyki i okienko." />
        </div>
        <button class="{{ $button }}">Zapisz statystyki</button>
    </form>
    </x-admin::tab-panel>
    <x-admin::tab-panel name="opinie" class="max-w-[880px]">
    <form id="opinie" method="post" action="{{ route('admin.settings.reviews') }}" novalidate class="{{ $card }}">
        @csrf
        @method('PUT')
        <h2 class="{{ $heading }}">Prośba o opinię w Google</h2>
        @if ($errors->hasBag('opinie'))
            <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif
        <p class="mb-3.5 max-w-[70ch] text-[13.5px] leading-[1.6] text-label">
            Kilka dni po zakończeniu zamówienia klient dostaje jednego maila z prośbą o opinię na wizytówce Google. Nie dostaje go, gdy przy zamówieniu był problem, odstąpienie od umowy albo reklamacja. Treść maila zmienisz w „Maile”.
        </p>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,260px),1fr))] gap-3">
            <x-shared::field name="review_request_url" id="opinie-link" type="url" bag="opinie" label="Link do wystawienia opinii"
                             :value="old('review_request_url', $reviewRequest['review_request_url'])" placeholder="https://g.page/r/…/review"
                             hint="Wizytówka Google → „Poproś o opinie” → skopiuj link. Puste pole wyłącza maila." />
            <x-shared::field name="review_request_days" id="opinie-dni" bag="opinie" label="Ile dni po zakończeniu zamówienia" inputmode="numeric"
                             :value="old('review_request_days', $reviewRequest['review_request_days'])" placeholder="np. 7"
                             hint="Liczysz od statusu „Zakończone”, czyli zwykle od doręczenia." />
        </div>
        <p class="{{ $hint }}">Mail tylko prosi o opinię — bez rabatu i bez nagrody. Google i UOKiK zakazują nagradzania za opinie.</p>
        <button class="{{ $button }}">Zapisz prośbę o opinię</button>
    </form>
    </x-admin::tab-panel>
  </x-admin::tabs>
</x-admin::layout>
