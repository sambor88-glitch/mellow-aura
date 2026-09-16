{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
{{ $vouchers->count() === 1 ? 'Twój voucher' : 'Twoje vouchery' }}

{{ $vouchers->count() === 1 ? 'Voucher jest w załączniku jako PDF' : 'Każdy voucher jest w załączniku jako osobny PDF' }} — wydrukuj go albo prześlij dalej osobie, dla której jest. W tym mailu nie ma cen, więc możesz go przekazać w całości.

@foreach ($vouchers as $voucher)
- {!! $voucher->code !!}: {!! collect([$voucher->orderItem?->product_name, $voucher->orderItem?->variant_label, $voucher->recipient_name ? 'dla: '.$voucher->recipient_name : null, $voucher->sender_name ? 'od: '.$voucher->sender_name : null, 'ważny do '.$voucher->valid_until->translatedFormat('j F Y')])->filter()->join(', ') !!}
@endforeach

Numer vouchera wystarczy, żeby go wykorzystać{!! $contactPhone ? ' — termin ustalisz na WhatsAppie: '.$contactPhone : '' !!}.@if ($contactEmail) Pytanie? Odpisz na tego maila — trafi prosto do mnie.@endif


Kasia

--
{{ $vouchers->count() === 1 ? 'Voucher' : 'Vouchery' }} do zamówienia {!! $order->number !!} w sklepie MellowAura.
