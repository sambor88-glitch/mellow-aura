{{-- On the order's page: the date the booking is on now and its moves (MA-210). The order's line keeps the date as bought. --}}
@php($booking = $order->workshopBooking?->loadMissing(['workshopDate', 'moves']))
@if ($booking !== null && $booking->moves->isNotEmpty())
    <section id="zapis-na-warsztat" class="{{ $card }} scroll-mt-6">
        <h2 class="mb-2 font-serif text-[23px]">Zapis na warsztat</h2>
        @if ($booking->workshopDate)
            <p class="text-[14px]">
                Teraz: {{ $booking->workshopDate->dayLabel() }}, {{ $booking->workshopDate->timeLabel() }}
                @if (\App\Modules\Admin\Support\PanelAccess::allows(auth()->user(), 'admin.workshops.dates.bookings'))
                    · <a href="{{ route('admin.workshops.dates.bookings', $booking->workshopDate) }}#zapis-{{ $booking->id }}">lista zapisów</a>
                @endif
            </p>
        @endif
        <ul class="mt-2 grid gap-0.5 text-[13px] text-label tabular-nums">
            @foreach ($booking->moves as $move)
                <li>Przeniesiony {{ $move->created_at->format('j.m.Y, H:i') }}: z {{ $move->fromLabel() }} na {{ $move->toLabel() }}</li>
            @endforeach
        </ul>
        <p class="mt-2 text-[12.5px] text-muted">W pozycjach zostaje termin z dnia zakupu. Płatność się nie zmieniła.</p>
    </section>
@endif
