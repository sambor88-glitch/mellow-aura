@foreach ($items as $item)
- {!! $item->product_name !!}, {!! $item->variant_label !!}@if ($item->quantity > 1), {{ $item->quantity }} × {{ $order->money($item->unit_price_gross) }}@endif: {{ $order->money($item->total()) }}
@if ($item->custom_text)
  {!! __('checkout::mail.items.text') !!}: „{!! str_replace("\n", ' / ', $item->custom_text) !!}”
@endif
@if ($item->custom_glaze)
  {!! __('checkout::mail.items.glaze') !!}: {!! $item->custom_glaze !!}
@endif
@if ($item->accepted_deviation)
  {!! __('checkout::mail.items.deviation') !!}: {!! $item->accepted_deviation !!}
@endif
@endforeach

{!! __('checkout::mail.items.subtotal') !!}: {{ $order->money($subtotal) }}
{!! __('checkout::mail.items.delivery') !!} · {!! $shippingLabel !!}: {{ $order->shipping_gross > 0 ? $order->money($order->shipping_gross) : __('checkout::mail.items.free') }}
{!! __('checkout::mail.items.total') !!}: {{ $order->money($order->total_gross) }}
