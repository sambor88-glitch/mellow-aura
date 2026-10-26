@php
    // The wording of annex 1 to the terms (the statutory model form), with room to write by hand.
    $lines = fn (int $count) => str_repeat('<div class="line"></div>', $count);
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Wzór formularza odstąpienia od umowy</title>
    <style>
        @include('shared::pdf.fonts')

        @page { margin: 15mm 20mm 14mm; }
        body { color: #2F2620; font-family: 'Instrument Sans', sans-serif; font-size: 9.5pt; line-height: 1.4; }
        .brand { font-family: 'Newsreader', serif; font-weight: 400; font-size: 13pt; letter-spacing: 0.3em; text-transform: uppercase; }
        .brand-note { font-size: 6.5pt; letter-spacing: 0.24em; text-transform: uppercase; color: #726456; margin: 1.5mm 0 7mm; }
        h1 { font-family: 'Newsreader', serif; font-weight: 300; font-size: 20pt; line-height: 1.1; margin: 0 0 1.5mm; }
        .note { font-size: 8.5pt; color: #6B5D4F; margin: 0 0 5mm; }
        .order { border: 0.3mm solid #E2D7C7; background: #F7F2EA; padding: 2.5mm 4mm; margin: 0 0 6mm; font-size: 9pt; }
        .field { margin: 0 0 4.5mm; }
        .label { margin: 0 0 1mm; }
        .filled { font-family: 'Newsreader', serif; font-weight: 400; font-size: 11.5pt; border-bottom: 0.35mm dotted #726456; padding-bottom: 1.5mm; }
        .line { border-bottom: 0.35mm dotted #726456; height: 8mm; }
        .footnote { font-size: 8.5pt; color: #6B5D4F; margin-top: 3mm; }
        .online { margin-top: 6mm; border-top: 0.4mm solid #E2D7C7; padding-top: 3mm; font-size: 8pt; line-height: 1.5; color: #4A3E33; }
        .online a { color: #855F3D; text-decoration: none; }
    </style>
</head>
<body>
    <div class="brand">mellowaura</div>
    <div class="brand-note">ceramika · rękodzieło · kraków</div>

    <h1>Wzór formularza odstąpienia od umowy</h1>
    <p class="note">(formularz ten należy wypełnić i odesłać tylko w przypadku chęci odstąpienia od umowy)</p>

    @if ($orderNumber)
        <div class="order">Twoje zamówienie: <strong>{{ $orderNumber }}</strong>{{ $orderedAt ? ', złożone '.$orderedAt->translatedFormat('j F Y') : '' }}</div>
    @endif

    <div class="field">
        <div class="label">Adresat:</div>
        <div class="filled">{{ $addressee }}</div>
    </div>
    <div class="field">
        <div class="label">Ja/My(*) niniejszym informuję/informujemy(*) o moim/naszym odstąpieniu od umowy sprzedaży następujących towarów(*) umowy dostawy następujących towarów(*) umowy o dzieło polegającej na wykonaniu następujących towarów(*)/o świadczenie następującej usługi(*)</div>
        {!! $lines(2) !!}
    </div>
    <div class="field">
        <div class="label">Data zawarcia umowy(*)/odbioru(*)</div>
        {!! $lines(1) !!}
    </div>
    <div class="field">
        <div class="label">Imię i nazwisko konsumenta(-ów)</div>
        {!! $lines(1) !!}
    </div>
    <div class="field">
        <div class="label">Adres konsumenta(-ów)</div>
        {!! $lines(2) !!}
    </div>
    <div class="field">
        <div class="label">Podpis konsumenta(-ów) (tylko jeżeli formularz jest przesyłany w wersji papierowej)</div>
        {!! $lines(1) !!}
    </div>
    <div class="field">
        <div class="label">Data</div>
        {!! $lines(1) !!}
    </div>
    <p class="footnote">(*) Niepotrzebne skreślić.</p>

    @if ($onlineForm || $contactEmail)
        <div class="online">
            @if ($onlineForm)
                Nie musisz drukować tego formularza. Odstąpienie zgłosisz też przez formularz „Odstąp od umowy tutaj”: <a href="{{ $onlineForm }}">{{ $onlineForm }}</a> — potwierdzenie z datą i godziną przyjdzie od razu mailem.
            @endif
            @if ($contactEmail)
                Wypełniony formularz możesz też wysłać mailem na {{ $contactEmail }}.
            @endif
        </div>
    @endif
</body>
</html>
