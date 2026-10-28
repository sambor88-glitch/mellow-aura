<x-shared::mail.layout title="Link do płatności" :preheader="'Do zapłaty '.$amount.'. Link jest ważny do '.$validUntil.'.'">
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 28px; line-height: 1.15; color: #2F2620;">Można zaczynać</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        {{ $mailTexts->html('payment_link', 'intro', ['imie' => $idea->name, 'kwota' => $amount]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 22px; border-top: 1px solid #E2D7C7;">
        <tr>
            <td style="padding: 10px 12px 10px 0; border-bottom: 1px solid #E2D7C7; font-size: 13px; color: #726456; white-space: nowrap; vertical-align: top;">Co to ma być</td>
            <td style="padding: 10px 0; border-bottom: 1px solid #E2D7C7; color: #2F2620; text-align: right;">{{ $idea->kind->label() }}</td>
        </tr>
        @if ($quote)
            <tr>
                <td style="padding: 10px 12px 10px 0; border-bottom: 1px solid #E2D7C7; font-size: 13px; color: #726456; white-space: nowrap; vertical-align: top;">Wycena</td>
                <td style="padding: 10px 0; border-bottom: 1px solid #E2D7C7; color: #2F2620; text-align: right;">{{ $quote }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding: 10px 12px 10px 0; border-bottom: 1px solid #E2D7C7; font-size: 13px; color: #726456; white-space: nowrap; vertical-align: top;">Do zapłaty teraz</td>
            <td style="padding: 10px 0; border-bottom: 1px solid #E2D7C7; color: #2F2620; text-align: right; font-size: 17px;">{{ $amount }}</td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 10px;">
        <tr>
            <td style="background: #2F2620; border-radius: 999px;">
                <a href="{{ $url }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">Płacę {{ $amount }}</a>
            </td>
        </tr>
    </table>
    <p style="margin: 0 0 22px; font-size: 13px; color: #726456;">Link jest ważny do {{ $validUntil }}.</p>

    @if ($contactEmail)
        <p style="margin: 0; color: #5C5043;">{{ $mailTexts->html('payment_link', 'outro', ['imie' => $idea->name, 'kwota' => $amount]) }}</p>
    @endif
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">{{ $mailTexts->common('signature') }}</p>

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        Link do płatności za zamówienie indywidualne.
    </x-slot:footer>
</x-shared::mail.layout>
