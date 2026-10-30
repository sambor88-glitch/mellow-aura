{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Kubek czeka na wybór

{!! $variant === 'buyer' ? strtr(__('gifts::mail-texts.gift_choice_reminder.intro_buyer'), ['{kod}' => $fill['kod'], '{data}' => $fill['data']]) : $mailTexts->text('gift_choice_reminder', 'intro', $fill) !!}

@if ($variant !== 'buyer')
Wybieram napis i kolor: {!! $url !!}
@endif
Kod karty: {!! $choice->code !!} · wybór do {!! $fill['data'] !!}.
@if ($contactEmail)

{!! $mailTexts->text('gift_choice_reminder', 'outro', $fill) !!}
@endif


{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
