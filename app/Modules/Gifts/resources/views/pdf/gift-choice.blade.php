{{-- On the voucher's paper (pdf/voucher.blade.php): dompdf knows no flexbox, so the columns are tables
     and the bands sit at fixed places on the A4 sheet. --}}
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Karta wyboru {{ $choice->code }}</title>
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
        .headline em { font-style: italic; color: #855F3D; }
        .dedication { font-family: 'Newsreader', serif; font-style: italic; font-weight: 300; font-size: 14pt; line-height: 1.45; color: #5C5043; margin-right: 14mm; }
        .fields { width: 62mm; border-left: 0.4mm solid #D6C8B4; padding-left: 12mm; font-size: 9pt; line-height: 1.4; }
        .field { margin-bottom: 6mm; }
        .label { font-size: 7pt; letter-spacing: 0.16em; text-transform: uppercase; color: #726456; margin-bottom: 1.5mm; }
        .blank { border-bottom: 0.3mm solid #C6B8A5; height: 7mm; }
        .name { font-family: 'Newsreader', serif; font-style: italic; font-weight: 300; font-size: 15pt; line-height: 1.2; color: #855F3D; }
        .footer { border-top: 0.4mm solid #D6C8B4; padding-top: 6mm; font-size: 8.5pt; line-height: 1.6; color: #4A3E33; }
        .footer strong { font-weight: 500; color: #2F2620; }
        .qr { width: 30mm; padding-right: 8mm; }
        .qr img { display: block; width: 30mm; height: 30mm; }
        .contact { text-align: right; width: 62mm; padding-left: 12mm; font-size: 8pt; color: #6B5D4F; }
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
                <td class="number">Kod karty<br><span>{{ $choice->code }}</span></td>
            </tr>
        </table>
    </div>

    <div class="band" style="top: 52mm;">
        <table>
            <tr>
                <td>
                    <div class="eyebrow">karta wyboru &middot; kubek z napisem &middot; {{ $choice->size_name }}</div>
                    <div class="headline">Kubek z napisem,<br>który <em>wymyślisz sama</em></div>
                    @if ($choice->dedication)
                        <div class="dedication">{!! nl2br(e($choice->dedication)) !!}</div>
                    @endif
                </td>
                <td class="fields">
                    @foreach (['Dla' => $choice->recipient_name, 'Od' => $choice->sender_name] as $label => $name)
                        <div class="field">
                            <div class="label">{{ $label }}</div>
                            @if ($name)
                                <div class="name">{{ $name }}</div>
                            @else
                                <div class="blank"></div>
                            @endif
                        </div>
                    @endforeach
                    @if ($choice->choose_by)
                        <div class="field">
                            <div class="label">Wybierz do</div>
                            <div>{{ $choice->choose_by->translatedFormat('j F Y') }}</div>
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="band footer" style="bottom: 18mm;">
        <table>
            <tr>
                <td class="qr"><img src="{{ $qr }}" alt=""></td>
                <td>
                    <strong>Jak wybrać:</strong> zeskanuj kod telefonem albo wejdź na <strong>{{ $address }}</strong> i wpisz kod karty.
                    Napiszesz swój tekst, wybierzesz kolor wnętrza i podasz adres. Dostawa jest już opłacona — kubek ulepię i wyślę do Ciebie.
                </td>
                @if ($contact)
                    <td class="contact">{!! collect($contact)->map(fn (string $line) => e($line))->join('<br>') !!}</td>
                @endif
            </tr>
        </table>
    </div>
</body>
</html>
