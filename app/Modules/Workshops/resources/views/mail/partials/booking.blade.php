{{-- The workshop, the date, the seats and where: shared by the confirmation and the reminder. --}}
<div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Warsztat</div>
<p style="margin: 0; color: #2F2620;">{{ $workshop }}</p>
<p style="margin: 0; color: #5C5043;">{{ $date->dayLabel() }}, {{ $date->timeLabel() }}</p>
<p style="margin: 0 0 22px; color: #5C5043;">Liczba osób: {{ $booking->seats }} · {{ $voucher ? 'voucher '.$voucher->code : 'zapłacone '.$order->money($order->total_gross) }}</p>

<div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Gdzie</div>
@if ($address)
    <p style="margin: 0 0 4px; white-space: pre-line; color: #2F2620;">{{ $address }}</p>
    <p style="margin: 0 0 22px; font-size: 13.5px; color: #5C5043;">Adres dostajesz tylko w mailu — nie ma go na stronie, więc nie podawaj go dalej, proszę.</p>
@else
    <p style="margin: 0 0 22px; color: #5C5043;">Adres pracowni wyślę Ci osobnym mailem przed warsztatem.</p>
@endif

@if ($whatToBring)
    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Co zabrać ze sobą</div>
    <ul style="margin: 0 0 22px; padding: 0 0 0 18px; color: #2F2620;">
        @foreach ($whatToBring as $item)
            <li style="margin: 0 0 2px;">{{ $item }}</li>
        @endforeach
    </ul>
@endif
