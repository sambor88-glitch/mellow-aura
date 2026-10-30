@props(['plain' => false])
{{-- The signature under every customer mail, with the picture Kasia put above it in „Maile” (MA-287). The text under the
     picture says who writes, so the picture is decoration with an empty alt: a client that blocks pictures loses nothing. --}}
@php
    $texts = app(\App\Modules\Shared\Mail\MailTexts::class);
    $image = $texts->signatureImage();
    $font = $plain ? 'color: #2F2620;' : "font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #855F3D;";
@endphp
@if ($image)
    <img src="{{ $image['url'] }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" alt="" style="display: block; margin: 18px 0 0; border: 0; outline: none; width: {{ $image['width'] }}px; max-width: 100%; height: auto;">
@endif
{{-- $font is one of the two styles above, never typed text. --}}
<p style="margin: {{ $image ? '6px' : '18px' }} 0 0; {!! $font !!}">{{ $texts->common('signature') }}</p>
