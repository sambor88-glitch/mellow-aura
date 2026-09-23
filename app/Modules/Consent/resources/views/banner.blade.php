@inject('consent', 'App\Modules\Consent\Support\Consent')
@if ($consent->isNeeded())
    @php
        $decided = $consent->decided(request());
        $config = [
            'open' => ! $decided,
            'decided' => $decided,
            'analytics' => $consent->allowsAnalytics(request()),
            'measurementId' => $consent->measurementId(),
        ];
        $button = 'min-h-11 flex-1 rounded-full bg-linen px-6 py-3 text-[14px] whitespace-nowrap text-ink transition duration-300 hover:bg-rose active:scale-[.97] sm:flex-none';
    @endphp
    {{-- Shown by the server until a choice is made, so it works without JavaScript; the footer link opens it again. --}}
    <section x-data="consentBanner(@js($config))" x-show="open" @unless ($config['open']) style="display: none" @endunless
             aria-labelledby="consent-title"
             class="focus-on-dark fixed inset-x-0 bottom-0 z-70 border-t border-line-dark bg-ink text-on-dark print:hidden">
        <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-x-10 gap-y-4 px-7 pt-5 pb-[calc(20px+env(safe-area-inset-bottom))]">
            <div class="min-w-0 flex-[1_1_420px]">
                <h2 id="consent-title" class="mb-1.5 text-[11px] tracking-[0.2em] text-label-dark uppercase">pliki cookies</h2>
                <p class="max-w-[76ch] text-[14px] leading-[1.6] text-pretty">
                    Koszyk i formularze działają na niezbędnych plikach cookies. Za Twoją zgodą włączę też Google Analytics — zobaczę, które strony się przydają. Bez reklam.
                    Wybór zmienisz w każdej chwili w stopce, w „Ustawieniach cookies”.
                    <a href="{{ route('content.privacy') }}" class="text-sand underline decoration-line-dark underline-offset-4 hover:text-rose">Polityka prywatności</a>
                </p>
                <p x-show="decided" x-cloak class="mt-1.5 text-[13px] text-on-dark-muted" x-text="analytics ? 'Teraz: statystyki włączone.' : 'Teraz: tylko niezbędne pliki.'"></p>
            </div>
            <form method="post" action="{{ route('consent.store') }}" x-on:submit="choose($event)" class="flex flex-[1_1_300px] flex-wrap items-center justify-end gap-2.5">
                @csrf
                <button name="analytics" value="1" class="{{ $button }}">Zgadzam się na statystyki</button>
                <button name="analytics" value="0" class="{{ $button }}">Tylko niezbędne</button>
                <button type="button" x-show="decided" x-cloak x-on:click="open = false" aria-label="Zamknij bez zmian"
                        class="relative grid size-11 place-items-center text-[22px] leading-none text-on-dark-muted hover:text-sand">×</button>
            </form>
        </div>
    </section>
@endif
