{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
{!! $parcel ? 'Thank you. I’m packing.' : 'Thank you.' !!}

Order {!! $order->number !!} is paid.{!! $parcel ? ' Before your parcel leaves, I’ll send you a photo of it — I wrap every piece on its own.' : '' !!}
@if ($missing->isNotEmpty())

Someone bought the last piece just before your payment came in: {!! $missing->map(fn ($item) => $item->product_name.' ('.$item->variant_label.')')->join(', ') !!}. I’ll refund {{ $order->money($refund) }} for the missing pieces within 14 days at the latest. If you’d rather have a similar piece made to order, just reply to this e-mail.
@endif

YOUR ORDER
@include('checkout::mail.partials.items-text')

DELIVERY
{!! $shippingLabel !!}
{!! $delivery !!}
{!! $order->name !!} · {!! $order->phone !!}

@if ($contactEmail)
A question about your order? Just reply to this e-mail — it comes straight to me.
@else
A question about your order? Write to me through the shop and give your order number, {!! $order->number !!}.
@endif
@if ($contactPhone)
You can also message me on WhatsApp: {!! $contactPhone !!}.
@endif

Attached are the shop terms{!! $order->terms_version ? ' ('.$order->terms_version.')' : '' !!} and the model withdrawal form.
@if (Route::has('withdrawal.create'))
The easiest way to withdraw is online: {!! route('withdrawal.create', ['zamowienie' => $order->number]) !!}
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Confirmation of order {!! $order->number !!} placed in the MellowAura shop.
