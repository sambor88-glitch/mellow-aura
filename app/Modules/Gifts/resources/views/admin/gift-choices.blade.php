@use('App\Modules\Shared\Support\Money')
@php
    $card = 'scroll-mt-6 rounded-[4px] border border-line bg-cream px-[26px] py-7';
    $alert = 'mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text';
    $tab = 'inline-flex min-h-11 items-center rounded-full border px-4 text-[13.5px]';
    $field = 'min-h-11 min-w-0 rounded-[4px] border border-line bg-white px-3 py-2.5 text-[14px] text-ink focus:border-ink';
    $settingErrors = $errors->getBag('ustawienia');
@endphp
<x-admin::layout title="Karty wyboru" lead="Kubki „Podaruj wybór”: kupująca płaci za rozmiar i dostawę, a obdarowana sama wymyśla napis, wybiera kolor wnętrza i podaje adres. Gotowy wybór trafia do zamówień jako „W realizacji”.">
    @if ($awaiting->isNotEmpty())
        <section class="mb-8">
            <h2 class="mb-1 font-serif text-[25px]">Po terminie — Twoja decyzja</h2>
            <p class="mb-4 text-[13.5px] leading-[1.6] text-label">Nikt nie wybrał napisu na czas. Kubek bez napisu ustalasz z kupującą sama; voucher pójdzie do niej mailem jako PDF na kwotę karty.</p>
            <div class="grid gap-2.5">
                @foreach ($awaiting as $choice)
                    @include('gifts::admin.gift-choice-row', ['choice' => $choice, 'decide' => true])
                @endforeach
            </div>
        </section>
    @endif

    <form method="get" action="{{ route('admin.gift-choices.index') }}" role="search" class="mb-5 flex flex-wrap items-end gap-2.5">
        <label for="szukaj" class="grid min-w-0 flex-[1_1_280px] gap-1.5 text-[12.5px] text-label">
            Kod karty, numer zamówienia, imię albo e-mail
            <input id="szukaj" name="szukaj" type="search" value="{{ $search }}" autocomplete="off" spellcheck="false" class="{{ $field }}">
        </label>
        <button class="min-h-11 rounded-full bg-ink px-6 text-[13.5px] text-linen hover:bg-navy">Szukaj</button>
        @if ($search !== '')
            <a href="{{ route('admin.gift-choices.index') }}" class="min-h-11 px-2 py-3 text-[13px] text-lead">Pokaż wszystkie</a>
        @endif
    </form>

    <div class="mb-4 flex flex-wrap items-center gap-2.5">
        <a href="{{ route('admin.gift-choices.index') }}" @class([$tab, 'border-ink bg-ink text-linen hover:text-linen' => $filter === 'open', 'border-line-strong text-ink' => $filter !== 'open'])>Czekają na wybór</a>
        <a href="{{ route('admin.gift-choices.index', ['pokaz' => 'wszystkie']) }}" @class([$tab, 'border-ink bg-ink text-linen hover:text-linen' => $filter === 'all', 'border-line-strong text-ink' => $filter !== 'all'])>Wszystkie</a>
    </div>

    @if ($choices->isEmpty() && $search !== '')
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">Nie znalazłam takiej karty</div>
            <p class="mx-auto max-w-[48ch] text-[13.5px] text-label">
                Sprawdź kod albo wpisz coś innego: numer zamówienia, imię lub e-mail.
                Kod vouchera wygląda tak samo jak kod karty
                @if (\App\Modules\Admin\Support\PanelAccess::allows(auth()->user(), 'admin.gifts.edit'))
                    — voucher sprawdzisz w <a href="{{ route('admin.gifts.edit') }}#sprawdz-voucher">Prezentach</a>.
                @else
                    — może to voucher.
                @endif
            </p>
        </div>
    @elseif ($choices->isEmpty())
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">Jeszcze nic tu nie ma</div>
            <p class="mx-auto mb-5 max-w-[44ch] text-[13.5px] text-label">Karta pojawi się tutaj, gdy ktoś za nią zapłaci w konfiguratorze kubka — z kodem, terminem i informacją, do kogo i kiedy idzie.</p>
            <a href="{{ route('mug.index') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-full bg-ink px-6 text-[13.5px] text-linen hover:bg-navy hover:text-linen">Zobacz konfigurator kubka</a>
        </div>
    @else
        <div class="grid gap-2.5">
            @foreach ($choices as $choice)
                @include('gifts::admin.gift-choice-row', ['choice' => $choice, 'decide' => false])
            @endforeach
        </div>

        @if ($choices->hasPages())
            <div class="mt-6 flex justify-between gap-4 text-[13.5px]">
                @if ($choices->previousPageUrl())
                    <a href="{{ $choices->previousPageUrl() }}">← Nowsze</a>
                @else
                    <span></span>
                @endif
                @if ($choices->nextPageUrl())
                    <a href="{{ $choices->nextPageUrl() }}">Starsze →</a>
                @endif
            </div>
        @endif
    @endif

    <form id="ustawienia" method="post" action="{{ route('admin.gift-choices.settings') }}" novalidate class="{{ $card }} mt-10 max-w-[560px]">
        @csrf
        @method('PUT')
        <h2 class="mb-1 font-serif text-[25px]">Zasady karty</h2>
        <p class="mb-5 text-[13.5px] leading-[1.6] text-label">Dotyczą kart kupionych od teraz. Przypomnienia idą same: 14 i 3 dni przed końcem terminu.</p>
        @if ($settingErrors->any())
            <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif
        <div class="grid gap-3">
            @foreach ([
                ['gift_choice_days', 'Czas na wybór napisu', old('gift_choice_days', $days), 'dni', null],
                ['gift_choice_delivery', 'Dostawa doliczona do ceny karty', old('gift_choice_delivery', $delivery === null ? '' : rtrim(rtrim(number_format($delivery / 100, 2, ',', ''), '0'), ',')), 'zł', 'Puste pole: najtańsza płatna dostawa ze sklepu, teraz '.Money::format($deliveryGross).'.'],
            ] as [$key, $label, $value, $unit, $hint])
                <div>
                    <div class="flex items-center justify-between gap-3.5">
                        <label for="karty-{{ $key }}" class="min-w-0 flex-1 text-[14px] text-graphite">{{ $label }}</label>
                        <span class="flex items-center gap-1.5">
                            <input id="karty-{{ $key }}" name="{{ $key }}" value="{{ $value }}" inputmode="decimal"
                                   @if ($settingErrors->has($key) || $hint) aria-describedby="karty-{{ $key }}-note" @endif
                                   @if ($settingErrors->has($key)) aria-invalid="true" @endif
                                   @class([
                                       'min-h-11 w-[84px] min-w-0 rounded-[4px] border bg-white px-2.5 text-right text-[14px] text-ink focus:border-ink',
                                       'border-error' => $settingErrors->has($key),
                                       'border-line' => ! $settingErrors->has($key),
                                   ])>
                            <span class="w-[30px] text-[13px] text-label">{{ $unit }}</span>
                        </span>
                    </div>
                    @if ($settingErrors->has($key))
                        <p id="karty-{{ $key }}-note" class="mt-1.5 text-[13px] text-error">{{ $settingErrors->first($key) }}</p>
                    @elseif ($hint)
                        <p id="karty-{{ $key }}-note" class="mt-1.5 text-[12.5px] text-label">{{ $hint }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        <button class="mt-[18px] min-h-11 w-full rounded-full bg-ink p-4 text-[14px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz zasady karty</button>
    </form>
</x-admin::layout>
