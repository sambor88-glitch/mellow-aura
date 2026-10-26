{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
Wiadomość ze strony{!! $topic ? ' · '.$topic : '' !!}
{!! $senderName !!} <{!! $senderEmail !!}>

{!! $body !!}

--
Odpisz na tego maila — trafi prosto do nadawcy.
