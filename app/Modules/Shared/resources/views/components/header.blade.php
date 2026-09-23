@use('App\Modules\Localization\Support\Locales')
@php
    // A link shows up once its page exists in the language of this page, so the header never points at a missing
    // page and the English menu has no Polish-only pages (workshops are held in Polish).
    $links = [
        ['shop', 'shop.index', 'shop.*'],
        ['gifts', 'gifts.index', 'gifts.*'],
        ['workshops', 'workshops.index', 'workshops.*'],
        ['about', 'content.about', 'content.about'],
        ['journal', 'journal.index', 'journal.*'],
        ['contact', 'content.contact', 'content.contact'],
    ];
    $links = array_values(array_filter($links, fn (array $link) => Locales::has($link[1], Locales::current())));
    // The English twin of „shop.index” is „en.shop.index”, so the current-page check looks for both.
    $isCurrent = fn (string $pattern) => request()->routeIs($pattern, Locales::current().'.'.$pattern);
@endphp
{{--
    A glass pill floating over the page (mellowaura-design, „Nagłówek-pigułka”). It slides away while scrolling down
    and comes back on the way up (resources/js/aura.js). Below 920 px the links move into a full-screen menu.
--}}
<div x-data="stickyHeader" class="pointer-events-none sticky top-0 z-60 px-3 pt-3.5 print:hidden">
    <div data-aura-header x-data="{ menu: false }" x-on:keydown.escape.window="if (menu) { menu = false; $refs.menuButton.focus() }"
         class="glass pointer-events-auto mx-auto flex max-w-[1120px] items-center gap-3 rounded-full py-2 pr-2 pl-[22px] transition-[transform,opacity] duration-600 ease-clay [&.is-away]:-translate-y-[140%] [&.is-away]:opacity-0">
        <a href="{{ route('home') }}" class="font-serif text-[18px] leading-none tracking-[0.22em] whitespace-nowrap text-ink uppercase hover:text-ink">mellowaura</a>
        <nav aria-label="{{ __('shared::header.nav') }}" class="ml-auto hidden gap-1 min-[920px]:flex">
            @foreach ($links as [$label, $route, $pattern])
                <a href="{{ route($route) }}" @if ($isCurrent($pattern)) aria-current="page" @endif
                   class="rounded-full px-3.5 py-2.5 text-[13.5px] text-graphite transition-colors duration-300 hover:bg-sand-dark/90 hover:text-navy aria-[current=page]:text-ink aria-[current=page]:underline aria-[current=page]:decoration-dash aria-[current=page]:underline-offset-[6px]">{{ __('shared::header.'.$label) }}</a>
            @endforeach
        </nav>
        <div class="ml-auto flex items-center gap-2 min-[920px]:ml-0">
            <x-localization::switcher />
            @includeIf('catalog::favorites.header-link')
            @if ($links)
                <button type="button" x-ref="menuButton" x-on:click="menu = true" aria-controls="menu" x-bind:aria-expanded="menu"
                        class="fill-btn flex min-h-11 items-center rounded-full border border-line-strong px-4 text-[13.5px] text-ink [--fill:var(--color-sand-dark)] min-[920px]:hidden">{{ __('shared::header.menu') }}</button>
            @endif
            @includeIf('cart::button')
        </div>

        @if ($links)
            <nav id="menu" aria-label="{{ __('shared::header.menu') }}" x-cloak x-show="menu" x-trap.noscroll="menu" data-lenis-prevent
                 x-transition:enter="transition duration-500 ease-clay" x-transition:enter-start="opacity-0" x-transition:leave="transition duration-300" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-80 flex flex-col justify-center gap-1.5 bg-sand/72 px-[clamp(24px,8vw,80px)] pt-24 pb-10 backdrop-blur-[26px] min-[920px]:hidden">
                <button type="button" x-on:click="menu = false; $refs.menuButton.focus()"
                        class="absolute top-[22px] right-[22px] flex min-h-11 items-center rounded-full border border-line-strong px-4 text-[13.5px] text-ink">{{ __('shared::header.close') }}</button>
                @foreach ($links as $i => [$label, $route, $pattern])
                    <a href="{{ route($route) }}" @if ($isCurrent($pattern)) aria-current="page" @endif x-on:click="menu = false"
                       x-bind:class="menu ? 'translate-y-0 opacity-100 blur-none' : 'translate-y-6 opacity-0 blur-sm'"
                       style="transition-delay: {{ $i * 60 }}ms"
                       class="font-serif text-[clamp(40px,10vw,72px)] leading-[1.08] font-light text-ink transition-[transform,opacity,filter] duration-700 ease-clay hover:text-navy">{{ __('shared::header.'.$label) }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</div>
