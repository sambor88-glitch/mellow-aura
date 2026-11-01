<x-shared::mail.layout
    :title="'Jak się sprawdza zamówienie '.$order->number"
    preheader="Kilka zdań na mojej wizytówce Google bardzo mi pomoże."
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Jak się u Ciebie sprawdza?</h1>
    <p style="margin: 0 0 14px; color: #5C5043;">{{ $mailTexts->html('review_request', 'intro', ['numer' => $order->number]) }}</p>
    <p style="margin: 0 0 22px; color: #5C5043;">Jeśli masz minutę, napisz kilka zdań o zakupie na mojej wizytówce Google. Opinia pomaga innym zdecydować, a mnie mówi, co robię dobrze, a co mogę poprawić.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 14px;">
        <tr>
            <td style="background: #2F2620; border-radius: 999px;">
                <a href="{{ $reviewUrl }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">Napisz opinię w Google</a>
            </td>
        </tr>
    </table>
    <p style="margin: 0 0 22px; font-size: 13.5px; color: #726456;">Szczera opinia jest dla mnie najcenniejsza — także krytyczna.</p>

    <p style="margin: 0; color: #5C5043;">
        @if ($contactEmail)
            {{ $mailTexts->html('review_request', 'outro', ['numer' => $order->number]) }}
        @else
            Coś jest nie tak z zamówieniem? Napisz do mnie przez stronę sklepu i podaj numer {{ $order->number }}.
        @endif
    </p>
    <x-shared::mail.signature />

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        Jednorazowa prośba o opinię o zamówieniu {{ $order->number }} ze sklepu MellowAura.
    </x-slot:footer>
</x-shared::mail.layout>
