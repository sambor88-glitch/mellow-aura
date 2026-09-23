@php
    // The same text as the page, set for A4. dompdf knows no grid, so the numbers hang in the left margin of each paragraph.
    $effectiveFrom = $document->effectiveFrom();
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title() }}</title>
    <style>
        @include('shared::pdf.fonts')

        @page { margin: 22mm 20mm 22mm 20mm; }
        body { color: #2F2620; font-family: 'Instrument Sans', sans-serif; font-size: 9.5pt; line-height: 1.45; }
        .brand { font-family: 'Newsreader', serif; font-weight: 400; font-size: 13pt; letter-spacing: 0.3em; text-transform: uppercase; }
        .brand-note { font-size: 6.5pt; letter-spacing: 0.24em; text-transform: uppercase; color: #726456; margin: 1.5mm 0 9mm; }
        h1 { font-family: 'Newsreader', serif; font-weight: 300; font-size: 22pt; line-height: 1.1; margin: 0 0 2.5mm; }
        .meta { font-size: 8.5pt; color: #6B5D4F; margin: 0 0 3mm; }
        .draft { border: 0.3mm dashed #C6B8A5; padding: 3mm 4mm; margin: 0 0 7mm; font-size: 8.5pt; color: #4A3E33; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7pt; color: #726456; border-top: 0.25mm solid #E2D7C7; padding-top: 2mm; }
        .footer .page:after { content: counter(page); }
        h3.sec { font-family: 'Newsreader', serif; font-weight: 400; font-size: 13pt; line-height: 1.25; margin: 7mm 0 2.5mm; page-break-after: avoid; }
        p { margin: 0 0 1.8mm; }
        p.np { padding-left: 7mm; }
        p.sp { padding-left: 13mm; }
        p.np .n, p.sp .n { display: inline-block; width: 6mm; margin-left: -6.5mm; color: #855F3D; }
        mark { background: #F1DEDA; color: #2F2620; padding: 0 0.6mm; }
        a { color: #2F2620; text-decoration: none; }
        .table-wrap { margin: 1.5mm 0 4mm; }
        table { width: 100%; border-collapse: collapse; font-size: 7.5pt; line-height: 1.35; }
        th { background: #EDE4D8; text-align: left; font-weight: 500; padding: 1.8mm 2mm; }
        td { border-top: 0.25mm solid #E2D7C7; padding: 1.8mm 2mm; vertical-align: top; }
    </style>
</head>
<body>
    <div class="footer">{{ $document->title() }} · {{ $document->versionLabel() }} · strona <span class="page"></span></div>

    <div class="brand">mellowaura</div>
    <div class="brand-note">ceramika · rękodzieło · kraków</div>

    <h1>{{ $document->title() }}</h1>
    <p class="meta">{{ ucfirst($document->versionLabel()) }} · obowiązuje {{ $effectiveFrom ? 'od '.$effectiveFrom->translatedFormat('j F Y') : 'od startu sklepu' }}</p>
    @if ($document->isDraft())
        <p class="draft">To projekt, który sprawdza jeszcze prawnik. Zaznaczone miejsca zostaną uzupełnione przed startem sklepu.</p>
    @endif

    {!! $html !!}
</body>
</html>
