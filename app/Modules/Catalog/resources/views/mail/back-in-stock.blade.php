<x-shared::mail.layout :title="__('catalog::mail.back_in_stock_title')" :preheader="$mailTexts->text('back_in_stock', 'subject', ['produkt' => $item])">
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 30px; line-height: 1.15; color: #2F2620;">{{ __('catalog::mail.back_in_stock_title') }}</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">{{ $mailTexts->html('back_in_stock', 'intro', ['produkt' => $item]) }}</p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 22px;">
        <tr>
            <td style="background: #2F2620; border-radius: 999px;">
                <a href="{{ $url }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">{{ __('catalog::mail.back_in_stock_button') }}</a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; color: #5C5043;">{{ $mailTexts->html('back_in_stock', 'outro', ['produkt' => $item]) }}</p>
    <p style="margin: 18px 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;">{{ $mailTexts->common('signature') }}</p>

    <x-slot:footer>
        {{ $mailTexts->common('footer') }}<br>
        {{ __('catalog::mail.back_in_stock_footer') }}
    </x-slot:footer>
</x-shared::mail.layout>
