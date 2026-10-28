<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('checkout::piece.card_title') }}</title>
    <style>
        @include('shared::pdf.fonts')

        {{-- A6 (105 × 148 mm) like the certificate, one side. dompdf knows no flexbox and ignores box-sizing,
             so the page is 122 mm tall plus 26 mm of padding and everything is centred text. --}}
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { color: #2F2620; font-family: 'Instrument Sans', sans-serif; font-weight: 400; }
        .page { position: relative; height: 122mm; padding: 13mm 12mm; overflow: hidden; background: #F3EDE4; text-align: center; }
        .brand { font-family: 'Newsreader', serif; font-weight: 400; font-size: 15pt; letter-spacing: 0.34em; text-transform: uppercase; line-height: 1; }
        .brand-note { font-size: 6pt; letter-spacing: 0.26em; text-transform: uppercase; color: #855F3D; margin-top: 2mm; padding-bottom: 6mm; border-bottom: 0.4mm solid #D6C8B4; }
        .eyebrow { font-size: 6.5pt; letter-spacing: 0.3em; text-transform: uppercase; color: #855F3D; margin: 6mm 0 2.5mm; }
        .work { font-family: 'Newsreader', serif; font-style: italic; font-weight: 300; font-size: 14pt; line-height: 1.2; color: #3A3027; }
        {{-- 44 mm with the quiet zone: modules of about 1.2 mm, read from arm's length. --}}
        .qr { display: block; width: 44mm; height: 44mm; margin: 6mm auto 2mm; }
        .address { font-size: 7pt; letter-spacing: 0.04em; color: #6B5D4F; }
        .scan { position: absolute; left: 12mm; top: 116mm; width: 81mm; font-size: 7.5pt; line-height: 1.4; color: #5C5043; border-top: 0.4mm solid #E2D7C7; padding-top: 3mm; }
    </style>
</head>
<body>
    <div class="page">
        <div class="brand">mellowaura</div>
        <div class="brand-note">{!! nl2br(e($brandNote), false) !!}</div>
        <div class="eyebrow">{{ __('checkout::piece.card_eyebrow') }}</div>
        <div class="work">{{ $work }}</div>
        <img class="qr" src="{{ $qr }}" alt="">
        <div class="address">{{ $address }}</div>
        <div class="scan">{{ __('checkout::piece.card_scan') }}</div>
    </div>
</body>
</html>
