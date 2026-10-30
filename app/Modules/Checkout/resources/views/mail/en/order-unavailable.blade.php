<x-shared::mail.layout
    :title="'Order '.$order->number"
    :preheader="'I’ll refund your full payment — '.$order->money($refund).' — within 14 days at the latest.'"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 28px; line-height: 1.15; color: #2F2620;">I’m sorry — it’s no longer on the shelf</h1>
    <p style="margin: 0 0 14px; color: #2F2620;">
        Someone bought @foreach ($missing as $item){{ $item->product_name }}{{ $item->variant_label ? ' ('.$item->variant_label.')' : '' }}{{ $loop->last ? '' : ', ' }}@endforeach
        just before your payment came in, so I can’t send order {{ $order->number }}.
    </p>
    <p style="margin: 0 0 14px; color: #2F2620;">
        {{ $mailTexts->html('order_unavailable', 'intro', ['kwota' => $order->money($refund)]) }}
    </p>
    <p style="margin: 0 0 4px; color: #5C5043;">
        @if ($contactEmail)
            {{ $mailTexts->html('order_unavailable', 'outro', ['numer' => $order->number]) }}
        @else
            If you’d rather have a similar piece made to order, write to me through the shop and give your order number, {{ $order->number }}.
        @endif
    </p>
    <x-shared::mail.signature />

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        About order {{ $order->number }} placed in the MellowAura shop.
    </x-slot:footer>
</x-shared::mail.layout>
