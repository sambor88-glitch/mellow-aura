@use('App\Modules\Localization\Support\Locales')
{{--
    The way to the other language, next to the cart. A plain link, so it works without JavaScript and search engines
    follow it. From a page with no twin it leads to the nearest page in that language and says why (see „notice”).
--}}
@foreach (Locales::enabled() as $locale)
    @continue($locale === Locales::current())
    @php
        $href = Locales::switchUrl(request(), $locale);
        if (Locales::switchFallsBack(request(), $locale)) {
            $href .= '?'.http_build_query(['unavailable' => 1]);
        }
    @endphp
    <a href="{{ $href }}" hreflang="{{ $locale }}" lang="{{ $locale }}"
       class="fill-btn flex min-h-11 min-w-11 items-center justify-center rounded-full border border-line-strong px-3 text-[12.5px] tracking-[0.08em] text-ink [--fill:var(--color-sand-dark)]">
        <span aria-hidden="true">{{ config("localization.locales.$locale.short") }}</span>
        <span class="sr-only">{{ __('localization::switcher.name', [], $locale) }}</span>
    </a>
@endforeach
