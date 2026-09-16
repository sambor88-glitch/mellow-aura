@use('App\Modules\Shared\Support\Money')
@php
    $card = 'rounded-[4px] border border-line bg-cream px-[26px] py-7';
    $row = 'flex flex-wrap justify-between gap-x-6 gap-y-0.5';
    $address = $order->shipping_address;
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
                    <a href="{{ route('admin.withdrawals.index') }}" class="mt-2.5 inline-block text-[13.5px]">Wszystkie odstąpienia →</a>
                </section>
            @endforeach

            <section class="{{ $card }}">
                <h2 class="mb-2 font-serif text-[23px]">Pozycje</h2>
                @foreach ($order->items as $item)
                    <div class="border-b border-sand-dark py-3.5 last:border-b-0">
                        <div class="flex flex-wrap justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[15px]">{{ $item->product_name }}</div>
                                <div class="mt-0.5 text-[13px] text-label">{{ collect([$item->variant_label, $item->quantity.' szt.', Money::format($item->unit_price_gross).' za sztukę'])->filter()->join(' · ') }}</div>
                            </div>
                            <div class="font-serif text-[18px] tabular-nums">{{ Money::format($item->total()) }}</div>
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
                        @if ($item->missing_quantity > 0)
                            <p class="mt-2.5 rounded-[4px] border border-alert-line bg-alert px-3.5 py-2.5 text-[13.5px] text-alert-text">
                                Brakuje {{ $item->missing_quantity }} szt. — ktoś kupił ostatnią sztukę chwilę wcześniej. Napisz do klientki, czy zrobisz kolejną, czy oddasz pieniądze.
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
                </div>
            </section>

            <section class="{{ $card }}">
                <h2 class="mb-4 font-serif text-[23px]">Płatność</h2>
                <dl class="grid gap-2.5 text-[14px]">
                    <div class="{{ $row }}"><dt class="text-label">Produkty</dt><dd class="tabular-nums">{{ Money::format($order->total_gross - $order->shipping_gross) }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-label">Dostawa</dt><dd class="tabular-nums">{{ $order->shipping_gross === 0 ? 'gratis' : Money::format($order->shipping_gross) }}</dd></div>
                    <div class="{{ $row }} border-t border-divider pt-2.5 font-serif text-[20px]"><dt>Razem</dt><dd class="tabular-nums">{{ Money::format($order->total_gross) }}</dd></div>
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
