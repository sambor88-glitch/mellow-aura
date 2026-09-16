@use('App\Modules\Shared\Support\Seo')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $sections = $document->sections();
    $effectiveFrom = $document->effectiveFrom();
@endphp

<x-shared::layout :title="Seo::title($seoTitle)" :description="$description" :canonical="$canonical" :noindex="$document->isDraft()">
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">dokumenty sklepu</div>
        <h1 class="mb-3 max-w-[20ch] font-serif text-[length:clamp(34px,4.6vw,56px)] leading-[1.06] font-light tracking-[-0.02em] text-balance">{{ $document->title() }}</h1>
        <p class="mb-8 text-[13.5px] text-label">
            {{ ucfirst($document->versionLabel()) }} &middot; obowiązuje {{ $effectiveFrom ? 'od '.$effectiveFrom->translatedFormat('j F Y') : 'od startu sklepu' }}
        </p>

        @if ($document->isDraft())
            <p role="note" class="mb-10 max-w-[70ch] rounded-[4px] border border-dashed border-line-strong bg-linen px-5 py-4 text-[14.5px] leading-[1.6] text-graphite">
                To projekt, który sprawdza jeszcze prawnik. <mark class="rounded-[3px] bg-rose/34 px-1 text-ink">Zaznaczone miejsca</mark> uzupełnię przed startem sklepu.
            </p>
        @endif

        <div class="flex flex-wrap items-start gap-x-12 gap-y-8">
            @if ($sections)
                <nav aria-label="Spis treści" class="min-w-0 flex-[1_1_220px] print:hidden">
                    <details open>
                        <summary class="mb-2 cursor-pointer text-[11px] tracking-[0.14em] text-label uppercase">Spis treści</summary>
                        <ol class="grid gap-0.5">
                            @foreach ($sections as $section)
                                <li><a href="#{{ $section['id'] }}" class="block py-[5px] text-[13.5px] leading-[1.4] text-lead hover:text-navy">{{ $section['title'] }}</a></li>
                            @endforeach
                        </ol>
                    </details>
                    <button type="button" x-data x-on:click="window.print()"
                            class="mt-5 min-h-11 rounded-full border border-line-strong px-5 py-2.5 text-[13px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark active:scale-[.98]">
                        Wydrukuj albo zapisz jako PDF
                    </button>
                </nav>
            @endif

            <article class="legal-document min-w-0 flex-[3_1_560px] rounded-[4px] border border-line bg-cream px-5 py-8 sm:px-12 sm:py-11">
                {!! $document->html($settings) !!}
            </article>
        </div>
    </div>
</x-shared::layout>
