@use('App\Modules\Shared\Support\Money')
{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Nowe zamówienie {!! $order->number !!}

Opłacone: {{ Money::format($order->total_gross) }}, {!! $order->payment_method->label() !!}.
Odpowiedź na tego maila trafi prosto do osoby, która zamówiła.
@if ($missing->isNotEmpty())

Brakuje na półce: {!! $missing->map(fn ($item) => $item->product_name.' ('.$item->variant_label.', '.$item->missing_quantity.' szt.)')->join(', ') !!}. Ktoś zapłacił za tę sztukę chwilę wcześniej. Napisz, czy zrobisz kolejną, czy oddajesz pieniądze.
@endif

DO SPAKOWANIA
@include('checkout::mail.partials.items-text')

DOSTAWA
{!! $shippingLabel !!}
{!! $delivery !!}
{!! $order->name !!} · {!! $order->phone !!} · {!! $order->email !!}
@if ($order->invoice_nip)
Faktura na NIP {!! $order->invoice_nip !!}
@endif
@if ($order->note)
Dopisek: {!! $order->note !!}
@endif

Otwórz zamówienie w panelu: {!! route('admin.orders.show', $order) !!}
