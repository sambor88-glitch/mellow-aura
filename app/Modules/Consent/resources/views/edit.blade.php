@php
    $row = 'rounded-[4px] border border-divider bg-cream px-6 py-5';
    $radio = 'group flex cursor-pointer items-start gap-3.5 rounded-[4px] border border-line bg-white px-[18px] py-4 has-checked:border-ink has-checked:bg-sand-dark has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
@endphp

<x-shared::layout title="Ustawienia cookies | MellowAura" :noindex="true">
    <div class="mx-auto max-w-[900px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">prywatność</div>
        <h1 class="mb-6 font-serif text-[length:clamp(38px,5.2vw,64px)] leading-[1.04] font-light tracking-[-0.02em]">Ustawienia cookies</h1>
        <p class="mb-10 max-w-[58ch] text-[17px] leading-[1.7] text-pretty text-lead">
            Niezbędne pliki pilnują koszyka i bezpieczeństwa formularzy — bez nich zamówienie się nie złoży.
            @if ($needed)
                Na statystyki Google Analytics pytam o zgodę i włączam je dopiero po Twoim „tak”.
            @else
                Innych plików teraz nie używam, więc nie ma tu nic do wybrania.
            @endif
        </p>

        @if (session('consent_saved'))
            <p role="status" class="mb-6 rounded-[4px] border border-line-strong bg-sand-dark p-4 text-[14px] leading-[1.6] text-graphite">
                Zapisane. {{ $analytics ? 'Statystyki są włączone.' : 'Zostają tylko niezbędne pliki.' }}
            </p>
        @endif

        <div class="mb-8 grid gap-2.5">
            <div class="{{ $row }}">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                    <h2 class="font-serif text-[21px]">Niezbędne</h2>
                    <span class="text-[12.5px] text-label">zawsze włączone</span>
                </div>
                <p class="mt-1 text-[14.5px] leading-[1.6] text-muted">Koszyk, formularz zamówienia i ochrona formularzy przed podszywaniem się. Do tego zapamiętanie Twojego wyboru z tej strony.</p>
            </div>
        </div>

        @if ($needed)
            <form method="post" action="{{ route('consent.store') }}" class="rounded-[4px] border border-divider bg-cream px-[30px] py-8">
                @csrf
                <fieldset>
                    <legend class="mb-3 font-serif text-[21px]">Statystyki — Google Analytics</legend>
                    <p class="mb-4 max-w-[62ch] text-[14.5px] leading-[1.6] text-muted">Liczą odwiedziny i pokazują, które strony się przydają. Pliki _ga zostają w przeglądarce do 2 lat. Bez reklam i bez łączenia z innymi danymi.</p>
                    <div class="grid gap-2.5">
                        @foreach (['1' => ['Zgadzam się na statystyki', 'Google Analytics zacznie liczyć moje odwiedziny.'], '0' => ['Tylko niezbędne', 'Statystyki zostają wyłączone.']] as $value => [$label, $hint])
                            <label class="{{ $radio }}">
                                <input type="radio" name="analytics" value="{{ $value }}" @checked($decided && $analytics === ((string) $value === '1')) required class="sr-only">
                                <span class="mt-1 grid size-4 flex-none place-items-center rounded-full border border-label"><span class="size-2 rounded-full group-has-checked:bg-ink"></span></span>
                                <span class="min-w-0">
                                    <span class="block text-[15px]">{{ $label }}</span>
                                    <span class="mt-0.5 block text-[12.5px] text-label">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                @error('analytics')
                    <p class="mt-2 text-[13px] text-error">Zaznacz jedną z odpowiedzi</p>
                @enderror
                @if ($decidedAt)
                    <p class="mt-3 text-[12.5px] text-hint">Twój wybór z {{ $decidedAt->translatedFormat('j F Y') }}.</p>
                @endif
                <button class="mt-5 min-h-11 rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]">Zapisz wybór</button>
            </form>
        @endif

        <p class="mt-8 text-[14px] text-muted">Wszystkie pliki, które zapisuje sklep, opisuję w <a href="{{ route('content.privacy') }}">polityce prywatności</a>.</p>
    </div>
</x-shared::layout>
