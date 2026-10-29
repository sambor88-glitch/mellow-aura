{{-- The plain-text part is never rendered as HTML, so values print raw instead of as HTML entities. --}}
{!! __('catalog::mail.back_in_stock_title') !!}

{!! $mailTexts->text('back_in_stock', 'intro', ['produkt' => $item]) !!}

{!! __('catalog::mail.back_in_stock_button') !!}: {!! $url !!}

{!! $mailTexts->text('back_in_stock', 'outro', ['produkt' => $item]) !!}

{!! $mailTexts->signature() !!}

--
{!! $mailTexts->footer() !!}
{!! __('catalog::mail.back_in_stock_footer') !!}
