@php
    // The buyer of a printed card reads a sentence of her own; whatever Kasia writes in the panel is for the person the card is for.
    $intro = $variant === 'buyer'
        ? new Illuminate\Support\HtmlString(e(strtr(__('gifts::mail-texts.gift_choice_reminder.intro_buyer'), ['{kod}' => $fill['kod'], '{data}' => $fill['data']])))
        : $mailTexts->html('gift_choice_reminder', 'intro', $fill);
@endphp
<x-shared::mail.layout
    title="Kubek z napisem czeka na wybór"
    :preheader="'Wybór do '.$fill['data'].'.'"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">Kubek czeka na wybór</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">{{ $intro }}</p>

    @if ($variant !== 'buyer')
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 12px;">
            <tr>
                <td style="background: #2F2620; border-radius: 999px;">
                    <a href="{{ $url }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">Wybieram napis i kolor</a>
                </td>
            </tr>
        </table>
    @endif
    <p style="margin: 0 0 22px; font-size: 13.5px; color: #726456;">Kod karty: {{ $choice->code }} · wybór do {{ $fill['data'] }}.</p>

    @if ($contactEmail)
        <p style="margin: 0 0 4px; color: #5C5043;">{{ $mailTexts->html('gift_choice_reminder', 'outro', $fill) }}</p>
    @endif
    <x-shared::mail.signature plain />

    <x-slot:footer>{{ $mailTexts->common('footer') }}</x-slot:footer>
</x-shared::mail.layout>
