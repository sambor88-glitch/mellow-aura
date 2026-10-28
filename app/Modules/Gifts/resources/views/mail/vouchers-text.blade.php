{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
{{ $vouchers->count() === 1 ? 'Twój voucher' : 'Twoje vouchery' }}

{!! $mailTexts->text('vouchers', 'intro', ['kod' => $vouchers->pluck('code')->join(', ')], $vouchers->count() > 1 ? 'many' : null) !!}

@foreach ($vouchers as $voucher)
- {!! $voucher->code !!}: {!! collect([$voucher->orderItem?->product_name, $voucher->orderItem?->variant_label, $voucher->recipient_name ? 'dla: '.$voucher->recipient_name : null, $voucher->sender_name ? 'od: '.$voucher->sender_name : null, 'ważny do '.$voucher->valid_until->translatedFormat('j F Y')])->filter()->join(', ') !!}
@endforeach

Numer vouchera wystarczy, żeby go wykorzystać{!! $contactPhone ? ' — termin ustalisz na WhatsAppie: '.$contactPhone : '' !!}.@if ($contactEmail) {!! $mailTexts->text('vouchers', 'outro', ['kod' => $vouchers->pluck('code')->join(', ')]) !!}@endif


{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
{{ $vouchers->count() === 1 ? 'Voucher' : 'Vouchery' }} do zamówienia {!! $order->number !!} w sklepie MellowAura.
