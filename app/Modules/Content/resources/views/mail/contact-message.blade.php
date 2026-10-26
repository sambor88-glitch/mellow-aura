<x-shared::mail.layout :title="'Wiadomość od '.$senderName" :preheader="\Illuminate\Support\Str::limit($body, 90)">
    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Wiadomość ze strony{{ $topic ? ' · '.$topic : '' }}</div>
    <h1 style="margin: 0 0 18px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 26px; line-height: 1.2; color: #2F2620;">{{ $senderName }}</h1>
    <p style="margin: 0 0 22px; white-space: pre-line; color: #2F2620;">{{ $body }}</p>
    <p style="margin: 0; color: #5C5043;">Odpisz na tego maila — trafi prosto na adres <a href="mailto:{{ $senderEmail }}" style="color: #855F3D;">{{ $senderEmail }}</a>.</p>
</x-shared::mail.layout>
