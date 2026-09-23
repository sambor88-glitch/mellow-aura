<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('checkout::certificate.title') }}</title>
    <style>
        @include('shared::pdf.fonts')

        {{-- Laid out like "Certyfikat unikatu.dc.html" on A6 (105 × 148 mm). dompdf knows no flexbox and ignores box-sizing,
             so rows are tables, a page is 122 mm tall plus 26 mm of padding, and the blocks at the foot sit at fixed heights. --}}
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { color: #2F2620; font-family: 'Instrument Sans', sans-serif; font-weight: 400; }
        table { border-collapse: collapse; width: 100%; }
        td { padding: 0; vertical-align: top; }
        .page { position: relative; height: 122mm; padding: 13mm 12mm; overflow: hidden; }
        .page + .page { page-break-before: always; }
        .front { background: #F3EDE4; }
        .back { background: #FCF9F4; }
        .head { text-align: center; padding-bottom: 6mm; border-bottom: 0.4mm solid #D6C8B4; }
        .brand { font-family: 'Newsreader', serif; font-weight: 400; font-size: 15pt; letter-spacing: 0.34em; text-transform: uppercase; line-height: 1; }
        .brand-note { font-size: 6pt; letter-spacing: 0.26em; text-transform: uppercase; color: #855F3D; margin-top: 2mm; }
        .intro { text-align: center; padding: 5mm 0 4mm; }
        .eyebrow { font-size: 6.5pt; letter-spacing: 0.3em; text-transform: uppercase; color: #855F3D; margin-bottom: 3mm; }
        .quote { font-family: 'Newsreader', serif; font-style: italic; font-weight: 300; font-size: 15pt; line-height: 1.2; color: #3A3027; }
        .fields td { border-bottom: 0.25mm dotted #C6B8A5; padding: 2mm 0 1mm; font-size: 8pt; line-height: 1.25; }
        .fields .label { width: 25mm; color: #726456; letter-spacing: 0.08em; }
        .fields .value { color: #2F2620; }
        .signature { position: absolute; left: 12mm; top: 121mm; width: 81mm; }
        .producer-block { position: absolute; left: 12mm; top: 103mm; width: 81mm; }
        .foot-block { position: absolute; left: 12mm; top: 125mm; width: 81mm; }
        .sign-line { border-bottom: 0.3mm solid #C6B8A5; height: 9mm; margin-right: 6mm; }
        .small-label { font-size: 6.5pt; letter-spacing: 0.14em; text-transform: uppercase; color: #726456; margin-top: 1.8mm; }
        .maker { width: 36mm; text-align: right; vertical-align: bottom; font-size: 7pt; line-height: 1.5; color: #6B5D4F; }
        .note { margin-bottom: 3mm; }
        .note-title { font-family: 'Newsreader', serif; font-weight: 400; font-size: 10.5pt; margin-bottom: 0.4mm; }
        .note-text { font-size: 7.5pt; line-height: 1.4; color: #5C5043; }
        .producer { font-size: 7pt; line-height: 1.4; color: #5C5043; }
        .foot { border-top: 0.4mm solid #E2D7C7; font-size: 7pt; line-height: 1.5; color: #6B5D4F; }
        .foot td { padding-top: 3mm; }
        .thanks { text-align: right; }
    </style>
</head>
<body>
    @foreach ($cards as $card)
        <div class="page front">
            <div class="head">
                <div class="brand">mellowaura</div>
                <div class="brand-note">{!! __('checkout::certificate.brand_note') !!}</div>
            </div>
            <div class="intro">
                <div class="eyebrow">{{ __('checkout::certificate.eyebrow') }}</div>
                <div class="quote">{!! __('checkout::certificate.quote') !!}</div>
            </div>
            <table class="fields">
                <tr><td class="label">{{ __('checkout::certificate.work') }}</td><td class="value">{{ $card['work'] }}@if ($card['text'])<br>„{{ $card['text'] }}”@endif</td></tr>
                <tr><td class="label">{{ __('checkout::certificate.number') }}</td><td class="value">{{ $card['number'] }}</td></tr>
                {{-- Written by hand on the card: the firing date and the glaze are known only in the studio. --}}
                @if ($card['ceramics'])
                    <tr><td class="label">{{ __('checkout::certificate.fired') }}</td><td class="value">&nbsp;</td></tr>
                    <tr><td class="label">{{ __('checkout::certificate.clay') }}</td><td class="value">&nbsp;</td></tr>
                @else
                    <tr><td class="label">{{ __('checkout::certificate.material') }}</td><td class="value">&nbsp;</td></tr>
                @endif
                <tr><td class="label">{{ __('checkout::certificate.dimensions') }}</td><td class="value">{{ $card['dimensions'] ?? '' }}&nbsp;</td></tr>
            </table>
            <table class="signature">
                <tr>
                    <td>
                        <div class="sign-line"></div>
                        <div class="small-label">{{ __('checkout::certificate.signature') }}</div>
                    </td>
                    <td class="maker">Katarzyna Samborska<br>{{ $website }}</td>
                </tr>
            </table>
        </div>

        <div class="page back">
            @if ($card['notes'])
                <div class="eyebrow" style="margin-bottom: 5mm;">{{ __('checkout::certificate.care_heading') }}</div>
                @foreach ($card['notes'] as [$title, $text])
                    <div class="note">
                        <div class="note-title">{{ $title }}</div>
                        <div class="note-text">{{ $text }}</div>
                    </div>
                @endforeach
            @endif
            {{-- The maker's name, postal address and e-mail go into the parcel with the product (GPSR art. 9). --}}
            @if ($producer->isNotEmpty())
                <div class="producer-block producer">
                    <div class="small-label" style="margin: 0 0 1mm;">{{ __('checkout::certificate.producer') }}</div>
                    @foreach ($producer as $line)
                        {{ $line }}<br>
                    @endforeach
                </div>
            @endif
            <div class="foot-block">
                <table class="foot">
                    <tr>
                        <td>@foreach ($contact as $line){{ $line }}<br>@endforeach</td>
                        <td class="thanks">{!! __('checkout::certificate.thanks') !!}</td>
                    </tr>
                </table>
            </div>
        </div>
    @endforeach
</body>
</html>
