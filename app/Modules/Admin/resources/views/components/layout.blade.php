{{-- heading: false when the screen draws its own title (the product card). On a phone the bar above already names the screen,
     so the big title is there only for screen readers (MA-313). compact: on a phone the lead and the actions go too — the screen
     has its own button under the thumb. actions: a button next to the title instead of the lead. --}}
@props(['title', 'lead' => null, 'heading' => true, 'compact' => false])
@use('App\Modules\Admin\Menu')
@use('App\Modules\Admin\Support\Search\QuickActions')
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Panel | MellowAura</title>
    <meta name="robots" content="noindex, nofollow">
    {{-- Added to the home screen, the panel opens like an app, without the browser's bar (MA-328). --}}
    <link rel="manifest" href="{{ route('admin.app.manifest') }}" crossorigin="use-credentials">
    <meta name="theme-color" content="#EDE4D8">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="MellowAura">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="{{ asset('icons/panel-apple-touch.png') }}">
    <script>
        if ('serviceWorker' in navigator) {
            addEventListener('load', () => navigator.serviceWorker.register(@js(route('admin.app.worker', absolute: false)), { scope: @js(\App\Modules\Admin\Http\Controllers\PanelAppController::SCOPE) }).catch(() => {}));
        }
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-sand-dark" x-data="{ menu: false }" x-on:keydown.escape.window="if (menu) { menu = false; $refs.menuButton.focus() }">
    {{-- On a phone the sections hide under „Menu” and the bar names the screen, so the orders come first (MA-249). --}}
    {{-- The search (MA-308) lives in the header: the pill on a computer, the magnifier next to „Menu” on a phone. --}}
    @php($owner = auth()->user()?->isOwner())
    <header class="border-b border-line" @auth x-data="panelSearch(@js([
        'url' => route('admin.search'),
        'quick' => app(QuickActions::class)->all(auth()->user()),
        'account' => auth()->id(),
    ]))" @endauth>
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
                <span aria-hidden="true" class="min-w-0 flex-1 truncate font-serif text-[21px] font-light md:hidden">{{ $title }}</span>
                <button type="button" x-on:click="show()" aria-haspopup="dialog" aria-label="Szukaj w panelu"
                        class="grid size-11 flex-none place-items-center rounded-full border border-line-strong bg-cream text-ink md:hidden">
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="size-[18px] fill-none stroke-current stroke-[1.6] [stroke-linecap:round]"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 20 20"/></svg>
                </button>
            @endauth
            <a href="{{ auth()->check() ? route('admin.dashboard') : url('/') }}" @class(['flex-col gap-0.5 text-ink hover:text-ink', 'hidden md:flex' => auth()->check(), 'flex' => auth()->guest()])>
                <span class="font-serif text-[22px] leading-none tracking-[0.16em] uppercase">mellowaura</span>
                <span class="text-[9px] tracking-[0.3em] text-label uppercase">mój panel</span>
            </a>
            @auth
                <button type="button" x-on:click="show()" aria-haspopup="dialog" aria-keyshortcuts="Control+K Meta+K /"
                        class="ml-6 hidden min-h-11 max-w-[520px] min-w-0 flex-1 items-center gap-2.5 rounded-full border border-line-strong bg-cream pr-2 pl-4 text-left text-[13.5px] text-hint transition-colors duration-300 hover:border-ink hover:text-lead md:flex">
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="size-[18px] flex-none fill-none stroke-current stroke-[1.6] [stroke-linecap:round]"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 20 20"/></svg>
                    <span class="min-w-0 flex-1 truncate">{{ $owner ? 'Szukaj produktu, zamówienia, klientki, ustawienia…' : 'Szukaj zamówienia…' }}</span>
                    <span aria-hidden="true" x-text="shortcut" class="flex-none rounded-full border border-line bg-linen px-2.5 py-0.5 text-[11px] tracking-[0.04em] text-label">Ctrl K</span>
                </button>
            @endauth
            <a href="{{ url('/') }}" @class(['ml-auto text-[13px] text-lead hover:text-navy', 'hidden md:inline' => auth()->check()])>Zobacz stronę</a>
            @auth
                <form method="post" action="{{ route('admin.logout') }}" x-on:submit="forget()" class="flex-none">
                    @csrf
                    <button class="min-h-11 min-w-11 px-1 text-[13px] text-label hover:text-ink">Wyloguj</button>
                </form>
            @endauth
        </div>
        @auth
            @include('admin::search')
        @endauth
    </header>

    <div class="mx-auto max-w-[1280px] animate-ma-view px-4 pt-5 pb-24 md:px-7 md:pt-11 md:pb-16">
        @if ($heading)
            {{-- On a phone nothing shows here without a lead or actions, so it leaves no gap either. --}}
            @php($bare = $compact || (! isset($actions) && blank($lead)))
            <div @class(['mb-[30px] flex flex-wrap items-end justify-between gap-5', 'max-md:mb-5' => ! $bare, 'max-md:mb-0' => $bare])>
                <div class="max-md:sr-only">
                    <div class="mb-3.5 text-[10.5px] tracking-[0.3em] text-brown uppercase">tylko dla Ciebie &middot; nie widzą tego klienci</div>
                    <h1 class="font-serif text-[length:clamp(32px,4.4vw,52px)] leading-[1.04] font-light">{{ $title }}</h1>
                </div>
                @isset($actions)
                    <div @class(['flex flex-wrap gap-2.5', 'max-md:hidden' => $compact])>{{ $actions }}</div>
                @elseif ($lead)
                    <p @class(['max-w-[40ch] text-[14px] text-pretty text-muted max-md:max-w-none max-md:text-[13px] max-md:leading-[1.5]', 'max-md:hidden' => $compact])>{{ $lead }}</p>
                @endisset
            </div>
        @endif

        <div class="flex flex-wrap gap-x-10 gap-y-6">
            @auth
                {{-- Four groups, a number next to what waits (MA-249). On a phone Escape closes it and gives the focus back to „Menu”.
                     On a computer it stays in view while the screen scrolls and scrolls on its own when the window is low (MA-313). --}}
                <nav id="panel-menu" aria-label="Sekcje panelu" x-bind:class="{ 'max-md:hidden': ! menu }"
                     x-init="if ($el.scrollHeight > $el.clientHeight) { const current = $el.querySelector('[aria-current=page]'); if (current) $el.scrollTop = current.offsetTop - $el.clientHeight / 2 }"
                     class="flex min-w-0 flex-col gap-[22px] max-md:hidden max-md:basis-full md:sticky md:top-4 md:max-h-[calc(100dvh-2rem)] md:flex-[0_0_220px] md:gap-3.5 md:self-start md:overflow-y-auto md:overscroll-contain md:pb-1 md:[scrollbar-width:thin]">
                    <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif
                       @class([
                           'flex min-h-11 flex-none items-center rounded-full border px-[18px] font-serif text-[17px] font-light md:min-h-9',
                           'border-ink bg-ink text-linen hover:text-linen' => request()->routeIs('admin.dashboard'),
                           'border-transparent text-lead hover:border-line-strong hover:bg-sand hover:text-ink' => ! request()->routeIs('admin.dashboard'),
                       ])>Na dziś</a>
                    @foreach (Menu::groups() as $group)
                        <div>
                            <h2 class="mb-2 ml-[18px] text-[10.5px] font-medium tracking-[0.3em] text-label uppercase md:mb-1">{{ $group['label'] }}</h2>
                            <ul class="flex flex-col gap-1 md:gap-0">
                                @foreach ($group['sections'] as $section)
                                    <li>
                                        <a href="{{ route($section['route']) }}" @if (Menu::isCurrent($section)) aria-current="page" @endif
                                           @class([
                                               'flex min-h-11 items-center gap-2.5 rounded-full border py-1 pr-2 pl-[18px] text-[13.5px] max-md:min-h-12 max-md:text-[15px] md:min-h-[30px] md:py-0.5',
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
            {{-- A changed form says so and asks before its changes are lost (MA-251, resources/js/unsaved.js). --}}
            <main class="min-w-0 flex-[1_1_600px]" @auth x-data="unsavedChanges(@js([
                'marked' => 'Niezapisane zmiany w „:form”.',
                'question' => 'Masz niezapisane zmiany w „:form”. Zapisz je najpierw albo porzuć.',
                'go' => 'Wróć do „:form”',
                'drop' => 'Porzuć zmiany w „:form”',
                'screen' => $title,
            ]))" @endauth>
                @auth
                <p x-ref="unsavedStatus" role="status" class="sr-only"></p>
                <template x-ref="unsavedTemplate">
                    <div role="alert" class="mb-4 rounded-[4px] border border-alert-line bg-alert px-[18px] py-4 text-[13.5px] text-alert-text">
                        <p data-text class="mb-3"></p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-go class="min-h-11 rounded-full bg-ink px-[18px] text-[13px] text-linen hover:bg-rose hover:text-ink"></button>
                            <button type="button" data-drop class="min-h-11 rounded-full border border-alert-line bg-cream px-[18px] text-[13px] text-error hover:border-error"></button>
                        </div>
                    </div>
                </template>
                @endauth
                {{ $slot }}
            </main>
        </div>
    </div>

    @if (session('panel_status'))
        <div role="status" x-data x-init="setTimeout(() => $el.remove(), 2600)"
             class="fixed bottom-[calc(26px+env(safe-area-inset-bottom))] left-1/2 z-120 w-max max-w-[calc(100%-32px)] -translate-x-1/2 animate-ma-up-quick rounded-full bg-ink px-6 py-3.5 text-center text-[14px] text-sand shadow-pill">{{ session('panel_status') }}</div>
    @endif
</body>
</html>
