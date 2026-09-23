@php
    // Buttons to pages that don't exist yet fall back to the contact page, so this page never ends in a dead end.
    $workshopsUrl = Route::has('workshops.index') ? route('workshops.index') : null;
    $firingUrl = Route::has('firing.index') ? route('firing.index') : null;
@endphp

<x-shared::layout title="Pracownia w Krakowie — ciepło, empatia, cierpliwość"
                  description="Kameralne home studio w Krakowie: jeden stół, do sześciu osób, dwa wypały w cenie warsztatu."
                  :canonical="route('content.studio')">
    <div class="animate-ma-view pb-24">
        @if ($hero !== null)
            <div class="relative">
                <img src="{{ $hero }}" alt="" class="block h-[clamp(320px,46vh,520px)] w-full bg-line-soft object-cover brightness-82">
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
                    <div class="mt-3 flex flex-wrap gap-3.5">
                        <a href="{{ $workshopsUrl ?? route('content.contact') }}" class="rounded-full bg-ink px-[30px] py-[15px] text-[14px] text-linen transition-[background-color,transform] duration-300 hover:bg-navy hover:text-linen active:scale-[.97]">{{ $workshopsUrl ? 'Terminy i cennik' : 'Zapytaj o warsztaty' }}</a>
                        @if ($firingUrl)
                            <a href="{{ $firingUrl }}" class="rounded-full border border-line-strong px-[30px] py-[15px] text-[14px] text-ink transition-[border-color,background-color,transform] duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.98]">Wypalę Twoje prace</a>
                        @endif
                    </div>
                </div>

                @if ($facts->isNotEmpty())
                    <div class="min-w-[260px] flex-[0_1_300px]">
                        <div class="rounded-[4px] border border-divider bg-cream p-7">
                            <h2 class="mb-[18px] text-[11px] tracking-[0.2em] text-gold uppercase">dobrze wiedzieć</h2>
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
                <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3.5">
                    @foreach ($gallery as $photo)
                        <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" loading="lazy" width="600" height="600" class="block aspect-square w-full rounded-[4px] bg-line-soft object-cover">
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-shared::layout>
