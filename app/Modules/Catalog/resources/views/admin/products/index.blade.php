@use('App\Modules\Shared\Support\Money')
<x-admin::layout title="Produkty" lead="Dodawaj produkty, zmieniaj ceny i stany. Zmiany widać na stronie od razu.">
    <div class="flex flex-wrap gap-[26px]">
        <div class="min-w-0 flex-[1_1_340px]">
            <div id="nowy-produkt" class="rounded-[4px] border border-line bg-cream px-[26px] py-7">
                <h2 class="mb-1 font-serif text-[25px]">Dodaj produkt</h2>
                <p class="mb-[22px] text-[13.5px] text-label">Kubek, miska, talerz, wazon, scrunchie, kosmetyczka, piórnik — cokolwiek zrobisz.</p>
                @include('catalog::admin.products.form', ['product' => null, 'formKey' => 'nowy-produkt'])
            </div>
        </div>

        <div class="min-w-0 flex-[1_1_420px]">
            <div class="mb-[22px] rounded-[4px] border border-line bg-cream px-[22px] py-6">
                <h2 class="mb-1 font-serif text-[23px]">Strona główna</h2>
                <p class="mb-[18px] text-[13.5px] text-label">Duże zdjęcie na górze strony ciągnie z katalogu — wybierz, który produkt tam stoi. Cena aktualizuje się sama.</p>
                <form method="post" action="{{ route('admin.products.hero') }}" novalidate class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PUT')
                    <div class="min-w-0 flex-[1_1_200px]">
                        <label for="hero-product" class="mb-1.5 block text-[13.5px] text-graphite">Produkt na dużym zdjęciu</label>
                        <select id="hero-product" name="home_hero_product" class="w-full min-w-0 rounded-[4px] border border-line bg-white px-[15px] py-[13px] text-[15px] text-ink focus:border-ink">
                            @foreach ($products->where('is_published', true) as $product)
                                <option value="{{ $product->slug }}" @selected(old('home_hero_product', $heroSlug) === $product->slug)>{{ $product->name }}</option>
                            @endforeach
                        </select>
                        @error('home_hero_product')
                            <p class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-shared::field name="home_hero_badge" id="hero-badge" label="Etykieta" :value="old('home_hero_badge', $heroBadge)" placeholder="np. nowość" class="flex-[0_1_160px]" />
                    <button class="min-h-11 rounded-full border border-line-strong px-5 py-3 text-[13.5px] text-ink hover:border-ink hover:bg-sand-dark">Zapisz</button>
                </form>
                <p class="mt-3 text-[12.5px] leading-[1.5] text-hint">Sekcja „Co teraz jest w pracowni” pokazuje cztery pierwsze widoczne produkty z listy poniżej — strzałkami ▲▼ ustawiasz kolejność. Pole „szt.” to stan magazynowy: po zejściu do zera produkt sam znika ze sklepu.</p>
            </div>

            <h2 class="mb-3.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Wszystko, co masz w ofercie</h2>
            <div class="overflow-hidden rounded-[4px] border border-line bg-cream">
                @foreach ($products as $product)
                    @php
                        $formKey = 'produkt-'.$product->id;
                        $cover = $product->getFirstMedia('images');
                        $prices = $product->variants->pluck('price_gross');
                        $soldOut = $product->variants->isNotEmpty() && $product->variants->every(fn ($variant) => $variant->stock === 0);
                        [$state, $stateClass] = match (true) {
                            ! $product->is_published => ['ukryty w sklepie', 'text-label'],
                            $soldOut => ['wyprzedany — dodaj sztuki, żeby wrócił', 'text-error'],
                            default => ['widoczny w sklepie', 'text-success'],
                        };
                        $arrow = 'grid size-11 place-items-center rounded-full border border-line text-[11px] text-muted hover:border-ink disabled:cursor-not-allowed disabled:opacity-40';
                    @endphp
                    <div id="{{ $formKey }}" class="scroll-mt-6 border-b border-sand-dark px-4 py-3.5 last:border-b-0">
                        <div class="flex flex-wrap items-center gap-3.5">
                            @if ($cover)
                                <img src="{{ $cover->getUrl() }}" alt="{{ $cover->getCustomProperty('alt') ?: $product->name }}" class="h-14 w-[46px] flex-none rounded-[3px] object-cover">
                            @else
                                <span class="grid h-14 w-[46px] flex-none place-items-center rounded-[3px] border border-dashed border-line-strong bg-sand-dark text-[11px] text-hint">brak</span>
                            @endif
                            <div class="min-w-0 flex-[1_1_150px]">
                                <div class="text-[14.5px] leading-[1.3]">{{ $product->name }}</div>
                                <div class="mt-[3px] text-[12px] {{ $stateClass }}">{{ $product->category->name }} &middot; {{ $state }}</div>
                            </div>
                            @if ($prices->isNotEmpty())
                                <div class="flex-none text-[14px] tabular-nums">{{ $prices->min() === $prices->max() ? Money::format($prices->min()) : 'od '.Money::format($prices->min()) }}</div>
                            @endif
                            <div class="flex flex-none gap-1.5">
                                <form method="post" action="{{ route('admin.products.move', $product) }}">
                                    @csrf
                                    <input type="hidden" name="kierunek" value="wyzej">
                                    <button aria-label="Wyżej: {{ $product->name }}" @disabled($loop->first) class="{{ $arrow }}">▲</button>
                                </form>
                                <form method="post" action="{{ route('admin.products.move', $product) }}">
                                    @csrf
                                    <input type="hidden" name="kierunek" value="nizej">
                                    <button aria-label="Niżej: {{ $product->name }}" @disabled($loop->last) class="{{ $arrow }}">▼</button>
                                </form>
                            </div>
                            <form method="post" action="{{ route('admin.products.toggle', $product) }}">
                                @csrf
                                <button class="min-h-11 rounded-full border border-line px-4 text-[12.5px] text-lead hover:border-ink">{{ $product->is_published ? 'Ukryj' : 'Pokaż' }}</button>
                            </form>
                        </div>
                        <details @if ($errors->hasBag($formKey)) open @endif class="group mt-1">
                            <summary class="flex min-h-11 cursor-pointer list-none items-center text-[13px] text-brown [&::-webkit-details-marker]:hidden">
                                <span class="group-open:hidden">Edytuj rozmiary, ceny i opis +</span><span class="hidden group-open:inline">Zwiń −</span>
                            </summary>
                            <div class="border-t border-dashed border-sand-dark pt-3.5">
                                @include('catalog::admin.products.form', ['product' => $product, 'formKey' => $formKey])
                            </div>
                        </details>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-admin::layout>
