@use('App\Modules\Shared\Support\Money')
<x-shared::mail.layout
    :title="'Zamówienie '.$order->number"
    :preheader="'Czekam na płatność '.Money::format($order->total_gross).'. Potwierdzenie przyjęcia do realizacji przyjdzie w osobnym mailu.'"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Dziękuję za zamówienie</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        Zamówienie <strong style="font-weight: 600; color: #2F2620;">{{ $order->number }}</strong> czeka na płatność.
        Gdy wpłata dotrze, dostaniesz drugi mail — potwierdzenie przyjęcia zamówienia do realizacji. Z tą chwilą zawieramy umowę.
    </p>

    @if ($bankTransfer)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
            <tr>
                <td style="background: #F7F2EA; border: 1px solid #E2D7C7; border-radius: 4px; padding: 14px 16px; font-size: 14.5px; line-height: 1.7; color: #2F2620;">
                    <div style="margin: 0 0 4px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Dane do przelewu</div>
                    Kwota: {{ Money::format($order->total_gross) }}<br>
                    Tytuł: {{ $order->number }}<br>
                    @if ($bankTransfer['account'])
                        Rachunek: {{ $bankTransfer['account'] }}<br>
                    @endif
                    @if ($bankTransfer['recipient'])
                        Odbiorca: {{ $bankTransfer['recipient'] }}<br>
                    @endif
                    <span style="color: #5C5043;">{{ $parcel ? 'Paczkę wyślę po zaksięgowaniu przelewu.' : 'PDF wyślę po zaksięgowaniu przelewu.' }}</span>
                </td>
            </tr>
        </table>
    @endif

    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Twoje zamówienie</div>
    <x-checkout::mail.items :order="$order" :items="$items" :subtotal="$subtotal" :shipping-label="$shippingLabel" />

    <div style="margin: 26px 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Dostawa i płatność</div>
    <p style="margin: 0; color: #2F2620;">{{ $shippingLabel }}</p>
    <p style="margin: 0; color: #5C5043;">{{ $delivery }}</p>
    <p style="margin: 0; color: #5C5043;">{{ $order->name }} · {{ $order->phone }}</p>
    <p style="margin: 0 0 22px; color: #5C5043;">Płatność: {{ $order->payment_method->label() }}</p>

    <p style="margin: 0; color: #5C5043;">
        @if ($contactEmail)
            Masz pytanie o zamówienie? Odpisz na tego maila — trafi prosto do mnie.
        @else
            Masz pytanie o zamówienie? Napisz do mnie przez stronę sklepu i podaj numer {{ $order->number }}.
        @endif
    </p>
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">Kasia</p>

    <x-slot:footer>
        MellowAura{{ $city ? ' · '.$city : '' }}<br>
        Podsumowanie zamówienia {{ $order->number }} złożonego w sklepie MellowAura.
    </x-slot:footer>
</x-shared::mail.layout>
