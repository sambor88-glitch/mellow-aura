@use('Illuminate\Support\Facades\Vite')
@php
    // The topic name matches the contact form's list from the panel; if it is renamed, the form simply opens without it.
    $contactUrl = route('content.contact', ['temat' => 'Zamówienie indywidualne']);
    $whatsApp = $phone ? 'https://wa.me/'.preg_replace('/\D+/', '', $phone) : null;
    $photos = [
        ['zdjecia/zestaw-filizanka-talerz.webp', 'Filiżanka z talerzykiem z malowanym wzorem'],
        ['zdjecia/kubek-krolowa-matka.webp', 'Kubek z wbijanym napisem'],
        ['zdjecia/podstawki-muszle-roz.webp', 'Różowe podstawki pod biżuterię w kształcie muszli'],
    ];
    $checklist = [
        'Co to ma być i ile sztuk',
        'Na kiedy tego potrzebujesz',
        'Budżet, jaki masz w głowie',
        $whatsApp ? 'Zdjęcia inspiracji — najłatwiej wysłać je na WhatsAppie' : 'Zdjęcia inspiracji, jeśli je masz',
    ];
@endphp

<x-shared::layout title="Zamówienia indywidualne — ceramika na zamówienie"
                  description="Serwis na wesele, kubek z Twoim napisem, forma dekoracyjna. Bezpłatny szkic i wycena w pięć dni, płatność BLIK-iem, realizacja około czterech tygodni."
                  :canonical="route('custom-orders.index')">
    <x-slot:head>{!! $structuredData !!}</x-slot:head>
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <div class="flex flex-wrap gap-14">
            <div class="min-w-0 flex-[1_1_420px]">
                <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">zamówienia indywidualne</div>
                <h1 class="mb-[22px] font-serif text-[length:clamp(38px,5.2vw,68px)] leading-[1.04] font-light tracking-[-0.02em]">Zaprojektujmy<br>to razem</h1>
                @if ($lead)
                    <p class="mb-10 max-w-[52ch] text-[17px] leading-[1.7] text-pretty text-lead">{{ $lead }}</p>
                @endif

                @if ($steps->isNotEmpty())
                    <h2 class="sr-only">Jak powstaje zamówienie</h2>
                    <ol class="mb-11 grid gap-px overflow-hidden rounded-[4px] border border-divider bg-divider">
                        @foreach ($steps as $step)
                            <li class="flex items-baseline gap-5 bg-cream px-[26px] py-6">
                                <span aria-hidden="true" class="w-[26px] flex-none font-serif text-[22px] text-gold">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <div class="min-w-0">
                                    <h3 class="mb-1 text-[16px]">{{ $step['title'] }}</h3>
                                    @if (filled($step['text'] ?? null))
                                        <p class="text-[14.5px] leading-[1.6] text-muted">{{ $step['text'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif

                <div class="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-3.5">
                    @foreach ($photos as [$photo, $alt])
                        <img src="{{ Vite::asset($photo) }}" alt="{{ $alt }}" loading="lazy" width="600" height="600" class="block aspect-square w-full rounded-[4px] bg-line-soft object-cover">
                    @endforeach
                </div>
            </div>

            <div class="min-w-0 flex-[1_1_340px]">
                <div class="rounded-[4px] border border-divider bg-cream px-[30px] py-8">
                    <h2 class="mb-1.5 font-serif text-[25px] font-normal">Opowiedz o pomyśle</h2>
                    <p class="mb-5 text-[14px] text-label">Kilka zdań wystarczy — resztę dopytam.</p>
                    <ul class="mb-7 grid gap-2.5">
                        @foreach ($checklist as $item)
                            <li class="flex gap-2.5 text-[14.5px] text-lead"><span aria-hidden="true" class="text-gold">✦</span><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                    <div class="grid gap-3">
                        @if ($whatsApp)
                            <a href="{{ $whatsApp }}" target="_blank" rel="noopener"
                               class="rounded-full bg-ink p-[15px] text-center text-[14.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Napisz na WhatsAppie</a>
                        @endif
                        <a href="{{ $contactUrl }}"
                           @class([
                               'rounded-full p-[15px] text-center text-[14.5px] transition duration-300 active:scale-[.98]',
                               'border border-line-strong text-ink hover:border-ink hover:bg-sand-dark hover:text-ink' => $whatsApp,
                               'bg-ink text-linen hover:bg-rose hover:text-ink' => ! $whatsApp,
                           ])>Napisz przez formularz</a>
                    </div>
                    @if ($whatsApp)
                        <p class="mt-5 border-t border-sand-dark pt-5 text-[13.5px] leading-[1.6] text-muted">Woli Ci się pisać na WhatsAppie? <a href="{{ $whatsApp }}" target="_blank" rel="noopener" class="whitespace-nowrap">{{ $phone }}</a></p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-shared::layout>
