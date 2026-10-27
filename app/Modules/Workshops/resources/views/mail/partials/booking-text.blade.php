WARSZTAT
{!! $workshop !!}
{!! $date->dayLabel() !!}, {!! $date->timeLabel() !!}
Liczba osób: {!! $booking->seats !!} · {!! $voucher ? 'voucher '.$voucher->code : 'zapłacone '.$order->money($order->total_gross) !!}

GDZIE
@if ($address)
{!! $address !!}
Adres dostajesz tylko w mailu — nie ma go na stronie, więc nie podawaj go dalej, proszę.
@else
Adres pracowni wyślę Ci osobnym mailem przed warsztatem.
@endif
@if ($whatToBring)

CO ZABRAĆ ZE SOBĄ
@foreach ($whatToBring as $item)
- {!! $item !!}
@endforeach
@endif
