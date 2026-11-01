{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
How is it getting on?

{!! $mailTexts->text('review_request', 'intro', ['numer' => $order->number]) !!}

If you have a minute, I’d be grateful for a few words about it on my Google profile. Reviews help others decide, and tell me what I’m doing well and what I could do better.

Write a Google review: {!! $reviewUrl !!}

An honest review means the most to me — critical ones too.

@if ($contactEmail)
{!! $mailTexts->text('review_request', 'outro', ['numer' => $order->number]) !!}
@else
Something not right with your order? Write to me through the shop and give your order number, {!! $order->number !!}.
@endif

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
A one-time request for a review of order {!! $order->number !!} from the MellowAura shop.
