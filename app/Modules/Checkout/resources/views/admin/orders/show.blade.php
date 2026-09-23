@use('App\Modules\Checkout\Enums\OrderStatus')
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

                @if (! $paid)
                    <p class="mt-3 text-[13px] leading-[1.55] text-label">Status zmienisz, gdy zamówienie będzie opłacone.</p>
                @else
                    <div class="mt-4 grid gap-3 border-t border-divider pt-4">
                        @if ($travels && in_array($order->status, [OrderStatus::InProgress, OrderStatus::Problem], true))
                            <form method="post" action="{{ route('admin.orders.status', $order) }}" class="grid gap-2.5">
                                @csrf
                                @method('patch')
                                <input type="hidden" name="status" value="{{ OrderStatus::Shipped->value }}">
                                <x-shared::field name="tracking_number" label="Numer przesyłki" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                                 hint="Nieobowiązkowe — z numerem klientka śledzi paczkę" />
                                <button class="w-full rounded-full bg-ink p-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Wysłane — powiadom klientkę</button>
                            </form>
                        @endif

                        @if (in_array($order->status, [OrderStatus::InProgress, OrderStatus::Shipped, OrderStatus::Problem], true))
                            <form method="post" action="{{ route('admin.orders.status', $order) }}">
                                @csrf
                                @method('patch')
                                <input type="hidden" name="status" value="{{ OrderStatus::Completed->value }}">
                                <button class="{{ $action }}">{{ match (true) {
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
                                    <button class="{{ $action }}">Oznacz jako problem</button>
                                </form>
                            </details>
                        @endif

                        @if (in_array($order->status, [OrderStatus::Shipped, OrderStatus::Completed, OrderStatus::Problem], true))
                            <form method="post" action="{{ route('admin.orders.status', $order) }}">
                                @csrf
                                @method('patch')
                                <input type="hidden" name="status" value="{{ OrderStatus::InProgress->value }}">
                                <button class="text-[13px] text-label underline decoration-line-strong underline-offset-4 hover:text-ink">Wróć do „W realizacji”</button>
                            </form>
                        @endif
                    </div>
                @endif
            </section>

            <section class="{{ $card }}">
                <h2 class="mb-4 font-serif text-[23px]">Kontakt</h2>
                <div class="grid gap-2 text-[14px]">
                    <div>{{ $order->name }}</div>
                    <a href="mailto:{{ $order->email }}" class="[overflow-wrap:anywhere]">{{ $order->email }}</a>
                    <a href="tel:+48{{ $order->phone }}" class="tabular-nums">{{ trim(chunk_split($order->phone, 3, ' ')) }}</a>
                    @if ($order->terms_version)
                        <p class="mt-2 border-t border-divider pt-2.5 text-[12.5px] leading-[1.5] text-label">
                            Regulamin zaakceptowany: {{ $order->terms_version }}, {{ $order->terms_accepted_at?->format('j.m.Y, H:i') }}
                        </p>
                    @endif
                    @if (Route::has('admin.complaints.index') && Gate::allows('manage-shop'))
                        <a href="{{ route('admin.complaints.index', ['zamowienie' => $order->number]) }}#odpowiedz" class="mt-1 text-[13px]">Odpowiedz na reklamację →</a>
                    @endif
                </div>
            </section>

            <section class="{{ $card }}">
                <h2 class="mb-4 font-serif text-[23px]">Płatność</h2>
                <dl class="grid gap-2.5 text-[14px]">
                    <div class="{{ $row }}"><dt class="text-label">Produkty</dt><dd class="tabular-nums">{{ $order->money($order->total_gross - $order->shipping_gross) }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-label">Dostawa</dt><dd class="tabular-nums">{{ $order->shipping_gross === 0 ? 'gratis' : $order->money($order->shipping_gross) }}</dd></div>
                    <div class="{{ $row }} border-t border-divider pt-2.5 font-serif text-[20px]"><dt>Razem</dt><dd class="tabular-nums">{{ $order->money($order->total_gross) }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-label">Metoda</dt><dd>{{ $order->payment_method->label() }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-label">Status</dt><dd>{{ $order->payment_status->label() }}</dd></div>
                    @if ($order->paid_at)
                        <div class="{{ $row }}"><dt class="text-label">Opłacone</dt><dd class="tabular-nums">{{ $order->paid_at->format('j.m.Y, H:i') }}</dd></div>
                    @endif
                    @if ($order->payment_provider_id)
                        <div class="{{ $row }}"><dt class="text-label">Nr płatności</dt><dd class="text-[12.5px] [overflow-wrap:anywhere] text-hint">{{ $order->payment_provider_id }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>
    </div>
</x-admin::layout>
