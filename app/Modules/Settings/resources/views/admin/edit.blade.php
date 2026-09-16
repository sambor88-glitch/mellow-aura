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
<x-admin::layout title="Ustawienia" lead="Dostawa, dane pracowni i kafelki o materiale. Zmiany widać na stronie od razu.">
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
            </div>
            <p class="{{ $hint }}">Próg darmowej wysyłki od razu zmienia pasek w koszyku i dopiski „gratis od” na stronie. Puste pole wyłącza darmową wysyłkę, a puste pakowanie znika ze strony zestawów.</p>
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
