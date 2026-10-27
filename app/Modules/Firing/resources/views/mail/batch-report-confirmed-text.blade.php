{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Mam Twoje zgłoszenie

Dziękuję, {!! $firingRequest->name !!}. Odpiszę z potwierdzeniem terminu i miejscem, gdzie przywieziesz prace.
@if ($firingRequest->quote !== null)
Wycena jest policzona z cennika i z Twojego zgłoszenia.
@else
Kwotę podam, gdy zobaczę prace albo ich zdjęcie.
@endif

@foreach ($firingRequest->summary() as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
@if ($firingRequest->message)

TWOJA WIADOMOŚĆ
{!! $firingRequest->message !!}
@endif
@if ($note)

{!! $note !!}
@endif
@if ($contactEmail)

Chcesz coś zmienić? Odpisz na tego maila — trafi prosto do mnie.
@endif

Kasia

--
MellowAura{!! $city ? ' · '.$city : '' !!}
Potwierdzenie zgłoszenia wysłane przez formularz „Zgłoś wsad” na stronie wypałów MellowAura.
