@use('App\Modules\Shared\Support\Money')
@foreach ($items as $item)
- {!! $item->product_name !!}, {!! $item->variant_label !!}@if ($item->quantity > 1), {{ $item->quantity }} × {{ Money::format($item->unit_price_gross) }}@endif: {{ Money::format($item->total()) }}
@if ($item->custom_text)
  Napis: „{!! $item->custom_text !!}”
@endif
@if ($item->custom_glaze)
  Szkliwo: {!! $item->custom_glaze !!}
@endif
@endforeach

Produkty: {{ Money::format($subtotal) }}
Dostawa · {!! $shippingLabel !!}: {{ $order->shipping_gross > 0 ? Money::format($order->shipping_gross) : 'gratis' }}
Razem: {{ Money::format($order->total_gross) }}
