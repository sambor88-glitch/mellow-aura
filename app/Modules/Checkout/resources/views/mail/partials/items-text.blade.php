@foreach ($items as $item)
- {!! $item->product_name !!}, {!! $item->variant_label !!}@if ($item->quantity > 1), {{ $item->quantity }} × {{ $order->money($item->unit_price_gross) }}@endif: {{ $order->money($item->total()) }}
@if ($item->custom_text)
  Napis: „{!! str_replace("\n", ' / ', $item->custom_text) !!}”
@endif
@if ($item->custom_glaze)
  Kolor wnętrza: {!! $item->custom_glaze !!}
@endif
@if ($item->accepted_deviation)
  Zaakceptowana cecha: {!! $item->accepted_deviation !!}
@endif
@endforeach

Produkty: {{ $order->money($subtotal) }}
Dostawa · {!! $shippingLabel !!}: {{ $order->shipping_gross > 0 ? $order->money($order->shipping_gross) : 'gratis' }}
Razem: {{ $order->money($order->total_gross) }}
