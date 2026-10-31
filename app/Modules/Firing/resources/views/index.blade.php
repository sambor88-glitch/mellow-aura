@use('App\Modules\Shared\Support\Money')
@use('App\Modules\Shared\Support\Seo')
@use('Illuminate\Support\Str')
@php
    // The topic name matches the contact form's list from the panel; if it is renamed, the form simply opens without it.
    $contactUrl = route('content.contact', ['temat' => 'Wypał moich prac']);
    // The lowest price per kilogram comes from the panel, like every number on the page.
    $fromPerKilogram = $prices->where('unit', 'kg')->min('price_gross');
    // The description follows the price list, so it never names a firing or a temperature the page doesn't have.
    $description = Seo::description('Wypał ceramiki na zlecenie w Krakowie'
        .($fromPerKilogram ? ', cennik od '.Money::format($fromPerKilogram).' za kilogram' : '')
        .($prices->isNotEmpty() ? ': '.$prices->map(fn (array $price) => Str::lcfirst(trim($price['label'])))->join(', ') : '').'.');
@endphp

<x-shared::layout title="Wypał ceramiki Kraków — cennik wypałów na zlecenie" :description="$description" :canonical="route('firing.index')">
    <x-slot:head>{!! $structuredData !!}</x-slot:head>
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">wypały na zlecenie</div>
        <h1 class="mb-5 font-serif text-[length:clamp(36px,5vw,64px)] leading-[1.05] font-light tracking-[-0.02em]">Wypalę Twoje prace</h1>
        @if ($lead)
            <p class="mb-12 max-w-[56ch] text-[17px] leading-[1.7] text-pretty text-lead">{{ $lead }}</p>
        @endif

        <div class="flex flex-wrap gap-11">
            @if ($prices->isNotEmpty() || $note)
            <div class="min-w-0 flex-[1_1_420px]">
                @if ($prices->isNotEmpty())
                    <table class="w-full overflow-hidden rounded-[20px] border border-divider bg-cream">
                        <thead>
                            <tr class="border-b border-divider bg-linen text-[11.5px] tracking-[0.16em] text-label uppercase">
                                <th scope="col" class="px-4 py-5 text-left font-normal sm:px-6">usługa</th>
                                <th scope="col" class="px-4 py-5 text-right font-normal sm:px-6">cena</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prices as $price)
                                <tr class="border-b border-sand-dark last:border-b-0">
                                    <td class="px-4 py-5 align-top hyphens-auto sm:px-6" lang="pl">
                                        <div class="text-[16px]">{{ $price['label'] }}</div>
                                        @if ($price['note'])
                                            <div class="mt-[3px] text-[13.5px] text-label">{{ $price['note'] }}</div>
                                        @endif
                                    </td>
                                    {{-- The unit goes under the price on a phone: side by side the table was wider than the screen. --}}
                                    <td class="px-4 py-5 text-right align-top text-[16px] whitespace-nowrap tabular-nums sm:px-6">
                                        {{ Money::format($price['price_gross']) }}@if ($price['unit_label'])<span class="block text-[12.5px] text-label sm:inline sm:text-[16px] sm:text-[color:inherit]"><span class="hidden sm:inline"> </span>{{ $price['unit_label'] }}</span>@endif
                                        <x-shared::price-before-reduction :was="$price['was']" :lowest="$price['lowest']" class="mt-1 ml-auto max-w-[22ch] whitespace-normal" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @if ($note)
                    <p class="mt-3.5 text-[13px] leading-[1.6] text-hint">{{ $note }}</p>
                @endif
            </div>
            @endif

            <div class="min-w-[min(100%,290px)] flex-[0_1_380px]">
                <div id="zglos-wsad" class="scroll-mt-6 rounded-[20px] border border-divider bg-cream px-7 py-[30px]">
                    {{-- Without a price list there is no form to pick a firing from, so the box asks for a message instead. --}}
                    <h2 class="mb-2.5 font-serif text-[23px]">{{ $prices->isNotEmpty() ? 'Zgłoś wsad' : 'Zapytaj o wypał' }}</h2>
                    @if ($prices->isEmpty())
                        <p class="text-[14.5px] leading-[1.6] text-muted">Cennik wypałów właśnie układam. Napisz, co chcesz wypalić i ile tego jest — odpiszę z ceną i terminem.</p>
                    @elseif ($reported)
                        <div role="status" class="mb-5 rounded-[20px] border border-line-strong bg-sand-dark p-4 text-[14px] leading-[1.6] text-graphite">
                            <p>Mam Twoje zgłoszenie{{ $reported->quote !== null ? ' — wycena '.Money::format($reported->quote) : '' }}. Potwierdzenie poszło na {{ $reported->email }}. Odpiszę z terminem i miejscem, gdzie przywieziesz prace.</p>
                        </div>
                    @elseif (session('firing_throttled'))
                        <p role="alert" class="mb-5 rounded-[20px] border border-alert-line bg-alert p-4 text-[14px] leading-[1.6] text-alert-text">
                            Mam już od Ciebie kilka zgłoszeń i na wszystkie odpiszę. Kolejne wyślesz za godzinę{{ $phone ? ', a jeśli to pilne, napisz na WhatsAppie '.$phone : '' }}.
                        </p>
                    @else
                        <p class="mb-5 text-[14.5px] leading-[1.6] text-muted">Wybierz wypał i wpisz, ile mniej więcej ważą prace. Wycenę z cennika zobaczysz od razu, a ja odpiszę z terminem.</p>
                    @endif

                    @if ($prices->isNotEmpty())
                        @php
                            $select = 'w-full min-w-0 rounded-[14px] border bg-white px-4 py-[15px] text-[15px] text-lead focus:border-ink pointer-coarse:text-[16px]';
                        @endphp
                        <form method="post" action="{{ route('firing.store') }}" novalidate data-submit-once class="grid gap-[13px]"
                              x-data="firingQuote(@js($services), @js(old('service', '')), @js(old('quantity', '')))">
                            @csrf
                            <x-shared::field name="name" id="firing-name" label="Imię" autocomplete="given-name" maxlength="120" />
                            <x-shared::field name="email" id="firing-email" label="E-mail" type="email" autocomplete="email" hint="Na ten adres wyślę wycenę" />
                            <x-shared::field name="phone" id="firing-phone" label="Telefon" type="tel" autocomplete="tel" maxlength="40" hint="Nieobowiązkowe — gdyby trzeba było szybko coś ustalić" />

                            <div class="min-w-0">
                                <label for="firing-service" class="mb-1.5 block text-[13.5px] text-graphite">Rodzaj wypału</label>
                                <select id="firing-service" name="service" x-model="service"
                                        @error('service') aria-invalid="true" aria-describedby="firing-service-error" @enderror
                                        @class([$select, 'border-error' => $errors->has('service'), 'border-line' => ! $errors->has('service')])>
                                    <option value="">Wybierz</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service['code'] }}" @selected(old('service') === $service['code'])>{{ $service['label'] }}</option>
                                    @endforeach
                                    <option value="advice" @selected(old('service') === 'advice')>Nie wiem, doradź</option>
                                </select>
                                @error('service')
                                    <p id="firing-service-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Without JavaScript the field stays visible with a label for both; the server asks for it only when the service needs it. --}}
                            <div class="min-w-0" x-show="byAmount">
                                <label for="firing-quantity" class="mb-1.5 block text-[13.5px] text-graphite" x-text="unit === 'piece' ? 'Ile prac?' : 'Ile ważą prace, w kg?'">Waga w kg albo liczba prac</label>
                                <input id="firing-quantity" name="quantity" x-model="quantity" inputmode="decimal" value="{{ old('quantity') }}" maxlength="6"
                                       :placeholder="unit === 'piece' ? '4' : '2,5'"
                                       @error('quantity') aria-invalid="true" aria-describedby="firing-quantity-error" @else aria-describedby="firing-quantity-note" @enderror
                                       @class([$select, 'text-ink placeholder:text-hint', 'border-error' => $errors->has('quantity'), 'border-line' => ! $errors->has('quantity')])>
                                @error('quantity')
                                    <p id="firing-quantity-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                @else
                                    <p id="firing-quantity-note" class="mt-1.5 text-[12.5px] text-hint" x-show="unit !== 'piece'">Wystarczy z grubsza — np. z wagi kuchennej</p>
                                @enderror
                            </div>

                            @if ($loadDays->isNotEmpty())
                                <div class="min-w-0">
                                    <label for="firing-day" class="mb-1.5 block text-[13.5px] text-graphite">Kiedy przywieziesz prace?</label>
                                    <select id="firing-day" name="drop_off_on"
                                            @error('drop_off_on') aria-invalid="true" aria-describedby="firing-day-error" @enderror
                                            @class([$select, 'border-error' => $errors->has('drop_off_on'), 'border-line' => ! $errors->has('drop_off_on')])>
                                        <option value="">Wybierz dzień</option>
                                        @foreach ($loadDays as $date => $label)
                                            <option value="{{ $date }}" @selected(old('drop_off_on') === $date)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('drop_off_on')
                                        <p id="firing-day-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif

                            <div class="min-w-0">
                                <label for="firing-message" class="mb-1.5 block text-[13.5px] text-graphite">Coś jeszcze?</label>
                                <textarea id="firing-message" name="message" rows="3" maxlength="2000" placeholder="Ile prac, jaka glina, czym szkliwione"
                                          @error('message') aria-invalid="true" aria-describedby="firing-message-error" @enderror
                                          @class([
                                              'w-full min-w-0 resize-y rounded-[20px] border bg-white px-4 py-[14px] text-[15px] leading-[1.6] text-ink placeholder:text-hint focus:border-ink pointer-coarse:text-[16px]',
                                              'border-error' => $errors->has('message'),
                                              'border-line' => ! $errors->has('message'),
                                          ])>{{ old('message') }}</textarea>
                                @error('message')
                                    <p id="firing-message-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div x-show="service" x-cloak aria-live="polite" class="flex items-baseline justify-between gap-4 rounded-[20px] border border-sand-dark bg-linen px-4 py-3.5">
                                <span class="text-[13.5px] text-label">Wycena z cennika</span>
                                <span class="text-right text-[17px] tabular-nums" x-text="quoteLabel"></span>
                            </div>

                            {{-- Hidden from people; a bot fills it in. --}}
                            <div aria-hidden="true" class="absolute -left-[9999px]">
                                <label for="firing-website">Strona internetowa</label>
                                <input id="firing-website" name="website" tabindex="-1" autocomplete="off">
                            </div>
                            <button class="rounded-full bg-ink p-4 text-[14.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Wyślij zgłoszenie</button>
                            <p class="text-[12px] leading-[1.5] text-hint">Zgłoszenie trafi prosto do mnie, a Ty dostaniesz kopię z wyceną. Więcej o danych w <a href="{{ route('content.privacy') }}">polityce prywatności</a>.</p>
                        </form>
                    @endif

                    <div class="mt-5 grid gap-3 border-t border-divider pt-5">
                        @if ($prices->isNotEmpty())
                            <p class="text-[13.5px] leading-[1.6] text-muted">Wolisz najpierw zapytać?</p>
                        @endif
                        @if ($phone)
                            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $phone) }}" target="_blank" rel="noopener"
                               class="rounded-full border border-line-strong p-[13px] text-center text-[14px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.98]">Napisz na WhatsAppie</a>
                        @endif
                        <a href="{{ $contactUrl }}"
                           class="rounded-full border border-line-strong p-[13px] text-center text-[14px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.98]">Napisz przez formularz</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-shared::layout>
