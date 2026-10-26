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
Klientka dostała tę samą wycenę mailem. Odpisz na tego maila — trafi prosto do niej. Napisz, gdzie ma przywieźć prace.
