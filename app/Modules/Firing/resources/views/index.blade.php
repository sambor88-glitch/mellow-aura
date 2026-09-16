@use('App\Modules\Shared\Support\Money')
@php
    // The topic name matches the contact form's list from the panel; if it is renamed, the form simply opens without it.
    $contactUrl = route('content.contact', ['temat' => 'Wypał moich prac']);
    // The lowest price per litre comes from the panel, like every number on the page.
    $fromPerLitre = $prices->where('unit', 'litre')->min('price_gross');
    $description = 'Wypał biskwitowy i na ostro do 1240°C, wypał złota, cała półka na wyłączność.'.($fromPerLitre ? ' Cennik od '.Money::input($fromPerLitre).' zł za litr.' : '');
@endphp

<x-shared::layout title="Wypał ceramiki Kraków — cennik wypałów na zlecenie" :description="$description" :canonical="route('firing.index')">
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">wypały na zlecenie</div>
        <h1 class="mb-5 font-serif text-[length:clamp(36px,5vw,64px)] leading-[1.05] font-light tracking-[-0.02em]">Wypalę Twoje prace</h1>
        @if ($lead)
            <p class="mb-12 max-w-[56ch] text-[17px] leading-[1.7] text-pretty text-lead">{{ $lead }}</p>
        @endif

        <div class="flex flex-wrap gap-11">
            <div class="min-w-0 flex-[1_1_420px]">
                @if ($prices->isNotEmpty())
                    <table class="w-full overflow-hidden rounded-[4px] border border-divider bg-cream">
                        <thead>
                            <tr class="border-b border-divider bg-linen text-[11.5px] tracking-[0.16em] text-label uppercase">
                                <th scope="col" class="px-6 py-5 text-left font-normal">usługa</th>
                                <th scope="col" class="px-6 py-5 text-right font-normal">cena</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prices as $price)
                                <tr class="border-b border-sand-dark last:border-b-0">
                                    <td class="px-6 py-5 align-top">
                                        <div class="text-[16px]">{{ $price['label'] }}</div>
                                        @if ($price['note'])
                                            <div class="mt-[3px] text-[13.5px] text-label">{{ $price['note'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-5 text-right align-top text-[16px] whitespace-nowrap tabular-nums">{{ Money::format($price['price_gross']) }}{{ $price['unit_label'] ? ' '.$price['unit_label'] : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @if ($note)
                    <p class="mt-3.5 text-[13px] leading-[1.6] text-hint">{{ $note }}</p>
                @endif
            </div>

            <div class="min-w-[280px] flex-[0_1_320px]">
                <div class="rounded-[4px] border border-divider bg-cream px-7 py-[30px]">
                    <h2 class="mb-2.5 font-serif text-[23px]">Zgłoś wsad</h2>
                    <p class="mb-5 text-[14.5px] leading-[1.6] text-muted">Napisz, ile prac, jakie rozmiary i jaka glina — odpiszę z terminem wsadu.</p>
                    <div class="grid gap-3">
                        @if ($phone)
                            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $phone) }}" target="_blank" rel="noopener"
                               class="rounded-full bg-ink p-[15px] text-center text-[14.5px] text-linen transition duration-300 hover:bg-navy hover:text-linen active:scale-[.97]">Napisz na WhatsAppie</a>
                        @endif
                        <a href="{{ $contactUrl }}"
                           @class([
                               'rounded-full p-[15px] text-center text-[14.5px] transition duration-300 active:scale-[.98]',
                               'border border-line-strong text-ink hover:border-ink hover:bg-sand-dark hover:text-ink' => $phone,
                               'bg-ink text-linen hover:bg-navy hover:text-linen' => ! $phone,
                           ])>Napisz przez formularz</a>
                    </div>
                    @if ($phone)
                        <p class="mt-3.5 text-[12.5px] text-hint">WhatsApp: {{ $phone }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-shared::layout>
