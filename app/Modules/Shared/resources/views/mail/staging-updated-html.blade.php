<p>Cześć Kasiu,</p>

<p>na stronie testowej pojawiło się coś nowego:</p>

<ul>
@foreach ($notes as $note)
    <li>{{ $note }}</li>
@endforeach
</ul>

<p>Zerknij, proszę, i sprawdź, czy wszystko się zgadza. Jeśli coś trzeba poprawić, odpisz po prostu w tym mailu, choćby w punktach.</p>

<p>Strona: <a href="{{ $siteUrl }}">{{ $siteUrl }}</a></p>

<p>Dzięki!@if ($signature !== '')<br>{{ $signature }}@endif</p>
