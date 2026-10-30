@props(['items'])
{{-- One trail for the shop, the product and the mug (MA-294): the home page first, then [label, url] pairs; the last one, without a url, is this page. --}}
<nav aria-label="{{ __('shared::breadcrumb.label') }}" {{ $attributes->class('text-[12.5px] text-label') }}>
    <ol class="flex flex-wrap items-baseline gap-x-1.5 gap-y-1">
        @foreach ([[__('shared::breadcrumb.home'), route('home')], ...$items] as [$label, $url])
            <li class="flex min-w-0 items-baseline gap-x-1.5">
                @unless ($loop->first)
                    <span aria-hidden="true">/</span>
                @endunless
                @if ($url)
                    <a href="{{ $url }}" class="text-label hover:text-navy">{{ $label }}</a>
                @else
                    <span aria-current="page" class="text-lead">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
