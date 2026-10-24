@use('App\Modules\Shared\Navigation')
@use('Illuminate\Support\Str')
<header class="ma-header">
    <div class="ma-header__inner">
        <a href="{{ route('home') }}" class="ma-logo" @if (request()->routeIs('home')) aria-current="page" @endif>
            <span class="ma-logo__name">mellowaura</span>
            <span class="ma-logo__tagline">ceramika &middot; rękodzieło &middot; kraków</span>
        </a>
        <nav class="ma-nav" aria-label="Menu główne">
            @foreach (Navigation::header() as $link)
                <a href="{{ $link['url'] }}" @if (request()->routeIs(Str::before($link['route'], '.').'.*')) aria-current="page" @endif>{{ $link['label'] }}</a>
            @endforeach
        </nav>
        <div class="ma-header__actions">
            {{-- Cart button arrives with the Cart module (sprint 2). --}}
        </div>
    </div>
</header>
