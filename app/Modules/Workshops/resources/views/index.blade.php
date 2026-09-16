@use('App\Modules\Shared\Support\Money')
@php
    // The topic name matches the contact form's list from the panel; if it is renamed, the form simply opens without it.
    $contactUrl = route('content.contact', ['temat' => 'Warsztaty i terminy']);
    $vouchersUrl = Route::has('vouchers.index') ? route('vouchers.index') : null;
    $chip = 'rounded-full bg-sand-dark px-[11px] py-[5px] text-[11.5px] tracking-[0.1em] text-muted uppercase';
@endphp

<x-shared::layout title="Warsztaty ceramiczne Kraków — kameralnie, do 6 osób"
                  description="Warsztaty ceramiczne w Krakowie: lepienie z ręki, szkliwienie, sesje 1:1, dla par i rodzin. Glina, narzędzia i dwa wypały w cenie. Terminy i cennik."
                  :canonical="route('workshops.index')">
    <x-slot:head>{!! $structuredData !!}</x-slot:head>
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">warsztaty &middot; cennik</div>
        <h1 class="mb-[18px] font-serif text-[length:clamp(38px,5.2vw,70px)] leading-[1.04] font-light tracking-[-0.02em]">Zanurz dłonie<br>w glinie</h1>
        @if ($lead)
            <p class="mb-[52px] max-w-[56ch] text-[17px] leading-[1.7] text-pretty text-lead">{{ $lead }}</p>
        @endif

        @if ($workshops->isNotEmpty())
            <div class="mb-[76px] grid grid-cols-[repeat(auto-fit,minmax(290px,1fr))] gap-[22px]">
                @foreach ($workshops as $workshop)
                    <article class="flex animate-ma-up flex-col rounded-[4px] border border-divider bg-cream px-7 py-[30px]" style="animation-delay: {{ $loop->index * 0.06 }}s">
                        <div class="mb-3.5 flex items-start justify-between gap-3.5">
                            <h2 class="min-w-0 font-serif text-[26px] leading-[1.15]">{{ $workshop['name'] }}</h2>
                            <div class="flex-none text-right">
                                <div class="font-serif text-[25px] leading-none tabular-nums">{{ Money::format($workshop['price_gross']) }}</div>
                                @if ($workshop['unit_label'])
                                    <div class="mt-1 text-[11.5px] text-label">{{ $workshop['unit_label'] }}</div>
                                @endif
                            </div>
                        </div>
                        <x-shared::price-before-reduction :was="$workshop['was']" :lowest="$workshop['lowest']" class="-mt-1.5 mb-3.5 text-right" />
                        @if ($workshop['duration_label'] || $workshop['group_label'])
                            <div class="mb-4 flex flex-wrap gap-2">
                                @foreach (array_filter([$workshop['duration_label'], $workshop['group_label']]) as $fact)
                                    <span class="{{ $chip }}">{{ $fact }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if ($workshop['summary'])
                            <p class="mb-[18px] text-[15px] leading-[1.62] text-lead">{{ $workshop['summary'] }}</p>
                        @endif
                        @if ($workshop['includes'])
                            <ul class="mt-auto grid gap-2 border-t border-sand-dark pt-4">
                                @foreach ($workshop['includes'] as $included)
                                    <li class="flex gap-2.5 text-[13.5px] text-muted"><span aria-hidden="true" class="text-gold">✦</span><span>{{ $included }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap gap-11">
            <div class="min-w-0 flex-[1_1_420px]">
                <h2 class="mb-2 font-serif text-[length:clamp(28px,3.4vw,40px)] font-light">Jak się zapisać</h2>
                <p class="mb-6 max-w-[52ch] text-[15.5px] leading-[1.65] text-muted">
                    Zapisy przez stronę dopiero przygotowuję. Na razie napisz, który warsztat Cię ciekawi i ile osób przyjdzie — ustalimy termin.
                </p>
                @if ($location)
                    <p class="mb-6 max-w-[52ch] text-[15px] leading-[1.65] text-lead">Pracownia: {{ $location }}. Dokładny adres podaję po zapisie.</p>
                @endif
                @if ($vouchersUrl)
                    <a href="{{ $vouchersUrl }}" class="border-b border-line-strong pb-[3px] text-[13.5px] tracking-[0.06em]">Warsztat na prezent? Zobacz vouchery →</a>
                @endif
            </div>
            <div class="min-w-[280px] flex-[0_1_340px]">
                <div class="focus-on-dark rounded-[4px] bg-ink px-7 py-[30px] text-divider">
                    <div class="mb-[18px] text-[11px] tracking-[0.2em] text-gold uppercase">zapisy</div>
                    <p class="mb-5 font-serif text-[21px] leading-[1.4] text-cream">Napisz, który warsztat i ile osób — odpiszę z wolnymi terminami.</p>
                    <div class="grid gap-3">
                        @if ($phone)
                            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $phone) }}" target="_blank" rel="noopener"
                               class="rounded-full bg-rose p-[15px] text-center text-[14.5px] text-ink transition duration-300 hover:bg-sand hover:text-ink active:scale-[.97]">Napisz na WhatsAppie</a>
                        @endif
                        <a href="{{ $contactUrl }}"
                           class="rounded-full border border-line-dark p-[15px] text-center text-[14.5px] text-sand transition duration-300 hover:border-rose hover:text-rose active:scale-[.98]">Napisz przez formularz</a>
                    </div>
                    @if ($phone)
                        <p class="mt-3.5 text-[12.5px] leading-[1.55] text-label-dark">WhatsApp: {{ $phone }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-shared::layout>
