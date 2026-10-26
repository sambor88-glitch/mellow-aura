{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
{!! $summary !!}

{!! $details !!}

--
{!! config('app.url') !!}
Ten sam alert przyjdzie ponownie najwcześniej za {{ config('monitoring.repeat_after_minutes') }} min.
