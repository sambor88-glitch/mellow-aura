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

            <div id="ceny-w-euro" class="mb-[22px] rounded-[4px] border border-line bg-cream px-[22px] py-6">
                <h2 class="mb-1 font-serif text-[23px]">Ceny w euro</h2>
                <p class="mb-[18px] text-[13.5px] text-label">Dotyczy produktów z zaznaczonym „Cena w euro z kursu NBP”: cena w złotych po kursie, plus narzut, w górę do końcówki ,90.</p>
                <form method="post" action="{{ route('admin.products.euro-markup') }}" novalidate class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PUT')
                    <x-shared::field name="euro_markup_percent" id="euro-markup" label="Narzut w procentach" :value="old('euro_markup_percent', $euroMarkup)" inputmode="numeric" placeholder="np. 15" class="flex-[0_1_200px]" />
                    <button class="min-h-11 rounded-full border border-line-strong px-5 py-3 text-[13.5px] text-ink hover:border-ink hover:bg-sand-dark">Zapisz</button>
                </form>
                <p class="mt-3 text-[12.5px] leading-[1.5] text-hint">Puste pole to bez narzutu. Ceny w euro wpisane ręcznie zostają, jak są.</p>
            </div>

            <h2 class="mb-3.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Wszystko, co masz w ofercie</h2>
            <div class="overflow-hidden rounded-[4px] border border-line bg-cream">
                @foreach ($products as $product)
                    @php
                        $formKey = 'produkt-'.$product->id;
                        $photos = $product->getMedia('images');
                        $cover = $photos->first();
                        $photoErrors = collect($errors->getBag('zdjecia-'.$product->id)->all())->unique();
                        $prices = $product->variants->pluck('price_gross');
                        // Written in English is half of it: without a euro price the product stays out of the English shop.
                        $english = match (true) {
                            ! $product->hasTranslation('en') => null,
                            $product->variants->contains(fn ($variant) => $variant->price('EUR') !== null) => 'EN',
                            default => 'EN bez ceny w euro',
                        };
                        $soldOut = $product->variants->isNotEmpty() && $product->variants->every(fn ($variant) => $variant->stock === 0);
                        [$state, $stateClass] = match (true) {
                            ! $product->is_published => ['ukryty w sklepie', 'text-label'],
                            $soldOut => ['wyprzedany — dodaj sztuki, żeby wrócił', 'text-error'],
                            default => ['widoczny w sklepie', 'text-success'],
                        };
                        // „3 osoby czekają na powrót”, with the sizes when there are several.
                        $waitingVariants = $product->variants->where('stock_notifications_count', '>', 0);
                        $waiting = $waitingVariants->sum('stock_notifications_count');
                        $waitingNote = $waiting === 0 ? null : trans_choice(':count osoba czeka|:count osoby czekają|:count osób czeka', $waiting, [], 'pl').' na powrót'
                            .($product->variants->count() > 1 ? ' ('.$waitingVariants->map(fn ($variant) => $variant->label.': '.$variant->stock_notifications_count)->join(', ').')' : '');
                        $photoCount = match (true) {
                            $photos->isEmpty() => 'Zdjęcia — jeszcze żadnego',
                            $photos->count() === 1 => 'Jedno zdjęcie',
                            in_array($photos->count() % 10, [2, 3, 4], true) && ! in_array($photos->count() % 100, [12, 13, 14], true) => $photos->count().' zdjęcia',
                            default => $photos->count().' zdjęć',
                        };
                        $arrow = 'grid size-11 place-items-center rounded-full border border-line text-[11px] text-muted hover:border-ink disabled:cursor-not-allowed disabled:opacity-40';
                        // Small buttons on a photo, each with a 44 px touch area that stays inside the tile.
                        $photoButton = 'absolute grid size-6 place-items-center rounded-full text-[13px] leading-none transition-colors duration-300 after:absolute after:-inset-2.5';
                    @endphp
                    <div id="{{ $formKey }}" class="scroll-mt-6 border-b border-sand-dark px-4 py-3.5 last:border-b-0">
                        <div class="flex flex-wrap items-center gap-3.5">
                            @if ($cover)
                                <img src="{{ $cover->getAvailableUrl(['thumb']) }}" alt="{{ $cover->getCustomProperty('alt') ?: $product->name }}" width="46" height="56" style="{{ \App\Modules\Shared\Support\FocalPoint::style($cover) }}" class="h-14 w-[46px] flex-none rounded-[3px] object-cover">
                            @else
                                <span class="grid h-14 w-[46px] flex-none place-items-center rounded-[3px] border border-dashed border-line-strong bg-sand-dark text-[11px] text-hint">brak</span>
                            @endif
                            <div class="min-w-0 flex-[1_1_150px]">
                                <div class="text-[14.5px] leading-[1.3]">{{ $product->name }}</div>
                                <div class="mt-[3px] text-[12px] {{ $stateClass }}">{{ $product->category->name }} &middot; {{ $state }}@if ($english) &middot; {{ $english }} @endif</div>
                                @if ($waitingNote)
                                    <div class="mt-[3px] text-[12px] text-navy-text">{{ $waitingNote }}</div>
                                @endif
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

                        <div class="mt-3.5 border-t border-dashed border-sand-dark pt-3">
                            <h3 class="mb-[9px] text-[11px] tracking-[0.14em] text-hint uppercase">{{ $photoCount }}</h3>
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($photos as $photo)
                                    <div @class(['relative h-[90px] w-[76px] flex-none rounded-[4px] border bg-line-soft', 'border-ink' => $loop->first, 'border-line' => ! $loop->first])>
                                        <img src="{{ $photo->getAvailableUrl(['thumb']) }}" alt="{{ $photo->getCustomProperty('alt') ?: 'Zdjęcie '.$loop->iteration }}" loading="lazy" width="76" height="90" style="{{ \App\Modules\Shared\Support\FocalPoint::style($photo) }}" class="size-full rounded-[3px] object-cover">
                                        <form method="post" action="{{ route('admin.products.photos.destroy', [$product, $photo]) }}" x-data x-on:submit="confirm('Usunąć to zdjęcie? Nie da się tego cofnąć.') || $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <button aria-label="Usuń zdjęcie {{ $loop->iteration }}: {{ $product->name }}" class="{{ $photoButton }} top-[3px] right-[3px] bg-ink/75 text-linen hover:bg-error">×</button>
                                        </form>
                                        @unless ($loop->first)
                                            <form method="post" action="{{ route('admin.products.photos.move', [$product, $photo]) }}">
                                                @csrf
                                                <input type="hidden" name="kierunek" value="lewo">
                                                <button aria-label="Zdjęcie {{ $loop->iteration }} w lewo: {{ $product->name }}" class="{{ $photoButton }} bottom-[3px] left-[3px] bg-cream/85 text-ink hover:bg-cream">‹</button>
                                            </form>
                                        @endunless
                                        @unless ($loop->last)
                                            <form method="post" action="{{ route('admin.products.photos.move', [$product, $photo]) }}">
                                                @csrf
                                                <input type="hidden" name="kierunek" value="prawo">
                                                <button aria-label="Zdjęcie {{ $loop->iteration }} w prawo: {{ $product->name }}" class="{{ $photoButton }} right-[3px] bottom-[3px] bg-cream/85 text-ink hover:bg-cream">›</button>
                                            </form>
                                        @endunless
                                    </div>
                                @endforeach

                                <form method="post" action="{{ route('admin.products.photos.store', $product) }}" enctype="multipart/form-data"
                                      x-data="photoPicker(@js($photoLimits))" x-on:pageshow.window="sending = false" class="contents">
                                    @csrf
                                    <label x-bind:class="sending && 'pointer-events-none opacity-60'"
                                           class="relative grid h-[90px] w-[76px] flex-none cursor-pointer place-items-center rounded-[4px] border border-dashed border-line-strong bg-linen text-[20px] text-gold transition-colors duration-300 hover:border-ink hover:bg-sand-dark has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                        <span aria-hidden="true" x-text="sending ? '…' : '+'">+</span>
                                        <span class="sr-only">Dodaj zdjęcia: {{ $product->name }}</span>
                                        <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple x-on:change="send($el)" class="sr-only">
                                    </label>
                                    <noscript><button class="min-h-11 rounded-full border border-line px-4 text-[12.5px] text-lead hover:border-ink">Wyślij wybrane</button></noscript>
                                    <p class="min-w-0 flex-[1_1_160px] text-[12px] leading-[1.5] text-hint">Pierwsze zdjęcie to okładka w sklepie. Strzałkami ‹ › zmieniasz kolejność.</p>
                                    <p role="status" x-text="sending ? 'Dodaję zdjęcia — chwilę to potrwa' : ''" class="basis-full text-[13px] text-lead empty:hidden"></p>
                                    <p role="alert" x-text="error" class="basis-full text-[13px] text-error empty:hidden"></p>
                                    @foreach ($photoErrors as $message)
                                        <p class="basis-full text-[13px] text-error">{{ $message }}</p>
                                    @endforeach
                                </form>
                            </div>
                        </div>

                        {{-- „Zobacz u siebie” on the product page (MA-310): only with a model here. --}}
                        @php
                            $arModel = $product->arModel();
                            $modelErrors = collect($errors->getBag('model-'.$product->id)->all())->unique();
                        @endphp
                        <div class="mt-3.5 border-t border-dashed border-sand-dark pt-3">
                            <h3 class="mb-[9px] text-[11px] tracking-[0.14em] text-hint uppercase">{{ $arModel ? 'Model 3D — klientki zobaczą go u siebie' : 'Model 3D — jeszcze żadnego' }}</h3>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($arModel)
                                    <span class="min-w-0 text-[13px] text-lead break-all">{{ $arModel->file_name }}</span>
                                    <span class="text-[12px] text-hint tabular-nums">{{ $arModel->human_readable_size }}</span>
                                @endif
                                <form method="post" action="{{ route('admin.products.model.store', $product) }}" enctype="multipart/form-data" x-data="{ sending: false }" x-on:pageshow.window="sending = false" class="contents">
                                    @csrf
                                    <label x-bind:class="sending && 'pointer-events-none opacity-60'"
                                           class="inline-flex min-h-11 cursor-pointer items-center rounded-full border border-line px-4 text-[12.5px] text-lead hover:border-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                        <span x-text="sending ? 'Wysyłam model…' : @js($arModel ? 'Podmień model' : 'Dodaj model (GLB)')">{{ $arModel ? 'Podmień model' : 'Dodaj model (GLB)' }}</span>
                                        <span class="sr-only">: {{ $product->name }}</span>
                                        <input type="file" name="model" accept=".glb,model/gltf-binary" x-on:change="if ($el.files.length) { sending = true; $el.form.requestSubmit() }" class="sr-only">
                                    </label>
                                    <noscript><button class="min-h-11 rounded-full border border-line px-4 text-[12.5px] text-lead hover:border-ink">Wyślij model</button></noscript>
                                </form>
                                @if ($arModel)
                                    <form method="post" action="{{ route('admin.products.model.destroy', $product) }}" x-data x-on:submit="confirm('Usunąć model 3D? Przycisk „Zobacz u siebie” zniknie z karty produktu.') || $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <button class="min-h-11 rounded-full px-3 text-[12.5px] text-error hover:underline">Usuń model<span class="sr-only">: {{ $product->name }}</span></button>
                                    </form>
                                @endif
                                <p class="min-w-0 basis-full text-[12px] leading-[1.5] text-hint">Plik GLB ze skanu (RealityScan, Polycam), najlepiej do 5 MB, w prawdziwej wielkości. Z modelem na karcie produktu pojawia się „Zobacz u siebie”.</p>
                                @foreach ($modelErrors as $message)
                                    <p role="alert" class="basis-full text-[13px] text-error">{{ $message }}</p>
                                @endforeach
                            </div>
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
