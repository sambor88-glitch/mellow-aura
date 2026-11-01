<x-shared::mail.layout :title="'Pomysł od '.$customOrderRequest->name" :preheader="collect($customOrderRequest->summary())->join(' · ')">
    <div style="margin: 0 0 6px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Zamówienie indywidualne</div>
    <h1 style="margin: 0 0 18px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 26px; line-height: 1.2; color: #2F2620;">{{ $customOrderRequest->name }}</h1>

    @include('custom-orders::mail.partials.summary')

    <p style="margin: 0 0 22px; white-space: pre-line; color: #2F2620;">{{ $customOrderRequest->description }}</p>
    <p style="margin: 0 0 6px; color: #5C5043;">E-mail: <a href="mailto:{{ $customOrderRequest->email }}" style="color: #855F3D;">{{ $customOrderRequest->email }}</a></p>
    @if ($customOrderRequest->phone)
        <p style="margin: 0 0 6px; color: #5C5043;">Telefon: {{ $customOrderRequest->phone }}</p>
    @endif
    <p style="margin: 18px 0 0; color: #5C5043;">
        @if (isset($customOrderRequest->summary()['Zdjęcia']))
            Zdjęcia od klienta są w załącznikach.
        @endif
        Odpisz na tego maila — trafi prosto do niej.
    </p>
    <p style="margin: 12px 0 0;"><a href="{{ route('admin.custom-orders.index') }}#pomysl-{{ $customOrderRequest->id }}" style="color: #855F3D;">Pomysł w panelu →</a></p>
</x-shared::mail.layout>
