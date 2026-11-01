<x-shared::mail.layout :title="'Wsad od '.$firingRequest->name" :preheader="collect($firingRequest->summary())->join(' · ')">
    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Zgłoszenie wsadu do wypału</div>
    <h1 style="margin: 0 0 18px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 26px; line-height: 1.2; color: #2F2620;">{{ $firingRequest->name }}</h1>

    @include('firing::mail.partials.summary')

    @if ($firingRequest->message)
        <p style="margin: 0 0 22px; white-space: pre-line; color: #2F2620;">{{ $firingRequest->message }}</p>
    @endif
    <p style="margin: 0 0 6px; color: #5C5043;">E-mail: <a href="mailto:{{ $firingRequest->email }}" style="color: #855F3D;">{{ $firingRequest->email }}</a></p>
    @if ($firingRequest->phone)
        <p style="margin: 0 0 6px; color: #5C5043;">Telefon: {{ $firingRequest->phone }}</p>
    @endif
    <p style="margin: 18px 0 0; color: #5C5043;">Klient dostał tę samą wycenę mailem. Odpisz na tego maila — trafi prosto do niego. Napisz, gdzie ma przywieźć prace.</p>
    <p style="margin: 12px 0 0;"><a href="{{ route('admin.firing.requests.index') }}#zgloszenie-{{ $firingRequest->id }}" style="color: #855F3D;">Zgłoszenie w panelu →</a></p>
</x-shared::mail.layout>
