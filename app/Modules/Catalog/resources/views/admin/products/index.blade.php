@use('App\Modules\Shared\Support\FocalPoint')
@use('App\Modules\Shared\Support\Money')
@use('Illuminate\Support\Str')
@php
    // One row per product; only what needs attention stands out (MA-307). The states feed the filters above the list.
    $rows = $products->map(function ($product) {
        $visible = $product->variants->reject(fn ($variant) => $variant->is_hidden);
        $prices = $visible->pluck('price_gross');
        $counted = $visible->contains(fn ($variant) => $variant->stock !== null);
        $stock = $counted ? $visible->sum(fn ($variant) => (int) $variant->stock) : null;
        $soldOut = $counted && $visible->isNotEmpty() && $visible->every(fn ($variant) => $variant->stock === 0);
        $name = fn ($variant) => $variant->label ?: 'jedyny rozmiar';
        $zeros = $visible->filter(fn ($variant) => $variant->stock === 0)->map($name);
        $lasts = $visible->filter(fn ($variant) => $variant->stock === 1)->map($name);
        $missing = array_keys(array_filter(['zdjęcia' => $product->getMedia('images')->isEmpty(), 'opisu' => blank($product->description)]));
        // „3 osoby czekają na powrót”, with the sizes when there are several.
        $waitingVariants = $product->variants->where('stock_notifications_count', '>', 0);
        $waiting = $waitingVariants->sum('stock_notifications_count');
        $low = $product->is_published && ($soldOut || $zeros->isNotEmpty() || $lasts->isNotEmpty());

        return [
            'product' => $product,
            'cover' => $product->getMedia('images')->first(),
            'price' => $prices->isEmpty() ? '—' : ($prices->min() === $prices->max() ? Money::format($prices->min()) : 'od '.Money::format($prices->min())),
            'stock' => $stock === null ? '—' : $stock.' szt.',
            'counted' => $counted,
            // Kind, how many sizes, and whether it is ready for the English shop: without a euro price it stays out of it.
            'sub' => implode(' · ', array_filter([
                $product->category->name,
                $visible->count() > 1 ? $product->variant_kind?->count($visible->count()) : null,
                match (true) {
                    ! $product->hasTranslation('en') => null,
                    $product->variants->contains(fn ($variant) => $variant->price('EUR') !== null) => 'EN',
                    default => 'EN bez ceny w euro',
                },
            ])),
            'missing' => $missing,
            'soldOut' => $soldOut,
            'zeros' => $soldOut ? collect() : $zeros,
            'lasts' => $soldOut ? collect() : $lasts,
            'waiting' => $waiting === 0 ? null : trans_choice(':count osoba czeka|:count osoby czekają|:count osób czeka', $waiting, [], 'pl').' na powrót'
                .($product->variants->count() > 1 ? ' ('.$waitingVariants->map(fn ($variant) => $variant->label.': '.$variant->stock_notifications_count)->join(', ').')' : ''),
            'states' => implode(' ', array_keys(array_filter(['live' => $product->is_published, 'hidden' => ! $product->is_published, 'low' => $low, 'todo' => $missing !== []]))),
            'search' => Str::lower(Str::ascii(implode(' ', [$product->name, $product->category->name, $product->piece_name, ...$product->variants->pluck('label')]))),
        ];
    });
    $filters = collect([
        'all' => ['Wszystkie', $rows->count()],
        'live' => ['W sklepie', $rows->filter(fn ($row) => str_contains($row['states'], 'live'))->count()],
        'hidden' => ['Ukryte', $rows->filter(fn ($row) => str_contains($row['states'], 'hidden'))->count()],
        'low' => ['Kończą się', $rows->filter(fn ($row) => str_contains($row['states'], 'low'))->count()],
        'todo' => ['Do uzupełnienia', $rows->filter(fn ($row) => str_contains($row['states'], 'todo'))->count()],
    ])->filter(fn ($filter, $key) => $key === 'all' || $filter[1] > 0);
    $warn = 'relative z-[1] inline-flex min-h-6 items-center gap-1.5 text-[12.5px] before:size-1.5 before:flex-none before:rounded-full before:bg-current';
