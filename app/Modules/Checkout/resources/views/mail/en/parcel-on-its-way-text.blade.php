{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Your parcel is on its way

I’ve sent your order {!! $order->number !!}.{!! match ($order->shipping_method) {
    'parcel_locker' => ' InPost will text and e-mail you when it’s waiting in the parcel locker.',
    'courier' => ' The InPost courier will let you know before delivery.',
    default => '',
} !!}
@if ($order->tracking_number)

Tracking number: {!! $order->tracking_number !!}
Track your parcel with InPost: {!! $order->trackingUrl() !!}
@endif

IN THE PARCEL
@foreach ($items as $item)
- {!! $item->product_name.($item->variant_label ? ', '.$item->variant_label : '').($item->quantity > 1 ? ' × '.$item->quantity : '') !!}
@endforeach

DELIVERY
{!! $shippingLabel !!}
{!! $delivery !!}

@if ($contactEmail)
Something wrong with the parcel? Just reply to this e-mail — it comes straight to me.
@else
Something wrong with the parcel? Write to me through the shop and give your order number, {!! $order->number !!}.
@endif
@if ($contactPhone)
You can also message me on WhatsApp: {!! $contactPhone !!}.
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Dispatch notice for order {!! $order->number !!} from the MellowAura shop.
