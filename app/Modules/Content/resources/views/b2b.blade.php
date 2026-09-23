@use('Illuminate\Support\Str')
@use('Illuminate\Support\Facades\Vite')
@php
    // The topic name matches the contact form's list from the panel; if it is renamed, the form simply opens without it.
    $contactUrl = route('content.contact', ['temat' => 'Kawiarnia / restauracja']);
    $whatsApp = $phone ? 'https://wa.me/'.preg_replace('/\D+/', '', $phone) : null;
    // The description follows the cards from the panel, so it never promises what the page doesn't.
    $description = 'Powtarzalne formy w ręcznej ceramice dla kawiarni i restauracji'.($facts->isNotEmpty()
        ? ': '.$facts->map(fn (array $fact) => Str::lcfirst(trim($fact['title'])))->join(', ').'.'
        : '.');
@endphp

<x-shared::layout title="Ceramika dla kawiarni i restauracji | MellowAura" :description="$description" :canonical="route('content.b2b')">
    <x-slot:head>{!! $structuredData !!}</x-slot:head>
    <div class="animate-ma-view">
        <div class="focus-on-dark bg-ink text-divider">
            <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-12 px-7 py-[72px]">
                <div class="min-w-0 flex-[1_1_400px]">
                    <div class="mb-5 text-[10.5px] tracking-[0.3em] text-label-dark uppercase">dla kawiarni, restauracji i hoteli</div>
                    <h1 class="mb-[22px] font-serif text-[length:clamp(36px,5vw,64px)] leading-[1.05] font-light tracking-[-0.02em] text-cream">Naczynia, które<br>widać na zdjęciach<br>Waszych gości</h1>
                    @if ($lead)
                        <p class="mb-8 max-w-[48ch] text-[17px] leading-[1.7] text-pretty text-line-strong">{{ $lead }}</p>
                    @endif
                    <a href="{{ $contactUrl }}" class="inline-block rounded-full bg-rose px-8 py-4 text-[14.5px] text-ink transition duration-300 hover:bg-sand hover:text-ink active:scale-[.97]">Poproś o wycenę</a>
                </div>
                <div class="min-w-0 flex-[1_1_320px]">
                    <img src="{{ Vite::asset('zdjecia/kubek-cappuccino.webp') }}" alt="Kawa w ręcznie robionym kubku" width="600" height="750" class="block aspect-[4/5] w-full rounded-[4px] bg-divider-dark object-cover">
                </div>
            </div>
        </div>

        @if ($facts->isNotEmpty())
            <div class="mx-auto max-w-[1280px] px-7 pt-[72px]">
                <h2 class="sr-only">Jak pracuję z lokalami</h2>
                <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,240px),1fr))] gap-[22px]">
                    @foreach ($facts as $fact)
                        <div class="rounded-[4px] border border-divider bg-cream px-7 py-[30px]">
                            <h3 class="mb-2.5 font-serif text-[23px] leading-[1.2] font-normal">{{ $fact['title'] }}</h3>
                            @if (filled($fact['text'] ?? null))
                                <p class="text-[14.5px] leading-[1.62] text-muted">{{ $fact['text'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mx-auto max-w-[1280px] px-7 pt-11 pb-24">
            <div class="flex flex-wrap items-center justify-between gap-[30px] rounded-[4px] bg-sand-dark px-[clamp(22px,4vw,40px)] py-11">
                <div class="min-w-0 flex-[1_1_340px]">
                    <h2 class="mb-2 font-serif text-[length:clamp(24px,3vw,34px)] leading-[1.2] font-light text-balance">{{ $ctaHeading ?: 'Porozmawiajmy o Waszym lokalu' }}</h2>
                    @if ($cta)
                        <p class="text-[15.5px] text-lead">{{ $cta }}</p>
                    @endif
                    @if ($whatsApp)
                        <p class="mt-2 text-[14px] text-muted">Albo napisz na WhatsAppie: <a href="{{ $whatsApp }}" target="_blank" rel="noopener" class="whitespace-nowrap">{{ $phone }}</a></p>
                    @endif
                </div>
                <a href="{{ $contactUrl }}" class="flex-none rounded-full bg-ink px-8 py-4 text-[14.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Umów spotkanie</a>
            </div>
        </div>
    </div>
</x-shared::layout>
