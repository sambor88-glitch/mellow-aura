{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Mam Twoje zgłoszenie

{!! $mailTexts->text('batch_report_confirmed', 'intro', ['imie' => $firingRequest->name]) !!}
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

{!! $mailTexts->text('batch_report_confirmed', 'outro', ['imie' => $firingRequest->name]) !!}
@endif

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
Potwierdzenie zgłoszenia wysłane przez formularz „Zgłoś wsad” na stronie wypałów MellowAura.
