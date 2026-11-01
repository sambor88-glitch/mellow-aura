{{-- The frame next to the price list (Usługi → Teksty → „Ramka obok cennika”), in the colours of its service. Only what is filled in shows. --}}
@use('App\Modules\Content\Enums\Service')
@php
    $tone = match ($service) {
        Service::Scarf => ['box' => 'bg-ink text-on-dark', 'eyebrow' => 'text-gold', 'text' => 'text-[14.5px]'],
        Service::Imprint => ['box' => 'bg-navy text-navy-pale', 'eyebrow' => 'text-navy-line', 'text' => 'text-[14px] text-navy-muted'],
    };
@endphp
<div class="rounded-[20px] px-[30px] py-8 {{ $tone['box'] }}">
    @if ($card['eyebrow'])
        <div class="mb-[18px] text-[11px] tracking-[0.2em] uppercase {{ $tone['eyebrow'] }}">{{ $card['eyebrow'] }}</div>
    @endif
    @if ($card['heading'])
        <p class="mb-4 font-serif text-[21px] leading-[1.5] text-cream last:mb-0">{{ $card['heading'] }}</p>
    @endif
    @if ($card['list'])
        <ul class="mb-5 grid gap-3.5 text-[15px] leading-[1.6] text-cream last:mb-0">
            @foreach ($card['list'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    @endif
    @if ($card['text'])
        <p class="leading-[1.66] {{ $tone['text'] }}">{{ $card['text'] }}</p>
    @endif
</div>
