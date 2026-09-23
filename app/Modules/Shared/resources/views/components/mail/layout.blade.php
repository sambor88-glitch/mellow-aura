@props(['title' => 'MellowAura', 'preheader' => ''])
{{-- E-mail clients ignore most CSS, so every style is inline and the layout is tables. Colours come from the palette in mellowaura-design. --}}
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $title }}</title>
</head>
<body style="margin: 0; padding: 0; background: #F3EDE4;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0;">{{ $preheader }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #F3EDE4;">
        <tr>
            <td align="center" style="padding: 32px 14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px;">
                    <tr>
                        <td style="padding: 0 4px 18px; font-family: Georgia, 'Times New Roman', serif; font-size: 20px; letter-spacing: 4px; text-transform: uppercase; color: #2F2620;">mellowaura</td>
                    </tr>
                    <tr>
                        <td style="background: #FCF9F4; border: 1px solid #E2D7C7; border-radius: 4px; padding: 32px 28px; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #2F2620;">
                            {{ $slot }}
                        </td>
                    </tr>
                    @isset($footer)
                        <tr>
                            <td style="padding: 18px 4px 0; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 12.5px; line-height: 1.6; color: #726456;">{{ $footer }}</td>
                        </tr>
                    @endisset
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
