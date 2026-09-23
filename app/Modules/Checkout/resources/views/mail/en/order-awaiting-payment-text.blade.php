{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Thank you for your order

Order {!! $order->number !!} is waiting for your payment. Once it arrives, you’ll get a second e-mail confirming I’ve accepted your order. That is when our contract is made.
@if ($bankTransfer)

BANK TRANSFER DETAILS
Amount: {{ $order->money($order->total_gross) }}
Reference: {!! $order->number !!}
@if ($bankTransfer['account'])
Account: {!! $bankTransfer['account'] !!}
@endif
@if ($bankTransfer['recipient'])
Recipient: {!! $bankTransfer['recipient'] !!}
@endif
{!! $parcel ? 'I’ll send your parcel once the transfer arrives.' : 'I’ll send the PDF once the transfer arrives.' !!}
@endif

YOUR ORDER
@include('checkout::mail.partials.items-text')

DELIVERY AND PAYMENT
{!! $shippingLabel !!}
{!! $delivery !!}
{!! $order->name !!} · {!! $order->phone !!}
Payment: {!! $order->payment_method->title() !!}

@if ($contactEmail)
A question about your order? Just reply to this e-mail — it comes straight to me.
@else
A question about your order? Write to me through the shop and give your order number, {!! $order->number !!}.
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Summary of order {!! $order->number !!} placed in the MellowAura shop.
