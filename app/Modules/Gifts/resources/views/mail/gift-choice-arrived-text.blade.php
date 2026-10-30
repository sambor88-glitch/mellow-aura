{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
{!! $choice->recipient_name ? $choice->recipient_name.', kubek' : 'Kubek' !!} z napisem czeka na Ciebie

{!! $mailTexts->text('gift_choice_arrived', 'intro', $fill) !!}

@if ($choice->dedication)
„{!! $choice->dedication !!}”
@endif
@if ($choice->sender_name)
od: {!! $choice->sender_name !!}
@endif

Wybieram napis i kolor: {!! $url !!}
Kod karty: {!! $choice->code !!} · wybór do {!! $choice->choose_by?->translatedFormat('j F Y') !!}. Kartę do wydruku znajdziesz w załączniku.
@if ($contactEmail)

{!! $mailTexts->text('gift_choice_arrived', 'outro', $fill) !!}
@endif


{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
