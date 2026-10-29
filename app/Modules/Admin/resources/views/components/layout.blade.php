@props(['title', 'lead' => null])
@use('App\Modules\Admin\Menu')
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel | MellowAura</title>
    <meta name="robots" content="noindex, nofollow">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-sand-dark" x-data="{ menu: false }" x-on:keydown.escape.window="if (menu) { menu = false; $refs.menuButton.focus() }">
    {{-- On a phone the sections hide under „Menu” and the bar names the screen, so the orders come first (MA-249). --}}
    <header class="border-b border-line">
        <div class="mx-auto flex max-w-[1280px] items-center gap-x-3 px-4 py-3 md:gap-x-6 md:px-7 md:py-4">
            @auth
                <button type="button" x-ref="menuButton" x-on:click="menu = ! menu" aria-controls="panel-menu" aria-expanded="false" x-bind:aria-expanded="menu.toString()"
                        x-bind:class="menu ? 'border-ink bg-ink text-linen' : 'border-line-strong bg-cream text-ink'"
                        class="inline-flex min-h-11 flex-none items-center gap-2 rounded-full border py-0 pr-2 pl-4 text-[13.5px] md:hidden">
                    Menu
                    @if ($waiting = Menu::waiting())
                        <span aria-hidden="true" class="inline-flex h-[26px] min-w-[26px] items-center justify-center rounded-full bg-rose px-2 text-[12px] font-semibold text-ink tabular-nums">{{ $waiting }}</span>
                        <span class="sr-only">, {{ $waiting }} {{ $waiting === 1 ? 'sprawa czeka' : 'spraw czeka' }}</span>
                    @endif
                </button>
                <span class="min-w-0 flex-1 truncate font-serif text-[21px] font-light md:hidden">{{ $title }}</span>
            @endauth
            <a href="{{ auth()->check() ? route('admin.dashboard') : url('/') }}" @class(['flex-col gap-0.5 text-ink hover:text-ink', 'hidden md:flex' => auth()->check(), 'flex' => auth()->guest()])>
                <span class="font-serif text-[22px] leading-none tracking-[0.16em] uppercase">mellowaura</span>
                <span class="text-[9px] tracking-[0.3em] text-label uppercase">mój panel</span>
            </a>
            <a href="{{ url('/') }}" @class(['ml-auto text-[13px] text-lead hover:text-navy', 'hidden md:inline' => auth()->check()])>Zobacz stronę</a>
            @auth
                <form method="post" action="{{ route('admin.logout') }}" class="flex-none">
                    @csrf
                    <button class="min-h-11 min-w-11 px-1 text-[13px] text-label hover:text-ink">Wyloguj</button>
                </form>
            @endauth
        </div>
    </header>

    <div class="mx-auto max-w-[1280px] animate-ma-view px-4 pt-7 pb-24 md:px-7 md:pt-11">
        <div class="mb-[30px] flex flex-wrap items-end justify-between gap-5">
            <div>
                <div class="mb-3.5 text-[10.5px] tracking-[0.3em] text-brown uppercase">tylko dla Ciebie &middot; nie widzą tego klienci</div>
                <h1 class="font-serif text-[length:clamp(32px,4.4vw,52px)] leading-[1.04] font-light">{{ $title }}</h1>
            </div>
            @if ($lead)
                <p class="max-w-[40ch] text-[14px] text-pretty text-muted">{{ $lead }}</p>
            @endif
        </div>

        <div class="flex flex-wrap gap-x-10 gap-y-6">
            @auth
                {{-- Four groups, a number next to what waits (MA-249). On a phone Escape closes it and gives the focus back to „Menu”. --}}
                <nav id="panel-menu" aria-label="Sekcje panelu" x-bind:class="{ 'max-md:hidden': ! menu }"
                     class="flex min-w-0 flex-col gap-[22px] max-md:hidden max-md:basis-full md:flex-[0_0_220px]">
                    <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif
                       @class([
                           'flex min-h-11 items-center rounded-full border px-[18px] font-serif text-[17px] font-light',
                           'border-ink bg-ink text-linen hover:text-linen' => request()->routeIs('admin.dashboard'),
                           'border-transparent text-lead hover:border-line-strong hover:bg-sand hover:text-ink' => ! request()->routeIs('admin.dashboard'),
                       ])>Mój panel</a>
                    @foreach (Menu::groups() as $group)
                        <div>
                            <h2 class="mb-2 ml-[18px] text-[10.5px] font-medium tracking-[0.3em] text-label uppercase">{{ $group['label'] }}</h2>
                            <ul class="flex flex-col gap-1">
                                @foreach ($group['sections'] as $section)
                                    <li>
                                        <a href="{{ route($section['route']) }}" @if (Menu::isCurrent($section)) aria-current="page" @endif
                                           @class([
                                               'flex min-h-11 items-center gap-2.5 rounded-full border py-1 pr-2 pl-[18px] text-[13.5px] max-md:min-h-12 max-md:text-[15px]',
                                               'border-ink bg-ink text-linen hover:text-linen' => Menu::isCurrent($section),
                                               'border-transparent text-lead hover:border-line-strong hover:bg-sand hover:text-ink' => ! Menu::isCurrent($section),
                                           ])>{{ $section['label'] }}@if ($section['count'])<span aria-hidden="true" class="ml-auto inline-flex h-[26px] min-w-[26px] flex-none items-center justify-center rounded-full bg-rose px-2 text-[12px] font-semibold text-ink tabular-nums">{{ $section['count'] }}</span><span class="sr-only">, {{ $section['count'] }} {{ $section['count_label'] }}</span>@endif</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                    <a href="{{ url('/') }}" class="flex min-h-12 items-center border-t border-line px-[18px] text-[14px] text-lead md:hidden">Zobacz stronę →</a>
                </nav>
            @endauth
            <main class="min-w-0 flex-[1_1_600px]">
                {{ $slot }}
            </main>
        </div>
    </div>

    @if (session('panel_status'))
        <div role="status" x-data x-init="setTimeout(() => $el.remove(), 2600)"
             class="fixed bottom-[26px] left-1/2 z-120 w-max max-w-[calc(100%-32px)] -translate-x-1/2 animate-ma-up-quick rounded-full bg-ink px-6 py-3.5 text-center text-[14px] text-sand shadow-pill">{{ session('panel_status') }}</div>
    @endif
</body>
</html>
