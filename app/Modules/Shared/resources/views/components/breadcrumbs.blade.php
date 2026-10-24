@props(['trail'])
<nav class="ma-breadcrumbs" aria-label="Jesteś tutaj">
    <ol>
        @foreach ($trail->items as [$label, $url])
            <li>
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}">{{ $label }}</a>
                @else
                    <span aria-current="page">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
