@use('App\Modules\Shared\Support\Money')
@php
    // Three forms on one page: each card saves on its own and keeps its own mistakes.
    $shippingErrors = $errors->getBag('dostawa');
    $tileErrors = $errors->getBag('materialy');

    $moneyRows = [
        ['free_shipping_threshold', 'free_shipping_threshold', 'Darmowa wysyłka od', old('free_shipping_threshold', $freeFrom === null ? null : Money::input((int) $freeFrom))],
        ...$shippingMethods->map(fn (array $method) => [
            'shipping['.$method['code'].']',
            'shipping.'.$method['code'],
            $method['label'] ?? $method['code'],
            old('shipping.'.$method['code'], Money::input((int) ($method['price_gross'] ?? 0))),
        ])->all(),
        ['gift_wrap_price', 'gift_wrap_price', 'Pakowanie na prezent', old('gift_wrap_price', $giftWrapPrice === null ? null : Money::input((int) $giftWrapPrice))],
    ];

    $savedTiles = array_values((array) old('tiles', $tiles));
    // Two empty rows for new tiles; left empty, they are not saved.
    $tileRows = [...$savedTiles, ['title' => '', 'text' => ''], ['title' => '', 'text' => '']];

    $card = 'scroll-mt-6 rounded-[4px] border border-line bg-cream p-[22px]';
    $heading = 'mb-3 text-[11.5px] tracking-[0.16em] text-label uppercase';
    $alert = 'mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text';
    $hint = 'mt-3.5 text-[12.5px] leading-[1.6] text-label';
    $button = 'mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]';
    $tileInput = 'w-full min-w-0 rounded-[4px] border bg-white px-3 py-2.5 text-[14px] text-ink placeholder:text-hint focus:border-ink';
@endphp
<x-admin::layout title="Ustawienia" lead="Dostawa, dane pracowni i firmy, statystyki i kafelki o materiale. Zmiany widać na stronie od razu.">
    <div class="grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] items-start gap-[22px]">
        <form id="dostawa" method="post" action="{{ route('admin.settings.shipping') }}" novalidate class="{{ $card }}">
            @csrf
            @method('PUT')
            <h2 class="{{ $heading }}">Dostawa i opłaty</h2>
            @if ($shippingErrors->any())
                <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
            @endif
            <div class="grid gap-3">
                @foreach ($moneyRows as [$name, $key, $label, $value])
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
                                <span aria-hidden="true" class="text-[13px] text-label">zł</span>
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
            <button class="{{ $button }}">Zapisz dostawę</button>
        </form>

        <form id="pracownia" method="post" action="{{ route('admin.settings.studio') }}" novalidate class="{{ $card }}">
            @csrf
            @method('PUT')
            <h2 class="{{ $heading }}">Dane pracowni</h2>
            @if ($errors->hasBag('pracownia'))
                <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
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
    </div>

    <form id="firma" method="post" action="{{ route('admin.settings.company') }}" novalidate class="{{ $card }} mt-[22px]">
        @csrf
        @method('PUT')
        <h2 class="{{ $heading }}">Dane firmy — do regulaminu i dla operatora płatności</h2>
        @if ($errors->hasBag('firma'))
            <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif
        <p class="mb-3.5 text-[13.5px] leading-[1.6] text-label">Wpisz je tak, jak są w CEIDG. Regulamin, polityka prywatności, strona kontaktu i stopka biorą je stąd. Operator płatności sprawdza, czy NIP na stronie zgadza się z wnioskiem.</p>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-3">
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
                             :value="old('return_address', $company['return_address'])" hint="Tu klientki odsyłają rzeczy po odstąpieniu od umowy" />
            <x-shared::field name="payment_operator" id="firma-operator" bag="firma" label="Operator płatności — pełna nazwa"
                             :value="old('payment_operator', $company['payment_operator'])" placeholder="np. PayPro S.A. (Przelewy24)" />
            <x-shared::field name="company_vat_note" id="firma-vat" bag="firma" label="Informacja o VAT"
                             :value="old('company_vat_note', $company['company_vat_note'])" hint="Zdanie od księgowej, np. o zwolnieniu z VAT" />
        </div>
        <p class="{{ $hint }}">Nie wpisuj tu adresu domowej pracowni — pokazujemy tylko adres z CEIDG. Puste pole zostaje w regulaminie jako miejsce do uzupełnienia.</p>
        <button class="{{ $button }}">Zapisz dane firmy</button>
    </form>

    <form id="statystyki" method="post" action="{{ route('admin.settings.analytics') }}" novalidate class="{{ $card }} mt-[22px]">
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

    <form id="materialy" method="post" action="{{ route('admin.settings.materials') }}" novalidate class="{{ $card }} mt-[22px]">
        @csrf
        @method('PUT')
        <h2 class="{{ $heading }}">Materiały — kafelki na „O mnie”</h2>
        @if ($tileErrors->any())
            <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif
        <div class="grid gap-3.5 sm:gap-2.5">
            @foreach ($tileRows as $index => $tile)
                @php($saved = $index < count($savedTiles))
                <div>
                    {{-- The „usuń” column keeps one width, so saved and empty rows line up; on a phone the name sits above the description. --}}
                    <div class="grid grid-cols-[minmax(0,1fr)_4.25rem] items-start gap-2">
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                            <input name="tiles[{{ $index }}][title]" value="{{ $tile['title'] ?? '' }}" maxlength="40"
                                   aria-label="Kafelek {{ $index + 1 }} — nazwa" placeholder="{{ $saved ? 'Nazwa' : 'Nowy kafelek' }}"
                                   @class([$tileInput, 'border-error' => $tileErrors->has('tiles.'.$index.'.title'), 'border-line' => ! $tileErrors->has('tiles.'.$index.'.title')])>
                            <textarea name="tiles[{{ $index }}][text]" rows="2" maxlength="300"
                                      aria-label="Kafelek {{ $index + 1 }} — opis" placeholder="Jedno, dwa zdania"
                                      @class([$tileInput, 'resize-y leading-[1.55]', 'border-error' => $tileErrors->has('tiles.'.$index.'.text'), 'border-line' => ! $tileErrors->has('tiles.'.$index.'.text')])>{{ $tile['text'] ?? '' }}</textarea>
                        </div>
                        @if ($saved)
                            <label class="flex min-h-11 items-center gap-1.5 text-[12.5px] text-label">
                                <input type="checkbox" name="tiles[{{ $index }}][remove]" value="1" class="size-4 accent-error"> usuń
                            </label>
                        @endif
                    </div>
                    @foreach (['title', 'text'] as $field)
                        @if ($tileErrors->has('tiles.'.$index.'.'.$field))
                            <p class="mt-1.5 text-[13px] text-error">{{ $tileErrors->first('tiles.'.$index.'.'.$field) }}</p>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
        @if ($tileErrors->has('tiles'))
            <p class="mt-1.5 text-[13px] text-error">{{ $tileErrors->first('tiles') }}</p>
        @endif
        <p class="{{ $hint }}">Kafelek bez opisu nie pokaże się na stronie. W puste wiersze na dole wpisz nowe kafelki.</p>
        <button class="{{ $button }}">Zapisz kafelki</button>
    </form>
</x-admin::layout>
