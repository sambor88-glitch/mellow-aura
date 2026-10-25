@props(['services', 'heading'])
@use('App\Modules\Localization\Support\Locales')
{{-- Descriptive links to the services made from the customer's own things (MA-116): the link text says where it
     leads. A service shows up once its page exists in the language of this page. --}}
@php
    $routes = ['mug' => 'mug.index', 'scarf' => 'content.scarf', 'imprint' => 'content.imprint'];
    $links = collect($services)
        ->filter(fn (string $service) => isset($routes[$service]) && Locales::has($routes[$service], Locales::current()))
        ->map(fn (string $service) => [route($routes[$service]), __('shared::services.'.$service)]);
@endphp
@if ($links->isNotEmpty())
    <nav aria-label="{{ $heading }}" {{ $attributes->merge(['class' => 'min-w-0']) }}>
        <h2 class="mb-3 text-[11.5px] tracking-[0.16em] text-label uppercase">{{ $heading }}</h2>
        <ul class="grid gap-2">
            @foreach ($links as [$url, $label])
                <li><a href="{{ $url }}" class="text-[15px] leading-[1.5] text-brown underline decoration-line-strong underline-offset-[3px] hover:text-navy hover:decoration-current">{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>
@endif
