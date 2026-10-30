@php
    $when = $date->dayLabel().', '.$date->timeLabel();
    $number = '<strong class="font-medium select-all">'.e($order->number).'</strong>';
    $confirmed = $paid && ! $overbooked;
@endphp
<x-shared::layout title="Zapis na warsztat | MellowAura" :noindex="true">
    @if ($analytics)
        {{-- Once per booking: a reload of this page does not count it again. --}}
        <x-consent::analytics-event name="workshop_booking" :params="$analytics" :once="'workshop-'.$order->number" />
    @endif
    {{-- The kiln glow grows from the middle and the ✓ draws itself (mellowaura-design, „Żar potwierdzenia”). --}}
    <div class="relative overflow-hidden">
    @if ($confirmed)
        <div aria-hidden="true" class="pointer-events-none absolute top-[40%] left-1/2 -mt-[55vmax] -ml-[55vmax] size-[110vmax] animate-[auraGlow_2.4s_var(--ease-clay)_both] rounded-full bg-[radial-gradient(circle,#F2C39A_0%,#E4A58C_20%,rgb(214_163_156/.4)_38%,rgb(214_163_156/0)_62%)] blur-[30px]"></div>
    @endif
    <div class="relative mx-auto max-w-[1000px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-14 pb-24 text-center">
        <div class="pt-10 pb-5">
            @if ($confirmed)
                <svg aria-hidden="true" viewBox="0 0 108 108" class="mx-auto mb-[26px] size-[108px] fill-none stroke-ink">
                    <circle cx="54" cy="54" r="50" stroke-width="1.5" class="animate-[auraDraw_1.1s_var(--ease-clay)_.3s_forwards] [stroke-dasharray:320] [stroke-dashoffset:320]"/>
                    <path d="M34 56 l14 14 l27 -30" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-[auraDraw_.6s_var(--ease-clay)_1.1s_forwards] [stroke-dasharray:80] [stroke-dashoffset:80]"/>
                </svg>
            @endif
            <h1 class="mb-5 animate-[auraBlurIn_1.2s_var(--ease-clay)_.6s_both] font-serif text-[length:clamp(48px,7vw,104px)] leading-[.92] font-light tracking-[-0.03em] text-balance">
                @if ($confirmed)
                    Do zobaczenia w pracowni
                @elseif ($overbooked)
                    Przepraszam — termin się zapełnił
                @else
                    Czekam na potwierdzenie płatności
                @endif
            </h1>
            <p class="mx-auto mb-8 max-w-[50ch] text-[17px] leading-[1.7] text-lead">
                @if ($confirmed)
                    {{ $order->items->first()?->product_name }}, <time datetime="{{ $date->starts_at->toIso8601String() }}">{{ $when }}</time> — miejsca: {{ $booking->seats }}.
                    Zapis {!! $number !!} jest opłacony{{ $booking->voucher ? ' voucherem '.$booking->voucher->code : '' }}. Potwierdzenie przyjdzie na {{ $order->email }}.
                @elseif ($overbooked)
                    Płatność za zapis {!! $number !!} przyszła, gdy ostatnie miejsce na {{ $when }} zajęła już inna osoba.
                    Napiszę do Ciebie: zwrócę {{ $order->vouchers_gross > 0 ? 'wpłatę, a kwotę z vouchera oddam na voucher,' : 'całą wpłatę' }} albo zaproponuję inny termin.
                @else
                    Czekam, aż bank potwierdzi płatność. Zwykle trwa to kilka sekund. Miejsce na {{ $when }} czeka na Ciebie — zapis {!! $number !!}.
                    <a href="{{ route('workshops.booking.confirmation') }}" class="mt-2 block text-graphite underline underline-offset-4 hover:text-ink">Sprawdź jeszcze raz</a>
                @endif
            </p>
            @if ($confirmed && $location)
                <p class="mx-auto mb-8 max-w-[50ch] text-[15px] leading-[1.65] text-muted">Pracownia: {{ $location }}. Dokładny adres dostaniesz mailem.</p>
            @endif
            @if ($confirmed)
                {{-- „Dodaj do kalendarza” (MA-268): the file for Apple and Outlook, the link for Google. No address here. --}}
                <p class="mx-auto mb-8 flex max-w-[50ch] flex-wrap items-center justify-center gap-x-5 gap-y-2 text-[15px] text-graphite">
                    <span class="text-muted">Dodaj do kalendarza:</span>
                    <a href="{{ route('workshops.booking.calendar') }}" download class="inline-flex min-h-11 items-center underline underline-offset-4 hover:text-ink">Apple, Outlook (plik .ics)</a>
                    <a href="{{ $googleCalendar }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center underline underline-offset-4 hover:text-ink">Kalendarz Google</a>
                </p>
            @endif
            <a href="{{ route('workshops.index') }}" class="inline-flex min-h-[52px] items-center rounded-full border border-ink px-[30px] text-[14px] text-ink transition duration-300 hover:bg-ink hover:text-linen active:scale-[.97]">Wracam do warsztatów</a>
        </div>
    </div>
    </div>
</x-shared::layout>
