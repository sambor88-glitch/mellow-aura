@use('App\Modules\Admin\Support\PanelAccess')
@use('App\Modules\Checkout\Enums\OrderStatus')
@use('App\Modules\Checkout\Enums\PaymentMethod')
@use('App\Modules\Checkout\Enums\PaymentStatus')
@php
    $card = 'rounded-[4px] border border-line bg-cream px-[26px] py-7';
    $row = 'flex flex-wrap justify-between gap-x-6 gap-y-0.5';
    $address = $order->shipping_address;
    $paid = $order->payment_status === PaymentStatus::Paid;
    // „Wysłane” fits a parcel that travels; a pickup at the studio and vouchers sent as PDFs end straight away.
    $travels = $order->sendsParcel() && $order->shipping_method !== 'studio_pickup';
    $action = 'w-full rounded-full border border-line-strong px-4 py-2.5 text-[13.5px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark active:scale-[.98]';
@endphp
<x-admin::layout title="Zamówienie {{ $order->number }}">
    <a href="{{ route('admin.orders.index') }}" class="mb-5 inline-block text-[13.5px]">← Wszystkie zamówienia</a>

    <div class="flex flex-wrap gap-[26px]">
        <div class="grid min-w-0 flex-[1_1_340px] content-start gap-[26px]">
            @foreach ($order->withdrawals as $withdrawal)
                <section @class(['rounded-[4px] border px-[26px] py-6', 'border-alert-line bg-alert text-alert-text' => $withdrawal->handled_at === null, 'border-line bg-linen text-lead' => $withdrawal->handled_at !== null])>
                    <h2 class="mb-1.5 font-serif text-[21px]">Odstąpienie od umowy — {{ $withdrawal->scope->label() }}</h2>
                    <p class="text-[14px] leading-[1.6]">
                        Oświadczenie przyszło {{ $withdrawal->submittedAtLabel() }} przez formularz na stronie.
                        @if ($withdrawal->handled_at === null)
                            Pieniądze trzeba zwrócić do {{ $withdrawal->submitted_at->copy()->addDays(14)->translatedFormat('j F') }}.
                        @else
                            Załatwione {{ $withdrawal->handled_at->format('j.m.Y') }}.
                        @endif
                    </p>
                    @if ($withdrawal->items !== null)
                        <p class="mt-2 text-[14px] whitespace-pre-line [overflow-wrap:anywhere]">Rzeczy: {{ $withdrawal->items }}</p>
                    @endif
                    @can('manage-shop')
                        <a href="{{ route('admin.withdrawals.index') }}" class="mt-2.5 inline-block text-[13.5px]">Wszystkie odstąpienia →</a>
                    @endcan
                </section>
            @endforeach

            <section class="{{ $card }}">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-serif text-[23px]">Pozycje</h2>
                    @if ($order->payment_status === PaymentStatus::Paid && $order->items->contains(fn ($item) => $item->getsCertificate()))
                        <a href="{{ route('admin.orders.certificates', $order) }}" target="_blank" rel="noopener"
                           class="inline-flex min-h-11 items-center rounded-full border border-line-strong px-5 text-[13.5px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink">Certyfikaty do druku</a>
                    @endif
                </div>
                @foreach ($order->items as $item)
                    <div class="border-b border-sand-dark py-3.5 last:border-b-0">
                        <div class="flex flex-wrap justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[15px]">{{ $item->product_name }}</div>
                                <div class="mt-0.5 text-[13px] text-label">{{ collect([$item->variant_label, $item->quantity.' szt.', $order->money($item->unit_price_gross).' za sztukę'])->filter()->join(' · ') }}</div>
                            </div>
                            <div class="font-serif text-[18px] tabular-nums">{{ $order->money($item->total()) }}</div>
                        </div>
                        @if ($item->custom_text !== null)
                            {{-- A mug from the configurator keeps one line of the text per row, as it goes on the clay. --}}
                            <div class="mt-2.5 rounded-[4px] bg-linen px-3.5 py-2.5 text-[14px]">
                                <span class="text-label">Napis do wbicia:</span> <span class="tracking-[0.08em] whitespace-pre-line [overflow-wrap:anywhere]">„{{ $item->custom_text }}”</span>
                                @if ($item->custom_glaze !== null)
                                    <div class="mt-1"><span class="text-label">Kolor wnętrza:</span> {{ $item->custom_glaze }}</div>
                                @endif
                            </div>
                        @endif
                        @if ($item->certificates->isNotEmpty())
                            <p class="mt-2 text-[13px] text-label">Certyfikat: <span class="text-ink tabular-nums">{{ $item->certificates->sortBy('piece')->pluck('number')->join(', ') }}</span></p>
                        @endif
                        @if ($paid && $item->getsCertificate())
                            {{-- The page behind the QR code (MA-234): what is filled here shows there and on the certificate. --}}
                            @php($bag = $errors->getBag('piece'.$item->id))
                            <div id="sztuka-{{ $item->id }}" class="mt-2.5 scroll-mt-6">
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px]">
                                    <a href="{{ $item->pageUrl() }}" target="_blank" rel="noopener">Strona sztuki ↗</a>
                                    <a href="{{ route('admin.orders.piece-card', [$order, $item]) }}" target="_blank" rel="noopener"
                                       class="inline-flex min-h-11 items-center rounded-full border border-line-strong px-4 text-[13px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink">Karta do paczki</a>
                                </div>
                                {{-- The photo of this very piece opens its page, before the shop's photo (MA-290). --}}
                                @php($piecePhoto = $item->getFirstMedia('photo'))
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2">
                                    @if ($piecePhoto)
                                        <div class="relative h-[90px] w-[76px] flex-none rounded-[4px] border border-line bg-line-soft">
                                            <img src="{{ $piecePhoto->getAvailableUrl(['thumb']) }}" alt="Zdjęcie sztuki: {{ $item->product_name }}" loading="lazy" width="76" height="90" class="size-full rounded-[3px] object-cover">
                                            <form method="post" action="{{ route('admin.orders.piece-photo.destroy', [$order, $item]) }}" x-data x-on:submit="confirm('Usunąć zdjęcie tej sztuki? Nie da się tego cofnąć.') || $event.preventDefault()">
                                                @csrf
                                                @method('DELETE')
                                                <button aria-label="Usuń zdjęcie sztuki: {{ $item->product_name }}" class="absolute top-[3px] right-[3px] grid size-6 place-items-center rounded-full bg-ink/75 text-[13px] leading-none text-linen transition-colors duration-300 after:absolute after:-inset-2.5 hover:bg-error">×</button>
                                            </form>
                                        </div>
                                    @endif
                                    <form method="post" action="{{ route('admin.orders.piece-photo', [$order, $item]) }}" enctype="multipart/form-data"
                                          x-data="photoPicker(@js($piecePhotoLimits))" x-on:pageshow.window="sending = false" class="contents">
                                        @csrf
                                        <label x-bind:class="sending && 'pointer-events-none opacity-60'"
                                               class="relative grid h-[90px] w-[76px] flex-none cursor-pointer place-items-center rounded-[4px] border border-dashed border-line-strong bg-linen text-center text-[12px] leading-[1.3] text-brown transition-colors duration-300 hover:border-ink hover:bg-sand-dark has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                            <span aria-hidden="true" x-text="sending ? '…' : '{{ $piecePhoto ? 'Zmień' : '+ Zdjęcie' }}'">{{ $piecePhoto ? 'Zmień' : '+ Zdjęcie' }}</span>
                                            <span class="sr-only">{{ $piecePhoto ? 'Zmień zdjęcie sztuki' : 'Dodaj zdjęcie sztuki' }}: {{ $item->product_name }}</span>
                                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" x-on:change="send($el)" class="sr-only">
                                        </label>
                                        <noscript><button class="min-h-11 rounded-full border border-line px-4 text-[12.5px] text-lead hover:border-ink">Wyślij zdjęcie</button></noscript>
                                        <p class="min-w-0 flex-[1_1_160px] text-[12px] leading-[1.5] text-hint">
                                            {{ match (true) {
                                                $piecePhoto !== null => 'To zdjęcie otwiera stronę sztuki.',
                                                $item->variant !== null => 'Zrób zdjęcie tej sztuki przed pakowaniem — otworzy jej stronę zamiast zdjęcia ze sklepu.',
                                                default => 'Zrób zdjęcie tej sztuki przed pakowaniem — bez niego jej strona nie ma zdjęcia.',
                                            } }}
                                        </p>
                                        <p role="status" x-text="sending ? 'Dodaję zdjęcie — chwilę to potrwa' : ''" class="basis-full text-[13px] text-lead empty:hidden"></p>
                                        <p role="alert" x-text="error" class="basis-full text-[13px] text-error empty:hidden"></p>
                                        @foreach ($errors->getBag('piecePhoto'.$item->id)->all() as $message)
                                            <p class="basis-full text-[13px] text-error">{{ $message }}</p>
                                        @endforeach
                                    </form>
                                </div>
                                <details @if ($bag->any()) open @endif class="mt-1.5">
                                    <summary class="cursor-pointer text-[13px] text-brown">
                                        {{ collect([$item->fired_on ? 'wypał '.$item->fired_on->format('j.m.Y') : null, $item->glaze, $item->plant, filled($item->maker_note) ? 'kilka słów od Ciebie' : null])->filter()->join(' · ') ?: 'Dopisz datę wypału, szkliwo i kilka słów' }}
                                    </summary>
                                    <form method="post" action="{{ route('admin.orders.piece', [$order, $item]) }}" class="mt-2.5 grid gap-3">
                                        @csrf
                                        @method('patch')
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <x-shared::field name="fired_on" id="fired_on-{{ $item->id }}" type="date" label="Data wypału" :bag="'piece'.$item->id"
                                                             :value="$bag->any() ? old('fired_on') : ($item->fired_on?->format('Y-m-d') ?? '')" max="{{ today()->format('Y-m-d') }}" />
                                            <x-shared::field name="glaze" id="glaze-{{ $item->id }}" label="Szkliwo" :bag="'piece'.$item->id" maxlength="120"
                                                             :value="$bag->any() ? old('glaze') : ($item->glaze ?? '')" />
                                        </div>
                                        <x-shared::field name="plant" id="plant-{{ $item->id }}" label="Roślina" :bag="'piece'.$item->id" maxlength="120"
                                                         :value="$bag->any() ? old('plant') : ($item->plant ?? '')" hint="Nieobowiązkowe — puste pola nie pokażą się klientce" />
                                        {{-- Shown only on the page of the piece, in place of the quote every piece shares (MA-292). --}}
                                        @php($noteError = $bag->first('maker_note'))
                                        <div class="min-w-0">
                                            <label for="maker_note-{{ $item->id }}" class="mb-1.5 block text-[13.5px] text-graphite">Kilka słów od Ciebie</label>
                                            <textarea id="maker_note-{{ $item->id }}" name="maker_note" rows="3" maxlength="500" aria-describedby="maker_note-{{ $item->id }}-note"
                                                      placeholder="co myślałaś, robiąc tę sztukę" @if ($noteError) aria-invalid="true" @endif
                                                      @class([
                                                          'w-full min-w-0 resize-y rounded-[14px] border bg-white px-4 py-[13px] text-[15px] leading-[1.6] text-ink transition-[border-color,box-shadow] duration-300 pointer-coarse:text-[16px] placeholder:text-hint focus:border-ink focus:shadow-[0_0_0_4px_rgb(36_65_126/.14)]',
                                                          'border-error' => $noteError,
                                                          'border-line' => ! $noteError,
                                                      ])>{{ $bag->any() ? old('maker_note') : ($item->maker_note ?? '') }}</textarea>
                                            @if ($noteError)
                                                <p id="maker_note-{{ $item->id }}-note" class="mt-1.5 text-[13px] text-error">{{ $noteError }}</p>
                                            @else
                                                <p id="maker_note-{{ $item->id }}-note" class="mt-1.5 text-[12.5px] text-hint">Na stronie sztuki zamiast wspólnego cytatu. Widzi ją każdy, kto ma kartę z kodem — bez imienia klientki.@if ($order->locale === 'en') Zamówienie po angielsku — napisz po angielsku.@endif</p>
                                            @endif
                                        </div>
                                        <button class="{{ $action }}">Zapisz na stronie sztuki i certyfikacie</button>
                                    </form>
                                </details>
                            </div>
                        @endif
                        @if ($item->accepted_deviation !== null)
                            <p class="mt-2.5 text-[13px] text-label">Klientka zaakceptowała osobnym polem: <span class="text-ink">{{ $item->accepted_deviation }}</span></p>
                        @endif
                        @if ($item->missing_quantity > 0)
                            <p class="mt-2.5 rounded-[4px] border border-alert-line bg-alert px-3.5 py-2.5 text-[13.5px] text-alert-text">
                                Brakuje {{ $item->missing_quantity }} szt. — ktoś kupił ostatnią sztukę chwilę wcześniej. Klientka dostała maila, że zwrócisz jej {{ $order->money($item->unit_price_gross * $item->missing_quantity) }} najpóźniej w ciągu 14 dni. Podobną sztukę zrób, jeśli o nią poprosi.
                            </p>
                        @endif
                    </div>
                @endforeach
            </section>

            @includeIf('gifts::admin.order-vouchers', ['order' => $order, 'card' => $card])
            @includeIf('workshops::admin.order-booking', ['order' => $order, 'card' => $card])

            <section class="{{ $card }}">
                <h2 class="mb-4 font-serif text-[23px]">Dostawa</h2>
                <dl class="grid gap-3 text-[14px]">
                    <div class="{{ $row }}"><dt class="text-label">Sposób</dt><dd>{{ $shippingLabel }}</dd></div>
                    @if ($address)
                        <div class="{{ $row }}"><dt class="text-label">Adres</dt><dd>{{ $address['street'] ?? '' }}, {{ $address['postal_code'] ?? '' }} {{ $address['city'] ?? '' }}</dd></div>
                    @endif
                    @if ($order->locker_code)
                        <div class="{{ $row }}"><dt class="text-label">Paczkomat</dt><dd>{{ $order->locker_code }}</dd></div>
                    @endif
                    @if ($order->note)
                        <div class="{{ $row }}"><dt class="text-label">Dopisek</dt><dd class="[overflow-wrap:anywhere]">{{ $order->note }}</dd></div>
                    @endif
                    @if ($order->invoice_nip)
                        <div class="{{ $row }}"><dt class="text-label">NIP do faktury</dt><dd class="tabular-nums">{{ $order->invoice_nip }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>

        <div class="grid min-w-0 flex-[0_1_300px] content-start gap-[26px]">
            <section class="{{ $card }}" aria-labelledby="status-heading">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 id="status-heading" class="font-serif text-[23px]">Status</h2>
                    <span class="rounded-full px-3 py-[5px] text-[11.5px] {{ $order->status->chipClass() }}">{{ $order->status->label() }}</span>
                </div>
                <dl class="grid gap-2 text-[13.5px]">
                    @if ($order->paid_at)
                        <div class="{{ $row }}"><dt class="text-label">Opłacone</dt><dd class="tabular-nums">{{ $order->paid_at->format('j.m.Y, H:i') }}</dd></div>
                    @endif
                    @if ($order->shipped_at)
                        <div class="{{ $row }}"><dt class="text-label">Wysłane</dt><dd class="tabular-nums">{{ $order->shipped_at->format('j.m.Y, H:i') }}</dd></div>
                    @endif
                    @if ($order->tracking_number)
                        <div class="{{ $row }}"><dt class="text-label">Nr przesyłki</dt><dd><a href="{{ $order->trackingUrl() }}" target="_blank" rel="noopener" class="tabular-nums [overflow-wrap:anywhere]">{{ $order->tracking_number }}</a></dd></div>
                    @endif
                    @if ($order->completed_at)
                        <div class="{{ $row }}"><dt class="text-label">Zakończone</dt><dd class="tabular-nums">{{ $order->completed_at->format('j.m.Y, H:i') }}</dd></div>
                    @endif
                </dl>
                @if ($order->status === OrderStatus::Problem && $order->problem_note)
                    <p class="mt-3 rounded-[4px] border border-alert-line bg-alert px-3.5 py-2.5 text-[13.5px] whitespace-pre-line [overflow-wrap:anywhere] text-alert-text">{{ $order->problem_note }}</p>
                @endif
                @error('status')
                    <p class="mt-3 text-[13px] text-error">{{ $message }}</p>
                @enderror

                @if (! $paid && $order->payment_method === PaymentMethod::BankTransfer && $order->payment_status === PaymentStatus::Pending && PanelAccess::allows(auth()->user(), 'admin.orders.paid'))
                    {{-- No gateway confirms a bank transfer: Kasia marks it once the money is on her account (MA-216). --}}
                    <div x-data="{ asking: false, sending: false }" x-on:pageshow.window="sending = false" class="mt-4 grid gap-2.5 border-t border-divider pt-4">
                        <p class="text-[13px] leading-[1.55] text-label">Przelew tradycyjny — sprawdź na koncie, czy przyszło {{ $order->money($order->amountDue()) }} z numerem {{ $order->number }} w tytule.</p>
                        <button type="button" x-show="! asking" x-on:click="asking = true" class="w-full rounded-full bg-ink p-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Wpłata doszła</button>
                        <form method="post" action="{{ route('admin.orders.paid', $order) }}" x-show="asking" x-cloak x-on:submit="sending = true" class="flex flex-wrap items-center gap-2.5">
                            @csrf
                            @method('patch')
                            <span class="text-[13px] text-ink">Na pewno? Klientka dostanie maila, a sztuki zejdą ze stanu.</span>
                            <button x-bind:disabled="sending" class="rounded-full bg-ink px-4 py-2 text-[12.5px] text-linen disabled:cursor-wait disabled:opacity-60"><span x-text="sending ? 'Zapisuję…' : 'Tak, opłacone'">Tak, opłacone</span></button>
                            <button type="button" x-show="! sending" x-on:click="asking = false" class="rounded-full px-3 py-2 text-[12.5px] text-label hover:text-ink">Anuluj</button>
                        </form>
                    </div>
                @elseif (! $paid)
                    <p class="mt-3 text-[13px] leading-[1.55] text-label">{{ $order->payment_method === PaymentMethod::BankTransfer && $order->payment_status === PaymentStatus::Pending
                        ? 'Czeka na przelew. Wpłatę zaznacza właścicielka, gdy pieniądze dojdą na konto.'
                        : 'Status zmienisz, gdy zamówienie będzie opłacone.' }}</p>
                @else
                    {{-- One tap per change: after the first submit every status button waits for the page to come back (MA-223). --}}
                    <div x-data="{ sending: false }" x-on:submit="sending = true" x-on:pageshow.window="sending = false" class="mt-4 grid gap-3 border-t border-divider pt-4">
                        @if ($travels && in_array($order->status, [OrderStatus::InProgress, OrderStatus::Problem], true))
                            <form method="post" action="{{ route('admin.orders.status', $order) }}" class="grid gap-2.5">
                                @csrf
                                @method('patch')
                                <input type="hidden" name="status" value="{{ OrderStatus::Shipped->value }}">
                                <x-shared::field name="tracking_number" label="Numer przesyłki" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                                 :hint="$order->tracking_number ? 'Puste pole zostawia numer '.$order->tracking_number : 'Nieobowiązkowe — z numerem klientka śledzi paczkę'" />
                                <button x-bind:disabled="sending" class="w-full rounded-full bg-ink p-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97] disabled:opacity-60">{{ $order->shipped_at ? 'Znowu wysłane — bez drugiego maila' : 'Wysłane — powiadom klientkę' }}</button>
                            </form>
                        @endif

                        @if (in_array($order->status, [OrderStatus::InProgress, OrderStatus::Shipped, OrderStatus::Problem], true))
                            <form method="post" action="{{ route('admin.orders.status', $order) }}">
                                @csrf
                                @method('patch')
                                <input type="hidden" name="status" value="{{ OrderStatus::Completed->value }}">
                                <button x-bind:disabled="sending" class="{{ $action }}">{{ match (true) {
                                    $order->status === OrderStatus::Shipped => 'Doręczone — zakończ',
                                    $order->shipping_method === 'studio_pickup' => 'Odebrane — zakończ',
                                    default => 'Zakończ zamówienie',
                                } }}</button>
                            </form>
                        @endif

                        @if ($order->status !== OrderStatus::Problem)
                            <details @if ($errors->has('problem_note')) open @endif class="group">
                                <summary class="cursor-pointer list-none text-[13px] text-brown [&::-webkit-details-marker]:hidden">Coś poszło nie tak? Oznacz problem</summary>
                                <form method="post" action="{{ route('admin.orders.status', $order) }}" class="mt-2.5 grid gap-2.5">
                                    @csrf
                                    @method('patch')
                                    <input type="hidden" name="status" value="{{ OrderStatus::Problem->value }}">
                                    <div class="min-w-0">
                                        <label for="problem_note" class="mb-1.5 block text-[13.5px] text-graphite">Co się stało</label>
                                        <textarea id="problem_note" name="problem_note" rows="3" maxlength="500" aria-describedby="problem_note-note"
                                                  class="w-full min-w-0 resize-y rounded-[4px] border border-line bg-white px-3.5 py-3 text-[14px] leading-[1.55] text-ink focus:border-ink pointer-coarse:text-[16px]">{{ old('problem_note') }}</textarea>
                                        @error('problem_note')
                                            <p id="problem_note-note" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                        @else
                                            <p id="problem_note-note" class="mt-1.5 text-[12.5px] text-hint">Tylko dla Ciebie, np. „paczka wróciła” albo „pęknięty kubek”</p>
                                        @enderror
                                    </div>
                                    <button x-bind:disabled="sending" class="{{ $action }}">Oznacz jako problem</button>
                                </form>
                            </details>
                        @endif

                        @if (in_array($order->status, [OrderStatus::Shipped, OrderStatus::Completed, OrderStatus::Problem], true))
                            {{-- Asked in place, like removing an account: the date and the tracking number stay either way. --}}
                            <div x-data="{ asking: false }" class="flex flex-wrap items-center gap-2.5">
                                <button type="button" x-show="! asking" x-on:click="asking = true"
                                        class="text-[13px] text-label underline decoration-line-strong underline-offset-4 hover:text-ink">Wróć do „W realizacji”</button>
                                <form method="post" action="{{ route('admin.orders.status', $order) }}" x-show="asking" x-cloak class="flex flex-wrap items-center gap-2.5">
                                    @csrf
                                    @method('patch')
                                    <input type="hidden" name="status" value="{{ OrderStatus::InProgress->value }}">
                                    <span class="text-[12.5px] text-label">Na pewno?</span>
                                    <button x-bind:disabled="sending" class="rounded-full bg-ink px-4 py-2 text-[12.5px] text-linen disabled:opacity-60">Tak, wróć</button>
                                    <button type="button" x-on:click="asking = false" class="rounded-full px-3 py-2 text-[12.5px] text-label hover:text-ink">Anuluj</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            @includeIf('shipping::admin.order-shipment', ['order' => $order, 'card' => $card, 'action' => $action])

            <section class="{{ $card }}">
                <h2 class="mb-4 font-serif text-[23px]">Kontakt</h2>
                <div class="grid gap-2 text-[14px]">
                    <div>{{ $order->name }}</div>
                    <a href="mailto:{{ $order->email }}" class="[overflow-wrap:anywhere]">{{ $order->email }}</a>
                    <a href="tel:+48{{ $order->phone }}" class="tabular-nums">{{ trim(chunk_split($order->phone, 3, ' ')) }}</a>
                    @php($vat = $order->items->first())
                    {{-- What the invoice needs, as it was when the order was placed (MA-129). --}}
                    <p class="mt-2 border-t border-divider pt-2.5 text-[12.5px] leading-[1.5] text-label">
                        Dostawa do: {{ $order->delivery_country }}@if ($vat) · VAT: {{ $vat->vat_exemption ? 'zw. — '.$vat->vat_exemption : rtrim(rtrim($vat->vat_rate, '0'), '.').'%' }}@endif
                        @if ($order->eur_rate) · kurs EUR (NBP): {{ str_replace('.', ',', $order->eur_rate) }} z {{ $order->eur_rate_on?->format('j.m.Y') }}@endif
                    </p>
                    @if ($order->terms_version)
                        <p class="mt-2 border-t border-divider pt-2.5 text-[12.5px] leading-[1.5] text-label">
                            Regulamin zaakceptowany: {{ $order->terms_version }}, {{ $order->terms_accepted_at?->format('j.m.Y, H:i') }}
                        </p>
                    @endif
                    @if (Route::has('admin.complaints.index') && Gate::allows('manage-shop'))
                        <a href="{{ route('admin.complaints.index', ['zamowienie' => $order->number]) }}#zapisz" class="mt-1 text-[13px]">Klientka zgłosiła reklamację →</a>
                    @endif
                </div>
            </section>

            <section class="{{ $card }}">
                <h2 class="mb-4 font-serif text-[23px]">Płatność</h2>
                <dl class="grid gap-2.5 text-[14px]">
                    <div class="{{ $row }}"><dt class="text-label">Produkty</dt><dd class="tabular-nums">{{ $order->money($order->total_gross - $order->shipping_gross) }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-label">Dostawa</dt><dd class="tabular-nums">{{ $order->shipping_gross === 0 ? 'gratis' : $order->money($order->shipping_gross) }}</dd></div>
                    <div class="{{ $row }} border-t border-divider pt-2.5 font-serif text-[20px]"><dt>Razem</dt><dd class="tabular-nums">{{ $order->money($order->total_gross) }}</dd></div>
                    @if ($order->vouchers_gross > 0)
                        {{-- MA-211: the vouchers' part is not a discount; VAT stays on the items as they are. --}}
                        <div class="{{ $row }}"><dt class="text-label">Voucherem</dt><dd class="tabular-nums">{{ $order->money($order->vouchers_gross) }}</dd></div>
                        <div class="{{ $row }}"><dt class="text-label">Dopłata</dt><dd class="tabular-nums">{{ $order->amountDue() > 0 ? $order->money($order->amountDue()) : 'bez dopłaty' }}</dd></div>
                    @endif
                    <div class="{{ $row }}"><dt class="text-label">Metoda</dt><dd>{{ $order->payment_method->label() }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-label">Status</dt><dd>{{ $order->payment_status->label() }}</dd></div>
                    @if ($order->paid_at)
                        <div class="{{ $row }}"><dt class="text-label">Opłacone</dt><dd class="tabular-nums">{{ $order->paid_at->format('j.m.Y, H:i') }}</dd></div>
                    @endif
                    @if ($order->payment_confirmed_by)
                        <div class="{{ $row }}"><dt class="text-label">Wpłata oznaczona przez</dt><dd class="[overflow-wrap:anywhere]">{{ $order->payment_confirmed_by }}</dd></div>
                    @endif
                    @if ($order->payment_provider_id)
                        <div class="{{ $row }}"><dt class="text-label">Nr płatności</dt><dd class="text-[12.5px] [overflow-wrap:anywhere] text-hint">{{ $order->payment_provider_id }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>
    </div>
</x-admin::layout>
