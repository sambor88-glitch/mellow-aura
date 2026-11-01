<x-shared::mail.layout
    :title="'How order '.$order->number.' is getting on'"
    preheader="A few words on my Google profile would help me a lot."
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">How is it getting on?</h1>
    <p style="margin: 0 0 14px; color: #5C5043;">{{ $mailTexts->html('review_request', 'intro', ['numer' => $order->number]) }}</p>
    <p style="margin: 0 0 22px; color: #5C5043;">If you have a minute, I’d be grateful for a few words about it on my Google profile. Reviews help others decide, and tell me what I’m doing well and what I could do better.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 14px;">
        <tr>
            <td style="background: #2F2620; border-radius: 999px;">
                <a href="{{ $reviewUrl }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">Write a Google review</a>
            </td>
        </tr>
    </table>
    <p style="margin: 0 0 22px; font-size: 13.5px; color: #726456;">An honest review means the most to me — critical ones too.</p>

    <p style="margin: 0; color: #5C5043;">
        @if ($contactEmail)
            {{ $mailTexts->html('review_request', 'outro', ['numer' => $order->number]) }}
        @else
            Something not right with your order? Write to me through the shop and give your order number, {{ $order->number }}.
        @endif
    </p>
    <x-shared::mail.signature />

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        A one-time request for a review of order {{ $order->number }} from the MellowAura shop.
    </x-slot:footer>
</x-shared::mail.layout>
