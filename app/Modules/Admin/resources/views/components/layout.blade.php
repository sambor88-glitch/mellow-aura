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
<body class="bg-sand-dark">
    <header class="border-b border-line">
        <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-x-6 gap-y-3 px-7 py-4">
            <a href="{{ auth()->check() ? route('admin.dashboard') : url('/') }}" class="flex flex-col gap-0.5 text-ink hover:text-ink">
                <span class="font-serif text-[22px] leading-none tracking-[0.16em] uppercase">mellowaura</span>
                <span class="text-[9px] tracking-[0.3em] text-label uppercase">mój panel</span>
            </a>
            <a href="{{ url('/') }}" class="ml-auto text-[13px] text-lead hover:text-navy">Zobacz stronę</a>
            @auth
                <form method="post" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="min-h-11 px-1 text-[13px] text-label hover:text-ink">Wyloguj</button>
                </form>
            @endauth
        </div>
    </header>

    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-11 pb-24">
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
                <nav aria-label="Sekcje panelu" class="flex min-w-0 flex-[0_1_200px] flex-col gap-2">
                    @foreach (Menu::sections() as $section)
                        <a href="{{ route($section['route']) }}" @if (request()->routeIs($section['active'])) aria-current="page" @endif
                           @class([
                               'rounded-full border px-[18px] py-[11px] text-[13.5px]',
                               'border-ink bg-ink text-linen hover:text-linen' => request()->routeIs($section['active']),
                               'border-line-strong text-lead hover:border-ink hover:bg-sand hover:text-ink' => ! request()->routeIs($section['active']),
                           ])>{{ $section['label'] }}</a>
                    @endforeach
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
