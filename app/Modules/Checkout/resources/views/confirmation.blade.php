@use('App\Modules\Shared\Support\Money')
<x-shared::layout title="Zamówienie | MellowAura" :noindex="true">
    <div class="mx-auto max-w-[1000px] animate-ma-view px-7 pt-14 pb-24 text-center">
        <div class="pt-10 pb-5">
            <div aria-hidden="true" class="mx-auto mb-[26px] grid size-[76px] place-items-center rounded-full border border-line bg-sand-dark text-[30px] text-success">✓</div>
            <h1 class="mb-4 font-serif text-[length:clamp(32px,4.4vw,52px)] leading-[1.04] font-light">Dziękuję. Pakuję.</h1>
            <p class="mx-auto mb-8 max-w-[48ch] text-[17px] leading-[1.7] text-lead">
                Zamówienie <strong class="font-medium select-all">{{ $order->number }}</strong> jest opłacone. Dostaniesz maila z potwierdzeniem,
                a ode mnie zdjęcie paczki przed wysłaniem — zawijam każdą sztukę osobno.
            </p>
            @if ($order->hasShortage())
                <p role="status" class="mx-auto mb-8 max-w-[48ch] rounded-[4px] border border-alert-line bg-alert px-5 py-4 text-[15px] leading-[1.6] text-alert-text">
                    Ktoś kupił ostatnią sztukę chwilę przed Tobą. Napiszę do Ciebie, żeby ustalić, co dalej — mogę zrobić kolejną albo oddać pieniądze.
                </p>
            @endif
            <div class="mb-6 inline-flex min-w-[min(380px,100%)] flex-col gap-3.5 rounded-[4px] border border-divider bg-cream px-[30px] py-[26px] text-left">
                <div class="flex justify-between gap-[30px] text-[14.5px]"><span class="text-label">Zapłacone</span><span>{{ Money::format($order->total_gross) }}</span></div>
                @if ($shippingLabel)
                    <div class="flex justify-between gap-[30px] text-[14.5px]"><span class="text-label">Dostawa</span><span>{{ $shippingLabel }}</span></div>
                @endif
                <div class="flex justify-between gap-[30px] text-[14.5px]"><span class="text-label">Wysyłka</span><span>3–5 dni roboczych</span></div>
            </div>
            <p class="mb-8 text-[13.5px] text-muted">Mail nie przyszedł w kwadrans? Zajrzyj do folderu ze spamem.</p>
            <a href="{{ url('/') }}" class="inline-block rounded-full bg-ink px-[30px] py-[15px] text-[14px] text-linen hover:bg-navy hover:text-linen">Wróć na stronę główną</a>
        </div>
    </div>
</x-shared::layout>
