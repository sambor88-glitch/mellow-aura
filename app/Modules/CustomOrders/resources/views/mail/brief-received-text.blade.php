{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Zamówienie indywidualne
{!! $customOrderRequest->name !!} <{!! $customOrderRequest->email !!}>
@if ($customOrderRequest->phone)
Telefon: {!! $customOrderRequest->phone !!}
@endif

@foreach ($customOrderRequest->summary() as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach

{!! $customOrderRequest->description !!}

--
@if (isset($customOrderRequest->summary()['Zdjęcia']))
Zdjęcia od klientki są w załącznikach.
@endif
Odpisz na tego maila — trafi prosto do niej.
Pomysł w panelu: {!! route('admin.custom-orders.index') !!}#pomysl-{!! $customOrderRequest->id !!}
