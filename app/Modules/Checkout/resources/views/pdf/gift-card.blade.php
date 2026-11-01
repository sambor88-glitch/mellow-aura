<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('checkout::gift-card.title') }}</title>
    <style>
        @include('shared::pdf.fonts')

        {{-- A6 (105 × 148 mm) like the piece card, one side. dompdf knows no flexbox and ignores box-sizing,
             so the page is 122 mm tall plus 26 mm of padding, the words sit high and the brand line at the foot. --}}
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { color: #2F2620; font-family: 'Instrument Sans', sans-serif; font-weight: 400; }
        .page { position: relative; height: 122mm; padding: 13mm 12mm; overflow: hidden; background: #F3EDE4; text-align: center; }
        .eyebrow { font-size: 6.5pt; letter-spacing: 0.3em; text-transform: uppercase; color: #855F3D; margin: 8mm 0 6mm; }
        {{-- The one sentence to remember is the buyer's own: Newsreader italic, at a size that reads like a letter.
             300 characters make about eight lines of the 81 mm width. --}}
        .message { font-family: 'Newsreader', serif; font-style: italic; font-weight: 300; font-size: 13pt; line-height: 1.45; color: #3A3027; word-wrap: break-word; }
        .foot { position: absolute; left: 12mm; top: 110mm; width: 81mm; border-top: 0.4mm solid #D6C8B4; padding-top: 4mm; }
        .brand { font-family: 'Newsreader', serif; font-weight: 400; font-size: 11pt; letter-spacing: 0.34em; text-transform: uppercase; line-height: 1; }
        .brand-note { font-size: 6pt; letter-spacing: 0.26em; text-transform: uppercase; color: #855F3D; margin-top: 2mm; }
    </style>
</head>
<body>
    <div class="page">
        <div class="eyebrow">{{ __('checkout::gift-card.eyebrow') }}</div>
        {{-- Typed by the buyer: escaped, and only its line breaks turned into markup. --}}
        <div class="message">{!! nl2br(e($message), false) !!}</div>
        <div class="foot">
            <div class="brand">mellowaura</div>
            <div class="brand-note">{!! nl2br(e($brandNote), false) !!}</div>
        </div>
    </div>
</body>
</html>
