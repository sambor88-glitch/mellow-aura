<x-shared::mail.layout
    :title="$vouchers->count() === 1 ? 'Voucher '.$vouchers->first()->code : 'Vouchery'"
    preheader="Voucher w PDF jest w załączniku — wydrukuj go albo wyślij dalej."
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">{{ $vouchers->count() === 1 ? 'Twój voucher' : 'Twoje vouchery' }}</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        {{ $vouchers->count() === 1 ? 'Voucher jest w załączniku jako PDF' : 'Każdy voucher jest w załączniku jako osobny PDF' }} — wydrukuj go albo prześlij
        dalej osobie, dla której jest. W tym mailu nie ma cen, więc możesz go przekazać w całości.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; margin: 0 0 22px;">
        @foreach ($vouchers as $voucher)
            <tr>
                <td style="padding: 12px 0; border-top: 1px solid #E2D7C7; vertical-align: top;">
                    <div style="font-size: 15px; color: #2F2620;">{{ $voucher->orderItem?->product_name }}</div>
                    <div style="font-size: 13px; color: #726456;">
                        {{ collect([$voucher->orderItem?->variant_label, $voucher->recipient_name ? 'dla: '.$voucher->recipient_name : null, 'ważny do '.$voucher->valid_until->translatedFormat('j F Y')])->filter()->join(' · ') }}
                    </div>
                </td>
                <td align="right" style="padding: 12px 0 12px 16px; border-top: 1px solid #E2D7C7; vertical-align: top; white-space: nowrap; font-family: Georgia, 'Times New Roman', serif; font-size: 17px; letter-spacing: 1px; color: #2F2620;">{{ $voucher->code }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin: 0 0 4px; color: #5C5043;">
        Numer vouchera wystarczy, żeby go wykorzystać.
        @if ($contactEmail)
            Pytanie? Odpisz na tego maila — trafi prosto do mnie.
        @endif
    </p>
    <p style="margin: 18px 0 0; color: #2F2620;">Kasia</p>

    <x-slot:footer>{{ $vouchers->count() === 1 ? 'Voucher' : 'Vouchery' }} do zamówienia {{ $order->number }} w sklepie MellowAura.</x-slot:footer>
</x-shared::mail.layout>
