@use('App\Modules\Localization\Support\Locales')
@php
    // Buttons to pages that don't exist yet, or not in the language of this page, fall back to the contact page,
    // so this page never ends in a dead end and /en/studio never leads to a Polish page.
    $workshopsUrl = Locales::has('workshops.index', Locales::current()) ? route('workshops.index') : null;
    $firingUrl = Locales::has('firing.index', Locales::current()) ? route('firing.index') : null;
@endphp

<x-shared::layout title="Pracownia w Krakowie — ciepło, empatia, cierpliwość"
                  description="Kameralna domowa pracownia w Krakowie: jeden stół, do sześciu osób, dwa wypały w cenie warsztatu."
                  :canonical="route('content.studio')">
    <div class="animate-ma-view pb-24">
        @if ($hero !== null)
            <div class="relative">
                <img src="{{ $hero }}" alt="" fetchpriority="high"
                     @if ($heroSrcset) srcset="{{ $heroSrcset }}" sizes="100vw" @endif
                     @if ($heroSize) width="{{ $heroSize['width'] }}" height="{{ $heroSize['height'] }}" @endif
                     class="block h-[clamp(320px,46vh,520px)] w-full bg-line-soft object-cover brightness-82">
                {{-- Darker in the lower 40 %, so the small eyebrow keeps 4.5:1 on a light photo. --}}
                <div class="absolute inset-0 flex items-end bg-linear-to-t from-scrim/72 via-scrim/60 via-40% to-scrim/5">
                    <div class="mx-auto w-full max-w-[1280px] px-7 pb-12">
                        <div class="mb-4 text-[10.5px] tracking-[0.3em] text-sand uppercase">pracownia &middot; kraków</div>
                        <h1 class="max-w-[22ch] font-serif text-[length:clamp(34px,5vw,66px)] leading-[1.04] font-light text-pretty text-cream">Kameralna pracownia. Ciepło, empatia, cierpliwość.</h1>
                    </div>
                </div>
            </div>
        @else
            <div class="mx-auto max-w-[1280px] px-7 pt-14">
                <div class="mb-4 text-[10.5px] tracking-[0.3em] text-brown uppercase">pracownia &middot; kraków</div>
                <h1 class="max-w-[22ch] font-serif text-[length:clamp(34px,5vw,66px)] leading-[1.04] font-light text-pretty">Kameralna pracownia. Ciepło, empatia, cierpliwość.</h1>
            </div>
        @endif

        <div class="mx-auto max-w-[1280px] px-7 pt-16">
            <div class="flex flex-wrap gap-14">
                <div class="min-w-0 flex-[1_1_420px]">
                    @if ($lead)
                        <p class="mb-[26px] font-serif text-[length:clamp(21px,2.4vw,28px)] leading-[1.48] text-pretty">{{ $lead }}</p>
                    @endif
                    @foreach ($paragraphs as $paragraph)
                        <p class="mb-5 text-[16.5px] leading-[1.72] text-lead">{{ $paragraph }}</p>
                    @endforeach
                    <div class="mt-3 cta-row">
                        <a href="{{ $workshopsUrl ?? route('content.contact') }}" class="rounded-full bg-ink px-[30px] py-[15px] text-[14px] text-linen transition-[background-color,transform] duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">{{ $workshopsUrl ? 'Terminy i cennik' : 'Zapytaj o warsztaty' }}</a>
                        @if ($firingUrl)
                            <a href="{{ $firingUrl }}" class="rounded-full border border-line-strong px-[30px] py-[15px] text-[14px] text-ink transition-[border-color,background-color,transform] duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.98]">Wypalę Twoje prace</a>
                        @endif
                    </div>
                </div>

                @if ($facts->isNotEmpty())
                    <div class="min-w-0 basis-full sm:min-w-[260px] sm:flex-[0_1_300px]">
                        <div class="rounded-[20px] border border-divider bg-cream p-7">
                            <h2 class="mb-[18px] text-[11px] tracking-[0.2em] text-brown uppercase">dobrze wiedzieć</h2>
                            <dl class="grid gap-4 text-[14.5px] leading-[1.6] text-lead">
                                @foreach ($facts as $fact)
                                    <div>
                                        <dt class="text-ink">{{ $fact['title'] }}</dt>
                                        @if (filled($fact['text'] ?? null))
                                            <dd>{{ $fact['text'] }}</dd>
                                        @endif
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($gallery->isNotEmpty())
            <div class="mx-auto max-w-[1280px] px-7 pt-16">
                {{-- Two, three or four to a row, and only whole rows: a photo left alone in the last row is not shown
                     at that width (Kasia adds photos in the panel, so their number is not known in advance). --}}
                @php
                    $count = $gallery->count();
                    $whole = fn (int $columns) => $count >= $columns ? $count - $count % $columns : $count;
                @endphp
                <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($gallery as $photo)
                        <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" loading="lazy" width="600" height="600" @class([
                            'block aspect-square w-full rounded-[20px] bg-line-soft object-cover',
                            'max-sm:hidden' => $loop->index >= $whole(2),
                            'sm:max-lg:hidden' => $loop->index >= $whole(3),
                            'lg:hidden' => $loop->index >= $whole(4),
                        ])>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-shared::layout>
