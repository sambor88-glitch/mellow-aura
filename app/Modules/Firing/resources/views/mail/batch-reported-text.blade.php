{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Zgłoszenie wsadu do wypału
{!! $firingRequest->name !!} <{!! $firingRequest->email !!}>
@if ($firingRequest->phone)
Telefon: {!! $firingRequest->phone !!}
@endif

@foreach ($firingRequest->summary() as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
@if ($firingRequest->message)

{!! $firingRequest->message !!}
@endif

--
Klient dostał tę samą wycenę mailem. Odpisz na tego maila — trafi prosto do niego. Napisz, gdzie ma przywieźć prace.
Zgłoszenie w panelu: {!! route('admin.firing.requests.index') !!}#zgloszenie-{!! $firingRequest->id !!}
