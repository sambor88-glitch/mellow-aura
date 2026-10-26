@inject('settings', 'App\Modules\Settings\Settings')
@php
    $returnAddress = $settings->get('return_address');
    $row = 'flex flex-wrap justify-between gap-x-6 gap-y-0.5';
@endphp

<x-shared::layout title="Odstąpienie od umowy | MellowAura" :noindex="true">
    <div class="mx-auto max-w-[900px] animate-ma-view px-7 pt-14 pb-24">
        <div aria-hidden="true" class="mb-[26px] grid size-[76px] animate-ma-check place-items-center rounded-full border border-line bg-sand-dark text-[30px] text-success">✓</div>
        <h1 class="mb-4 font-serif text-[length:clamp(32px,4.4vw,52px)] leading-[1.04] font-light">Dostałam Twoje oświadczenie</h1>
        <p class="mb-8 max-w-[58ch] text-[17px] leading-[1.7] text-lead">
            Doszło {{ $withdrawal->submittedAtLabel() }}. Tę samą treść z datą i godziną wysłałam na <strong class="font-medium [overflow-wrap:anywhere]">{{ $withdrawal->email }}</strong>.
            Mail nie przyszedł w kwadrans? Zajrzyj do folderu ze spamem.
        </p>

        <section aria-labelledby="tresc" class="mb-8 rounded-[4px] border border-divider bg-cream px-[30px] py-7">
            <h2 id="tresc" class="mb-3 text-[11.5px] tracking-[0.16em] text-label uppercase">Treść oświadczenia</h2>
            <p class="mb-5 font-serif text-[20px] leading-[1.5] whitespace-pre-line [overflow-wrap:anywhere]">{{ $withdrawal->statement() }}</p>
            <dl class="grid gap-2 border-t border-divider pt-4 text-[14px]">
                <div class="{{ $row }}"><dt class="text-label">Imię i nazwisko</dt><dd>{{ $withdrawal->name }}</dd></div>
                <div class="{{ $row }}"><dt class="text-label">E-mail</dt><dd class="[overflow-wrap:anywhere]">{{ $withdrawal->email }}</dd></div>
                <div class="{{ $row }}"><dt class="text-label">Numer zamówienia</dt><dd>{{ $withdrawal->order_number }}</dd></div>
                <div class="{{ $row }}"><dt class="text-label">Zakres</dt><dd>{{ $withdrawal->scope->label() }}</dd></div>
                @if ($withdrawal->items !== null)
                    <div class="{{ $row }}"><dt class="text-label">Rzeczy</dt><dd class="whitespace-pre-line [overflow-wrap:anywhere]">{{ $withdrawal->items }}</dd></div>
                @endif
                <div class="{{ $row }}"><dt class="text-label">Wysłane</dt><dd class="tabular-nums">{{ $withdrawal->submittedAtLabel() }}</dd></div>
            </dl>
        </section>

        <h2 class="mb-3 font-serif text-[25px]">Co dalej</h2>
        <ol class="mb-10 grid max-w-[62ch] list-decimal gap-2.5 pl-5 text-[15.5px] leading-[1.7] text-lead marker:text-brown">
            <li>Rzeczy odeślij w ciągu 14 dni od dziś{{ $returnAddress ? ' na adres: '.$returnAddress : '' }}. Koszt odesłania jest po Twojej stronie, a ceramikę zapakuj tak, żeby nie stłukła się w drodze.</li>
            <li>Pieniądze zwrócę w ciągu 14 dni od dziś, tą samą metodą płatności. Mogę z tym poczekać, aż rzeczy do mnie wrócą albo dostanę dowód ich nadania.</li>
        </ol>

        <a href="{{ route('shop.index') }}" class="inline-block rounded-full border border-line-strong px-[30px] py-[15px] text-[14px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink">Wróć do sklepu</a>
    </div>
</x-shared::layout>
