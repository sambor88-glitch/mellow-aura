<x-shared::mail.layout
    :title="'Zamówienie '.$order->number"
    :preheader="'Zwrócę całą wpłatę — '.$order->money($refund).' — najpóźniej w ciągu 14 dni.'"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 28px; line-height: 1.15; color: #2F2620;">Przepraszam — tego już nie ma na półce</h1>
    <p style="margin: 0 0 14px; color: #2F2620;">
        Ktoś kupił @foreach ($missing as $item){{ $item->product_name }}{{ $item->variant_label ? ' ('.$item->variant_label.')' : '' }}{{ $loop->last ? '' : ', ' }}@endforeach
        chwilę przed zaksięgowaniem Twojej płatności, więc nie wyślę zamówienia {{ $order->number }}.
    </p>
    <p style="margin: 0 0 14px; color: #2F2620;">
        {{ $mailTexts->html('order_unavailable', 'intro', ['kwota' => $order->money($refund)]) }}
    </p>
    <p style="margin: 0 0 4px; color: #5C5043;">
        @if ($contactEmail)
            {{ $mailTexts->html('order_unavailable', 'outro', ['numer' => $order->number]) }}
        @else
            Jeśli wolisz podobną sztukę na zamówienie, napisz do mnie przez stronę sklepu i podaj numer {{ $order->number }}.
        @endif
    </p>
    <x-shared::mail.signature />

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        Informacja o zamówieniu {{ $order->number }} złożonym w sklepie MellowAura.
    </x-slot:footer>
</x-shared::mail.layout>
