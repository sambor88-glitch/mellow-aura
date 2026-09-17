@use('Illuminate\Support\Facades\Vite')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $lead = $settings->get('text_about_paragraph_1');
    $paragraphs = array_filter([$settings->get('text_about_paragraph_2'), $settings->get('text_about_paragraph_3')]);

    // A tile without its text is waiting for Kasia to fill it in, so it stays off the page.
    $materials = collect((array) $settings->get('material_tiles', []))
        ->filter(fn (mixed $tile) => is_array($tile) && filled($tile['title'] ?? null) && filled($tile['text'] ?? null));

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
                    <div class="mt-3 flex flex-wrap gap-3.5">
                        @if ($studioUrl)
                            <a href="{{ $studioUrl }}" class="rounded-full bg-ink px-[30px] py-[15px] text-[14px] text-linen transition-[background-color,transform] duration-300 hover:bg-navy hover:text-linen active:scale-[.97]">Zobacz jak tworzę</a>
                        @endif
                        <a href="{{ route('content.contact') }}" class="rounded-full border border-line-strong px-[30px] py-[15px] text-[14px] text-ink transition-[border-color,background-color,transform] duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.98]">Napisz do mnie</a>
                    </div>
                </div>
                <div class="min-w-0 flex-[1_1_340px]">
                    <img src="{{ Vite::asset('zdjecia/kasia-talerz-zloto.webp') }}" alt="Kasia trzymająca talerz ze złoconą krawędzią"
                         width="600" height="800" class="block aspect-[3/4] w-full rounded-[4px] bg-line-soft object-cover">
                    <div class="mt-3.5 flex gap-3.5">
                        <img src="{{ Vite::asset('zdjecia/kubek-ochujeje.webp') }}" alt="Kubek z wbijanym tekstem" loading="lazy"
                             width="600" height="600" class="block aspect-square min-w-0 flex-1 rounded-[4px] bg-line-soft object-cover">
                        <img src="{{ Vite::asset('zdjecia/kadzielnica-pszczoly.webp') }}" alt="Kadzielnica ze złoconym kołnierzem" loading="lazy"
                             width="600" height="600" class="block aspect-square min-w-0 flex-1 rounded-[4px] bg-line-soft object-cover">
                    </div>
                </div>
            </div>
        </div>

        @if ($materials->isNotEmpty())
            <div class="ma-reveal mx-auto max-w-[1280px] px-7 pt-21">
                <dl class="flex flex-wrap gap-px overflow-hidden rounded-[4px] border border-divider bg-divider">
                    @foreach ($materials as $tile)
                        <div class="min-w-0 flex-[1_1_220px] bg-cream px-[26px] py-8">
                            <dt class="mb-2 font-serif text-[21px]">{{ $tile['title'] }}</dt>
                            <dd class="text-[14.5px] leading-[1.6] text-muted">{{ $tile['text'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        @if ($featured->isNotEmpty())
            <section class="ma-reveal mx-auto max-w-[1280px] px-7 pt-21">
                <h2 class="mb-1.5 font-serif text-[length:clamp(30px,3.6vw,44px)] font-light">Niektóre z moich prac</h2>
                <p class="mb-8 text-[16.5px] text-muted">Wszystko zrobione w zgodzie z naturą i kobiecą intuicją.</p>
                <div class="grid grid-cols-[repeat(auto-fit,minmax(230px,1fr))] gap-[26px]">
                    @foreach ($featured as $product)
                        <x-catalog::product-card :product="$product" :delay="$loop->index * 0.09" :with-variant-count="false" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-shared::layout>
