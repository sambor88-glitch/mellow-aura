@php
    // Laid out like "Voucher warsztatowy.dc.html". dompdf knows no flexbox, so the columns are tables
    // and the bands sit at fixed places on the A4 sheet. With a workshop description the main band
    // moves up and the headline gets smaller, so the four notes fit above the footer.
    $item = $voucher->orderItem;
    $noteLabels = [
        'expect' => 'Czego się spodziewać',
        'activities' => 'Co będziecie robili',
        'takeaway' => 'Z czym wyjdziecie',
        'preparation' => 'Jak się przygotować',
    ];
    $notes = collect($noteLabels)->map(fn (string $label, string $key) => filled($notes[$key] ?? null) ? [$label, $notes[$key]] : null)->filter();
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Voucher {{ $voucher->code }}</title>
    <style>
        @include('shared::pdf.fonts')

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
        .headline.compact { font-size: 30pt; margin-bottom: 5mm; }
        .headline em { font-style: italic; color: #855F3D; }
        .dedication { font-family: 'Newsreader', serif; font-style: italic; font-weight: 300; font-size: 14pt; line-height: 1.45; color: #5C5043; margin-right: 14mm; }
        .dedication.compact { font-size: 13pt; }
        .fields { width: 62mm; border-left: 0.4mm solid #D6C8B4; padding-left: 12mm; font-size: 9pt; line-height: 1.4; }
        .field { margin-bottom: 6mm; }
        .label { font-size: 7pt; letter-spacing: 0.16em; text-transform: uppercase; color: #726456; margin-bottom: 1.5mm; }
        .blank { border-bottom: 0.3mm solid #C6B8A5; height: 7mm; }
        .name { font-family: 'Newsreader', serif; font-style: italic; font-weight: 300; font-size: 15pt; line-height: 1.2; color: #855F3D; }
        .notes { border-top: 0.4mm solid #D6C8B4; padding-top: 5mm; }
        .note { width: 25%; padding-right: 7mm; font-size: 8.5pt; line-height: 1.45; color: #4A3E33; }
        .note:last-child { padding-right: 0; }
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

    <div class="band" style="top: {{ $notes->isNotEmpty() ? '46mm' : '60mm' }};">
        <table>
            <tr>
                <td>
                    <div class="eyebrow">{{ $item?->product_name ?? 'voucher' }}</div>
                    <div @class(['headline', 'compact' => $notes->isNotEmpty()])>Dzień, w którym<br>ulepisz coś<br><em>swojego</em></div>
                    @if ($voucher->dedication)
                        <div @class(['dedication', 'compact' => $notes->isNotEmpty()])>{!! nl2br(e($voucher->dedication)) !!}</div>
                    @endif
                </td>
                <td class="fields">
                    @foreach (['Dla' => $voucher->recipient_name, 'Od' => $voucher->sender_name] as $label => $name)
                        <div class="field">
                            <div class="label">{{ $label }}</div>
                            @if ($name)
                                <div class="name">{{ $name }}</div>
                            @else
                                <div class="blank"></div>
                            @endif
                        </div>
                    @endforeach
                    <div class="field">
                        <div class="label">Ważny do</div>
                        <div>{{ $voucher->valid_until->translatedFormat('j F Y') }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    @if ($notes->isNotEmpty())
        <div class="band notes" style="top: 122mm;">
            <table>
                <tr>
                    @foreach ($notes as [$label, $text])
                        <td class="note">
                            <div class="label">{{ $label }}</div>
                            <div>{{ $text }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    <div class="band footer" style="bottom: 20mm;">
        <table>
            <tr>
                <td>
                    @if ($howToUse || $whatsApp)
                        <strong>Jak go wykorzystać:</strong> {{ $howToUse }}
                        @if ($whatsApp)
                            <strong>WhatsApp: {{ $whatsApp }}</strong>
                        @endif
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
