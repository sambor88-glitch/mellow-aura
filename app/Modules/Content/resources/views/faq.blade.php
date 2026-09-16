@use('App\Modules\Shared\Support\Money')
<x-shared::layout title="Wysyłka, płatności i pielęgnacja ceramiki — FAQ"
                  description="Jak myć ceramikę ze złotem, czy można do zmywarki, ile trwa wysyłka, jak zapłacić BLIK-iem i jak wyglądają zwroty."
                  :canonical="route('content.faq')">
    <div class="mx-auto max-w-[900px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">wysyłka, płatności, pielęgnacja</div>
        <h1 class="mb-11 font-serif text-[length:clamp(38px,5.2vw,64px)] leading-[1.04] font-light tracking-[-0.02em]">Częste pytania</h1>

        @if ($questions->isNotEmpty())
            <div class="border-t border-divider">
                @foreach ($questions as $item)
                    {{-- The same name opens one answer at a time, like the prototype. --}}
                    <details name="faq" class="group border-b border-divider">
                        <summary class="flex cursor-pointer list-none items-baseline gap-5 px-1 py-6 [&::-webkit-details-marker]:hidden">
                            <span class="min-w-0 flex-1 font-serif text-[length:clamp(19px,2.2vw,24px)] leading-[1.3]">{{ $item['question'] }}</span>
                            <span aria-hidden="true" class="flex-none text-[22px] leading-none text-brown"><span class="group-open:hidden">+</span><span class="hidden group-open:inline">−</span></span>
                        </summary>
                        <div class="animate-ma-up-quick pr-11 pb-[26px] pl-1 text-[16px] leading-[1.72] text-pretty whitespace-pre-line text-lead">{{ $item['answer'] }}</div>
                    </details>
                @endforeach
            </div>
        @endif

        @if ($shippingMethods->isNotEmpty())
            <section aria-labelledby="wysylka-i-zwroty" class="mt-12 rounded-[4px] border border-divider bg-cream px-[30px] py-8">
                <h2 id="wysylka-i-zwroty" class="mb-5 font-serif text-[25px]">Wysyłka i zwroty</h2>
                <div class="grid gap-px overflow-hidden rounded-[4px] border border-divider bg-divider">
                    @foreach ($shippingMethods as $method)
                        <div class="flex items-baseline justify-between gap-4 bg-linen px-5 py-4">
                            <div class="min-w-0">
                                <div class="text-[15px] text-ink">{{ $method['label'] }}</div>
                                @if ($method['note'])
                                    <div class="mt-0.5 text-[13px] text-label">{{ $method['note'] }}</div>
                                @endif
                            </div>
                            <div class="flex-none font-serif text-[19px] tabular-nums">{{ $method['price_gross'] === 0 ? 'gratis' : Money::format($method['price_gross']) }}</div>
                        </div>
                    @endforeach
                </div>
                @if ($freeFrom > 0)
                    <p class="mt-4 text-[14.5px] leading-[1.7] text-lead">Od {{ Money::format($freeFrom) }} za produkty każda wysyłka jest gratis.</p>
                @endif
                <p class="mt-4 text-[14.5px] leading-[1.7] text-lead">
                    @if ($returnAddress)
                        Zwroty odsyłasz na adres: {{ $returnAddress }}.
                    @endif
                    Jak odstąpić od umowy i złożyć reklamację, piszę w&nbsp;<a href="{{ route('content.terms') }}#regulamin-12-prawo-odstapienia-od-umowy">regulaminie, w&nbsp;§12 i&nbsp;§13</a>.
                </p>
                @if (Route::has('withdrawal.create'))
                    <a href="{{ route('withdrawal.create') }}" class="mt-5 inline-block rounded-full border border-line-strong px-[26px] py-[13px] text-[14px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.98]">Odstąp od umowy tutaj</a>
                @endif
            </section>
        @endif

        <div class="mt-12 flex flex-wrap items-center justify-between gap-6 rounded-[4px] border border-divider bg-cream p-8">
            <div class="min-w-0">
                <h2 class="mb-1.5 font-serif text-[24px]">Nie ma tu Twojego pytania?</h2>
                <p class="text-[15px] text-muted">{{ $phone ? 'Napisz na WhatsAppie — to najszybsza droga do mnie.' : 'Napisz do mnie — odpisuję zwykle tego samego dnia.' }}</p>
            </div>
            <a href="{{ route('content.contact') }}" class="rounded-full bg-ink px-[30px] py-[15px] text-[14px] text-linen transition duration-300 hover:bg-navy hover:text-linen active:scale-[.97]">Kontakt</a>
        </div>
    </div>
</x-shared::layout>
