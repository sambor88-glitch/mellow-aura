<x-shared::mail.layout
    title="Kubek z napisem w prezencie"
    preheader="Napis i kolor wnętrza wybierasz Ty — dostawa jest już opłacona."
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">{{ $choice->recipient_name ? $choice->recipient_name.', kubek' : 'Kubek' }} z napisem czeka na Ciebie</h1>
    <p style="margin: 0 0 18px; color: #5C5043;">{{ $mailTexts->html('gift_choice_arrived', 'intro', $fill) }}</p>

    @if ($choice->dedication || $choice->sender_name)
        <div style="margin: 0 0 22px; padding: 16px 20px; background: #F3EDE4; border-radius: 16px;">
            @if ($choice->dedication)
                <p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 17px; line-height: 1.5; color: #5C5043;">{!! nl2br(e($choice->dedication)) !!}</p>
            @endif
            @if ($choice->sender_name)
                <p style="margin: {{ $choice->dedication ? '8px' : '0' }} 0 0; font-size: 14px; color: #726456;">od: {{ $choice->sender_name }}</p>
            @endif
        </div>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 12px;">
        <tr>
            <td style="background: #2F2620; border-radius: 999px;">
                <a href="{{ $url }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">Wybieram napis i kolor</a>
            </td>
        </tr>
    </table>
    <p style="margin: 0 0 22px; font-size: 13.5px; color: #726456;">Kod karty: {{ $choice->code }} · wybór do {{ $choice->choose_by?->translatedFormat('j F Y') }}. Kartę do wydruku znajdziesz w załączniku.</p>

    @if ($contactEmail)
        <p style="margin: 0 0 4px; color: #5C5043;">{{ $mailTexts->html('gift_choice_arrived', 'outro', $fill) }}</p>
    @endif
    <x-shared::mail.signature plain />

    <x-slot:footer>{{ $mailTexts->common('footer') }}</x-slot:footer>
</x-shared::mail.layout>
