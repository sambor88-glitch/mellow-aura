<x-shared::mail.layout :title="'Odpowiedź na reklamację'" :preheader="$paragraphs[2] ?? ''">
    @foreach ($paragraphs as $paragraph)
        <p style="margin: 0 0 14px; color: #2F2620;">{!! nl2br(e($paragraph)) !!}</p>
    @endforeach

    <x-slot:footer>
        Odpowiedź na reklamację wysłana e-mailem ze sklepu MellowAura. Zachowaj tego maila.
    </x-slot:footer>
</x-shared::mail.layout>
