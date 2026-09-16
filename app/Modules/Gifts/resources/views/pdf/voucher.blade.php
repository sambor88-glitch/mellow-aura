@php
    // Laid out like "Voucher warsztatowy.dc.html". dompdf knows no flexbox, so the columns are tables
    // and the three bands sit at fixed places on the A4 sheet.
    $item = $voucher->orderItem;
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Voucher {{ $voucher->code }}</title>
    <style>
        @font-face { font-family: 'Newsreader'; font-style: normal; font-weight: 300; src: url('{{ $fonts }}/Newsreader-Light.ttf') format('truetype'); }
        @font-face { font-family: 'Newsreader'; font-style: italic; font-weight: 300; src: url('{{ $fonts }}/Newsreader-LightItalic.ttf') format('truetype'); }
        @font-face { font-family: 'Newsreader'; font-style: normal; font-weight: 400; src: url('{{ $fonts }}/Newsreader-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Instrument Sans'; font-style: normal; font-weight: 400; src: url('{{ $fonts }}/InstrumentSans-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Instrument Sans'; font-style: normal; font-weight: 500; src: url('{{ $fonts }}/InstrumentSans-Medium.ttf') format('truetype'); }

        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { background: #F3EDE4; color: #2F2620; font-family: 'Instrument Sans', sans-serif; font-weight: 400; }
        table { border-collapse: collapse; width: 100%; }
        td { padding: 0; vertical-align: top; }
        .band { position: absolute; left: 26mm; right: 26mm; }
        .brand { font-family: 'Newsreader', serif; font-weight: 400; font-size: 20pt; letter-spacing: 0.3em; text-transform: uppercase; line-height: 1; }
        .brand-note { font-size: 7.5pt; letter-spacing: 0.24em; text-transform: uppercase; color: #855F3D; margin-top: 2.5mm; }
        .number { text-align: right; font-size: 8pt; line-height: 1.6; color: #6B5D4F; }
        .number span { font-family: 'Newsreader', serif; font-weight: 400; font-size: 15pt; color: #2F2620; letter-spacing: 0.06em; }
        .eyebrow { font-size: 8pt; letter-spacing: 0.3em; text-transform: uppercase; color: #855F3D; margin-bottom: 5mm; }
        .headline { font-family: 'Newsreader', serif; font-weight: 300; font-size: 34pt; line-height: 1.06; letter-spacing: -0.01em; margin-bottom: 7mm; }
        .headline em { font-style: italic; color: #855F3D; }
        .dedication { font-family: 'Newsreader', serif; font-style: italic; font-weight: 300; font-size: 14pt; line-height: 1.45; color: #5C5043; margin-right: 14mm; }
        .fields { width: 62mm; border-left: 0.4mm solid #D6C8B4; padding-left: 12mm; font-size: 9pt; line-height: 1.4; }
        .field { margin-bottom: 6mm; }
        .label { font-size: 7pt; letter-spacing: 0.16em; text-transform: uppercase; color: #726456; margin-bottom: 1.5mm; }
        .blank { border-bottom: 0.3mm solid #C6B8A5; height: 7mm; }
        .name { font-family: 'Newsreader', serif; font-style: italic; font-weight: 300; font-size: 15pt; line-height: 1.2; color: #855F3D; }
        .footer { border-top: 0.4mm solid #D6C8B4; padding-top: 7mm; font-size: 8pt; line-height: 1.6; color: #6B5D4F; }
        .footer strong { font-weight: 500; color: #2F2620; }
        .contact { text-align: right; width: 70mm; padding-left: 14mm; }
    </style>
</head>
<body>
    <div class="band" style="top: 22mm;">
        <table>
            <tr>
                <td>
                    <div class="brand">mellowaura</div>
                    <div class="brand-note">pracownia ceramiki &middot; kraków</div>
                </td>
                <td class="number">Numer vouchera<br><span>{{ $voucher->code }}</span></td>
            </tr>
        </table>
    </div>

    <div class="band" style="top: 60mm;">
        <table>
            <tr>
                <td>
                    <div class="eyebrow">{{ $item?->product_name ?? 'voucher' }}</div>
                    <div class="headline">Dzień, w którym<br>ulepisz coś<br><em>swojego</em></div>
                    @if ($voucher->dedication)
                        <div class="dedication">{!! nl2br(e($voucher->dedication)) !!}</div>
                    @endif
                </td>
                <td class="fields">
                    <div class="field">
                        <div class="label">Dla</div>
                        @if ($voucher->recipient_name)
                            <div class="name">{{ $voucher->recipient_name }}</div>
                        @else
                            <div class="blank"></div>
                        @endif
                    </div>
                    <div class="field">
                        <div class="label">Od</div>
                        <div class="blank"></div>
                    </div>
                    @if ($item?->variant_label)
                        <div class="field">
                            <div class="label">Szczegóły</div>
                            <div>{{ $item->variant_label }}</div>
                        </div>
                    @endif
                    <div class="field">
                        <div class="label">Ważny do</div>
                        <div>{{ $voucher->valid_until->translatedFormat('j F Y') }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="band footer" style="bottom: 20mm;">
        <table>
            <tr>
                <td>
                    @if ($howToUse)
                        <strong>Jak go wykorzystać:</strong> {{ $howToUse }}
                    @endif
                </td>
                @if ($contact)
                    <td class="contact">{!! collect($contact)->map(fn (string $line) => e($line))->join('<br>') !!}</td>
                @endif
            </tr>
        </table>
    </div>
</body>
</html>
