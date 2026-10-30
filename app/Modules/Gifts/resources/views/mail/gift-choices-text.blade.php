{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
{{ $choices->count() === 1 ? 'Twoja karta wyboru' : 'Twoje karty wyboru' }}

{!! $mailTexts->text('gift_choices', 'intro', $fill, $choices->count() > 1 ? 'many' : null) !!}

@foreach ($choices as $choice)
- {!! $choice->code !!}: {!! collect(['Kubek z napisem, '.$choice->size_name, $choice->recipient_name ? 'dla: '.$choice->recipient_name : null, $choice->sender_name ? 'od: '.$choice->sender_name : null, 'wybór do '.$choice->choose_by?->translatedFormat('j F Y')])->filter()->join(', ') !!}@if ($choice->goesByEmail()). Wyślę ją na {!! $choice->recipient_email !!} {!! $choice->send_on->isToday() ? 'jeszcze dziś' : $choice->send_on->translatedFormat('j F') !!} rano.@endif

@endforeach

Osoba, która dostanie kartę, wpisze kod na stronie {!! route('gift-choice.index') !!}, wymyśli napis, wybierze kolor wnętrza i poda adres. Dostawa jest już opłacona.@if ($contactEmail) {!! $mailTexts->text('gift_choices', 'outro', $fill) !!}@endif


{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
{{ $choices->count() === 1 ? 'Karta' : 'Karty' }} do zamówienia {!! $order->number !!} w sklepie MellowAura.
