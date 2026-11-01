@inject('pagePhotos', 'App\Modules\Content\Support\PagePhotos')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $lead = $settings->get('text_about_paragraph_1');
    $paragraphs = array_filter([$settings->get('text_about_paragraph_2'), $settings->get('text_about_paragraph_3')]);

    $studioUrl = Route::has('content.studio') ? route('content.studio') : null;
@endphp

<x-shared::layout title="O mnie — Kasia Samborska, pracownia MellowAura | Kraków"
                  description="Poznaj Kasię — tworzy ceramikę i jedwabne rękodzieło w kameralnej pracowni w Krakowie, łącząc surowość z miękkością."
                  :canonical="route('content.about')">
    <div class="animate-ma-view pb-24">
        <div class="mx-auto max-w-[1280px] px-7 pt-14">
            <div class="flex flex-wrap items-start gap-14">
                <div class="min-w-0 flex-[1_1_380px]">
                    <div class="mb-[22px] text-[10.5px] tracking-[0.3em] text-brown uppercase">o mnie</div>
                    <h1 class="mb-[30px] font-serif text-[length:clamp(40px,5.4vw,74px)] leading-[1.02] font-light tracking-[-0.02em]">Cześć,<br>jestem <em class="text-brown italic">Kasia</em></h1>
                    @if ($lead)
                        <p class="mb-[22px] text-[18px] leading-[1.72] text-pretty text-graphite">{{ $lead }}</p>
                    @endif
                    @foreach ($paragraphs as $paragraph)
                        <p class="mb-[22px] text-[16.5px] leading-[1.75] text-pretty text-lead">{{ $paragraph }}</p>
                    @endforeach
                    <div class="mt-3 cta-row">
                        @if ($studioUrl)
                            <x-shared::button href="{{ $studioUrl }}">Zobacz, jak tworzę</x-shared::button>
                        @endif
                        <x-shared::button href="{{ route('content.contact') }}" variant="secondary">Napisz do mnie</x-shared::button>
                    </div>
                </div>
                <div class="min-w-0 flex-[1_1_340px]">
                    @if (($main = $pagePhotos->get('about-main'))->exists())
                        <img src="{{ $main->url }}" {{ $main->srcsetAttributes('(min-width: 768px) 45vw, 100vw') }} alt="{{ $main->alt('Kasia trzymająca talerz ze złoconą krawędzią') }}"
                             width="600" height="800" style="{{ $main->style() }}" class="mb-3.5 block aspect-[3/4] w-full rounded-[20px] bg-line-soft object-cover">
                    @endif
                    <div class="flex gap-3.5">
                        @foreach ([['about-detail-1', 'Kubek z wbijanym tekstem'], ['about-detail-2', 'Kadzielnica ze złoconym kołnierzem']] as [$place, $alt])
                            @continue (! ($detail = $pagePhotos->get($place))->exists())
                            <img src="{{ $detail->url }}" {{ $detail->srcsetAttributes('(min-width: 768px) 22vw, 50vw') }} alt="{{ $detail->alt($alt) }}" loading="lazy"
                                 width="600" height="600" style="{{ $detail->style() }}" class="block aspect-square min-w-0 flex-1 rounded-[20px] bg-line-soft object-cover">
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- No material tiles here: Kasia took them off „O mnie” (1.11). --}}
        @if ($featured->isNotEmpty())
            <section class="ma-reveal mx-auto max-w-[1280px] px-7 pt-21">
                <h2 class="mb-1.5 font-serif text-[length:clamp(30px,3.6vw,44px)] font-light">Niektóre z moich prac</h2>
                <p class="mb-8 text-[16.5px] text-muted">Wszystko zrobione w zgodzie z naturą i kobiecą intuicją.</p>
                <div class="grid grid-cols-[repeat(auto-fit,minmax(230px,1fr))] gap-[26px]">
                    @foreach ($featured as $product)
                        <x-catalog::product-card :phone-columns="1" :product="$product" :delay="$loop->index * 0.09" :with-variant-count="false" :with-button="false" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-shared::layout>