@endphp
<x-admin::layout title="Produkty" :compact="true">
    <x-slot:actions>
        <x-shared::button href="{{ route('admin.products.create') }}" size="md">+ Dodaj produkt</x-shared::button>
    </x-slot:actions>

    <div x-data="productList(@js(['reorderUrl' => route('admin.products.reorder'), 'token' => csrf_token()]))">
        <div class="mb-3.5 flex flex-wrap items-center gap-x-3 gap-y-2.5">
            <label class="flex min-w-0 flex-[1_1_240px] items-center gap-2.5 rounded-full border border-line bg-white px-4 text-label transition focus-within:border-ink focus-within:shadow-[0_0_0_4px_rgb(36_65_126/.14)] md:max-w-[380px]">
                <svg viewBox="0 0 24 24" aria-hidden="true" class="size-[18px] flex-none fill-none stroke-current stroke-[1.6]"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 20 20" stroke-linecap="round"/></svg>
                <span class="sr-only">Szukaj w produktach</span>
                <input type="search" x-model="q" placeholder="Szukaj w produktach" autocomplete="off" class="min-w-0 flex-1 bg-transparent py-[11px] text-[15px] text-ink outline-none placeholder:text-hint [&::-webkit-search-cancel-button]:appearance-none pointer-coarse:text-[16px]">
            </label>
            <div role="group" aria-label="Pokaż" class="-mx-4 flex max-w-[calc(100%+32px)] min-w-0 flex-[1_1_100%] gap-1 overflow-x-auto px-4 py-0.5 [scrollbar-width:none] md:mx-0 md:max-w-full md:flex-[0_1_auto] md:px-0.5">
                @foreach ($filters as $key => [$label, $count])
                    <button type="button" x-on:click="filter = @js($key)" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}" x-bind:aria-pressed="(filter === @js($key)).toString()"
                            class="inline-flex min-h-10 flex-none items-center gap-2 rounded-full border border-transparent py-0 pr-2 pl-3.5 text-[13px] text-lead transition-colors hover:border-line-strong hover:bg-sand hover:text-ink aria-pressed:border-ink aria-pressed:bg-ink aria-pressed:text-linen">
                        {{ $label }}
                        <span @class(['inline-flex h-6 min-w-6 items-center justify-center rounded-full px-[7px] text-[11.5px] font-semibold tabular-nums', 'bg-cream text-lead' => ! in_array($key, ['low', 'todo'], true), 'bg-alert text-alert-text' => in_array($key, ['low', 'todo'], true)])>{{ $count }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="rounded-[4px] border border-line bg-cream">
            <div aria-hidden="true" class="hidden min-h-10 grid-cols-[28px_52px_minmax(0,1fr)_100px_96px_84px] items-center gap-3.5 pr-4 pl-2 text-[10.5px] tracking-[0.14em] whitespace-nowrap text-label uppercase md:grid">
                <span></span><span></span><span>Produkt</span><span class="text-right">Cena</span><span class="text-right">Na półce</span><span class="text-center">W sklepie</span>
            </div>
            {{-- Rows move by their handle: grab ⠿ and drop anywhere (MA-307). The handle also moves with ↑ and ↓. --}}
            <div x-ref="rows">
            @foreach ($rows as $row)
                @php($product = $row['product'])
                <div id="produkt-{{ $product->id }}" data-id="{{ $product->id }}" data-states="{{ $row['states'] }}" data-search="{{ $row['search'] }}" x-show="shows($el)"
                     class="relative grid scroll-mt-24 grid-cols-[28px_52px_minmax(0,1fr)_auto] items-center gap-2.5 border-t border-divider bg-cream py-2.5 pr-2 pl-1 transition-colors hover:bg-linen has-[a.row-link:focus-visible]:outline-2 has-[a.row-link:focus-visible]:-outline-offset-2 has-[a.row-link:focus-visible]:outline-navy md:min-h-[84px] md:grid-cols-[28px_52px_minmax(0,1fr)_100px_96px_84px] md:gap-3.5 md:pr-4 md:pl-2">
                    <button type="button" data-drag x-show="sortable" x-on:keydown.arrow-up.prevent="step($el, -1)" x-on:keydown.arrow-down.prevent="step($el, 1)"
                            aria-label="Przesuń: {{ $product->name }} — złap i przeciągnij albo użyj strzałek w górę i w dół"
                            class="relative z-[1] grid h-11 w-7 cursor-grab touch-none place-items-center rounded-full text-label transition-colors hover:bg-sand hover:text-ink active:cursor-grabbing">
                        <svg viewBox="0 0 10 16" aria-hidden="true" class="h-4 w-2.5 fill-current"><circle cx="2" cy="2" r="1.5"/><circle cx="8" cy="2" r="1.5"/><circle cx="2" cy="8" r="1.5"/><circle cx="8" cy="8" r="1.5"/><circle cx="2" cy="14" r="1.5"/><circle cx="8" cy="14" r="1.5"/></svg>
                    </button>
                    <span x-show="! sortable" x-cloak aria-hidden="true"></span>
                    @if ($row['cover'])
                        <img src="{{ $row['cover']->getAvailableUrl(['thumb']) }}" alt="" width="52" height="64" loading="lazy" style="{{ FocalPoint::style($row['cover']) }}" class="h-16 w-[52px] rounded-[8px] object-cover">
                    @else
                        <span class="grid h-16 w-[52px] place-items-center rounded-[8px] border border-dashed border-line-strong bg-sand-dark text-[10.5px] text-label">brak</span>
                    @endif
                    <div class="min-w-0">
                        <a href="{{ route('admin.products.edit', $product) }}" class="row-link text-[15px] leading-[1.3] font-medium text-ink outline-none after:absolute after:inset-0 hover:text-ink">{{ $product->name }}</a>
                        <div class="mt-0.5 text-[12.5px] text-label">{{ $row['sub'] }}</div>
                        <div class="mt-0.5 text-[12.5px] text-lead tabular-nums md:hidden"><span class="font-serif text-[15.5px] font-light">{{ $row['price'] }}</span> &middot; {{ $row['stock'] }}</div>
                        @if ($row['missing'] || $row['soldOut'] || $row['zeros']->isNotEmpty() || $row['lasts']->isNotEmpty() || $row['waiting'])
                            <div class="mt-1 flex flex-wrap gap-x-3.5 gap-y-0.5">
                                @if ($row['missing'])
                                    <a href="{{ route('admin.products.edit', [$product, 'tab' => 'desc']) }}" class="{{ $warn }} text-brown hover:text-ink hover:underline">Brakuje {{ implode(' i ', $row['missing']) }}</a>
                                @endif
                                @if ($row['soldOut'])
                                    <a href="{{ route('admin.products.edit', $product) }}" class="{{ $warn }} text-error hover:underline">Wyprzedany — dodaj sztuki, żeby wrócił</a>
                                @endif
                                @if ($row['zeros']->isNotEmpty())
                                    <span class="{{ $warn }} text-error">{{ $row['zeros']->count() > 2 ? 'Brak w '.$row['zeros']->count().' wariantach' : 'Brak: '.$row['zeros']->join(', ') }}</span>
                                @endif
                                @if ($row['lasts']->isNotEmpty())
                                    <span class="{{ $warn }} text-brown">{{ $row['lasts']->count() > 2 ? 'Ostatnie sztuki w '.$row['lasts']->count().' wariantach' : 'Ostatnia sztuka: '.$row['lasts']->join(', ') }}</span>
                                @endif
                                @if ($row['waiting'])
                                    <span class="{{ $warn }} text-navy-text">{{ $row['waiting'] }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <span class="hidden text-right font-serif text-[17px] font-light whitespace-nowrap tabular-nums md:block">{{ $row['price'] }}</span>
                    <span @class(['hidden text-right text-[13px] whitespace-nowrap tabular-nums md:block', 'text-error' => $row['soldOut'], 'text-brown' => ! $row['soldOut'] && ($row['zeros']->isNotEmpty() || $row['lasts']->isNotEmpty()), 'text-label' => ! $row['counted'], 'text-lead' => $row['counted'] && ! $row['soldOut'] && $row['zeros']->isEmpty() && $row['lasts']->isEmpty()])>{{ $row['stock'] }}</span>
                    {{-- One click hides or shows the product; the page comes back with what happened (MA-307). --}}
                    <form method="post" action="{{ route('admin.products.toggle', $product) }}" class="relative z-[1] justify-self-center">
                        @csrf
                        <button role="switch" aria-checked="{{ $product->is_published ? 'true' : 'false' }}" aria-label="Widoczny w sklepie: {{ $product->name }}" class="grid h-11 w-12 place-items-center rounded-full">
                            <span aria-hidden="true" class="switch-track"></span>
                        </button>
                    </form>
                </div>
            @endforeach
            </div>
            <div x-show="empty" x-cloak class="border-t border-divider px-6 py-9 text-center">
                <p class="mb-2 font-serif text-[22px] font-light">Nic tu nie pasuje</p>
                <p class="mx-auto mb-4 max-w-[42ch] text-[13.5px] text-label">Sprawdź pisownię albo pokaż wszystkie produkty.</p>
                <x-shared::button type="button" variant="secondary" size="md" x-on:click="q = ''; filter = 'all'">Pokaż wszystkie</x-shared::button>
            </div>
        </div>

        <p class="mt-3.5 text-[12.5px] text-hint" x-text="sortable ? 'Kolejność w sklepie zmienisz, łapiąc wiersz za ⠿ i przeciągając go w dowolne miejsce. Cztery pierwsze widoczne produkty stoją na stronie głównej.' : 'Kolejność zmienisz w widoku „Wszystkie”, bez szukania.'">Kolejność w sklepie zmienisz, łapiąc wiersz za ⠿ i przeciągając go w dowolne miejsce. Cztery pierwsze widoczne produkty stoją na stronie głównej.</p>
        <p role="status" x-text="notice" x-show="notice" x-cloak x-transition.opacity
           class="fixed bottom-[calc(26px+env(safe-area-inset-bottom))] left-1/2 z-120 w-max max-w-[calc(100%-32px)] -translate-x-1/2 rounded-full bg-ink px-6 py-3.5 text-center text-[14px] text-sand shadow-pill"></p>
        <p class="mt-1.5 text-[12.5px] text-hint">Produkt na dużym zdjęciu strony głównej i narzut na ceny w euro są w <a href="{{ route('admin.products.settings') }}" class="text-brown underline decoration-line-strong underline-offset-[3px] hover:text-ink">Ustawieniach produktów</a>.</p>

        {{-- On a phone the main action waits under the thumb. --}}
        <div class="sticky bottom-[calc(16px+env(safe-area-inset-bottom))] z-40 mt-3.5 flex justify-end md:hidden">
            <x-shared::button href="{{ route('admin.products.create') }}" size="md" class="shadow-[0_18px_36px_-18px_rgb(47_38_32/.6)]">+ Dodaj</x-shared::button>
        </div>
    </div>
</x-admin::layout>
