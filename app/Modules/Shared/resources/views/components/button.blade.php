{{--
    The one button of the site (MA-300, mellowaura-design „Przycisk główny”): a link with href, a <button> without.
    variant: primary (ink, fills with rose), glass (second choice next to a primary one), secondary (outline),
             light (linen on a dark section), outline-light (the second choice on a dark section),
             pay (navy, whole width — the last step of a payment).
    size: lg — 52 px, the main action of a section; md — 44 px, a smaller action next to it. pay is always 60 px.
--}}
{{-- href stays in the attributes: Blade has escaped it once already, a prop would be escaped a second time. --}}
@props(['variant' => 'primary', 'size' => 'lg', 'type' => 'submit'])
@php
    $variants = [
        'primary' => 'bg-ink text-linen [--fill:var(--color-rose)] hover:bg-rose hover:text-ink',
        'glass' => 'glass text-ink [--fill:var(--color-cream)] hover:border-ink hover:text-ink',
        'secondary' => 'border border-line-strong text-ink [--fill:var(--color-sand-dark)] hover:border-ink hover:bg-sand-dark hover:text-ink',
        'light' => 'bg-linen text-ink [--fill:var(--color-rose)] hover:bg-rose hover:text-ink',
        'outline-light' => 'border border-line-dark text-sand [--fill:transparent] hover:border-rose hover:text-rose',
        'pay' => 'w-full bg-navy text-linen [--fill:var(--color-ink)] hover:bg-ink hover:text-linen',
    ];
    $sizes = [
        'lg' => 'min-h-[52px] px-[30px] text-[14px]',
        'md' => 'min-h-11 px-6 text-[13.5px]',
    ];
    $class = implode(' ', [
        'fill-btn inline-flex items-center justify-center gap-2.5 rounded-full text-center font-medium tracking-[0.03em] transition duration-300 active:scale-[.97]',
        $variants[$variant] ?? $variants['primary'],
        $variant === 'pay' ? 'min-h-[60px] px-[30px] py-3 text-[15px]' : ($sizes[$size] ?? $sizes['lg']),
    ]);
@endphp
@if ($attributes->has('href'))
    <a {{ $attributes->class($class) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($class) }}>{{ $slot }}</button>
@endif
