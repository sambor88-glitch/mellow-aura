@use('App\Modules\Catalog\Enums\CategoryGroup')
@use('App\Modules\Catalog\Support\ProductPhoto')
@use('App\Modules\Checkout\Support\CertificatePdf')
@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\FocalPoint')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    // Only what the piece is — never who bought it, for how much or where it went (MA-234).
    $product = $item->variant?->product;
    // The photo of this very piece from the panel, else the shop's photo of its product (MA-290).
    $photo = $item->photo();
    $srcset = $photo ? ProductPhoto::srcset($photo) : null;
    $alt = $photo?->collection_name === 'photo'
        ? __('checkout::piece.photo_alt', ['name' => $item->product_name])
        : ($photo?->getCustomProperty('alt') ?: $item->product_name);
    $polish = Locales::current() === Locales::default();
    // A mug from the configurator has no product in the shop, but it is ceramics too (as on the certificate).
    $ceramics = $product === null || $product->category?->group === CategoryGroup::Ceramics;
    // The general care rule from the panel is written in Polish, so an English page shows only the piece's own line.
    $care = $product?->care_note ?: ($ceramics && $polish ? $settings->get('care_rule_ceramics') : null);
    $quote = $settings->get(CertificatePdf::setting('quote', Locales::current())) ?: __('checkout::certificate.quote');
    // Where the idea came from, in the language of the order: a product without it in that language shows nothing (MA-292).
    $story = $product?->story;
    $facts = collect([
        [__('checkout::piece.fired'), $item->fired_on?->translatedFormat('j F Y')],
        [__('checkout::piece.glaze'), $item->glaze],
        [__('checkout::piece.plant'), $item->plant],
    ])->filter(fn (array $fact) => filled($fact[1]));
    $handle = ltrim((string) $settings->get('instagram_handle'), '@');
    $btnMain = 'fill-btn inline-flex min-h-[52px] items-center gap-2.5 rounded-full bg-ink px-[30px] text-[14px] font-medium tracking-[0.04em] text-linen transition-[background-color,color] duration-300 [--fill:var(--color-rose)] hover:bg-rose hover:text-ink active:scale-[.97]';
    $btnGlass = 'fill-btn glass inline-flex min-h-[52px] items-center gap-2.5 rounded-full px-[30px] text-[14px] font-medium tracking-[0.04em] text-ink transition-[border-color,color] duration-300 [--fill:var(--color-cream)] hover:border-ink hover:text-ink active:scale-[.97]';
@endphp

<x-shared::layout :title="__('checkout::piece.title', ['name' => $item->product_name])" :noindex="true">
    <div class="mx-auto max-w-[1160px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-10 pb-24">
        <div class="flex flex-wrap items-start gap-[clamp(28px,5vw,72px)]">
            {{-- Only Kasia's photos: a piece without one opens with its name, never with an empty frame or someone else's picture. --}}
            @if ($photo)
                <div class="min-w-0 flex-[1_1_420px]">
                    <img src="{{ $photo->getAvailableUrl(['card']) }}" alt="{{ $alt }}" fetchpriority="high"
                         @if ($srcset) srcset="{{ $srcset }}" sizes="(min-width: 872px) 520px, calc(100vw - 36px)" @endif
                         width="1200" height="1500" style="{{ FocalPoint::style($photo) }}"
                         class="block aspect-[4/5] w-full rounded-[26px] bg-linen object-cover">
                </div>
            @endif

            <div @class(['min-w-0 flex-[1_1_380px]', 'max-w-[720px]' => ! $photo])>
                <p class="eyebrow mb-5">{{ __('checkout::piece.eyebrow') }}</p>
                <h1 class="font-serif text-[length:clamp(38px,6.4vw,72px)] leading-[.98] font-light tracking-[-0.02em] text-balance [overflow-wrap:anywhere]">{{ $item->product_name }}</h1>
                @if (filled($item->variant_label))
                    <p class="mt-3 text-[15px] text-label">{{ $item->variant_label }}</p>
                @endif
                @if (filled($item->maker_note))
                    {{-- Kasia's own words about this one piece (MA-292) take the place of the quote every piece shares. --}}
                    <p class="mt-6 max-w-[46ch] font-serif text-[21px] leading-[1.45] font-light text-graphite whitespace-pre-line [overflow-wrap:anywhere]">{{ $item->maker_note }}</p>
                @else
                    <p class="mt-6 max-w-[46ch] font-serif text-[21px] leading-[1.35] font-light text-lead italic whitespace-pre-line">{{ $quote }}</p>
                @endif

                @if ($facts->isNotEmpty() || $item->custom_text !== null)
                    <dl class="mt-8 grid gap-0 border-t border-line">
                        @foreach ($facts as [$label, $value])
                            <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 border-b border-line py-3.5">
                                <dt class="text-[11px] tracking-[0.16em] text-label uppercase">{{ $label }}</dt>
                                <dd class="min-w-0 text-[15px] [overflow-wrap:anywhere]">{{ $value }}</dd>
                            </div>
                        @endforeach
                        @if ($item->custom_text !== null)
                            {{-- Typed by the customer: escaped like every other place the text shows. --}}
                            <div class="flex flex-wrap justify-between gap-x-6 gap-y-1 border-b border-line py-3.5">
                                <dt class="text-[11px] tracking-[0.16em] text-label uppercase">{{ __('checkout::piece.text') }}</dt>
                                <dd class="min-w-0 text-[15px] tracking-[0.08em] whitespace-pre-line [overflow-wrap:anywhere]">„{{ $item->custom_text }}”</dd>
                            </div>
                        @endif
                    </dl>
                @endif

                @if (filled($story))
                    <section class="mt-9">
                        <h2 class="mb-2.5 font-serif text-[24px] font-light">{{ __('checkout::piece.story_heading') }}</h2>
                        <p class="max-w-[52ch] text-[15.5px] leading-[1.68] text-lead whitespace-pre-line">{{ $story }}</p>
                    </section>
                @endif

                @if (filled($care))
                    <section class="mt-9">
                        <h2 class="mb-2.5 font-serif text-[24px] font-light">{{ __('checkout::piece.care_heading') }}</h2>
                        <p class="max-w-[52ch] text-[15.5px] leading-[1.68] text-lead whitespace-pre-line">{{ $care }}</p>
                    </section>
                @endif

                <section class="mt-10 rounded-[24px] glass px-[clamp(20px,4vw,30px)] py-7">
                    <h2 class="font-serif text-[26px] leading-[1.1] font-light">{{ __('checkout::piece.similar_heading') }}</h2>
                    <p class="mt-2.5 mb-6 max-w-[46ch] text-[15px] leading-[1.6] text-lead">{{ __('checkout::piece.similar_text') }}</p>
                    <div class="cta-row">
                        @if (Route::has('custom-orders.index'))
                            <a href="{{ route('custom-orders.index') }}#formularz" class="{{ $btnMain }}">{{ __('checkout::piece.similar_cta') }} <span aria-hidden="true">→</span></a>
                        @endif
                        <a href="{{ route('shop.index') }}" class="{{ $btnGlass }}">{{ __('checkout::piece.shop_cta') }}</a>
                    </div>
                </section>

                @if ($handle !== '')
                    <p class="mt-6 text-[14px] leading-[1.6] text-muted">
                        <a href="https://www.instagram.com/{{ rawurlencode($handle) }}/" target="_blank" rel="noopener">{{ __('checkout::piece.instagram', ['handle' => $handle]) }}</a>
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-shared::layout>
