{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
I’m sorry — it’s no longer on the shelf

Someone bought {!! $missing->map(fn ($item) => $item->product_name.($item->variant_label ? ' ('.$item->variant_label.')' : ''))->join(', ') !!} just before your payment came in, so I can’t send order {!! $order->number !!}.

{!! $mailTexts->text('order_unavailable', 'intro', ['kwota' => $order->money($refund)]) !!}

@if ($contactEmail)
{!! $mailTexts->text('order_unavailable', 'outro', ['numer' => $order->number]) !!}
@else
If you’d rather have a similar piece made to order, write to me through the shop and give your order number, {!! $order->number !!}.
@endif

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
About order {!! $order->number !!} placed in the MellowAura shop.
