<x-admin::layout title="Ustawienia produktów" lead="To, co dotyczy wszystkich produktów naraz: duże zdjęcie na stronie głównej, ostatnie sztuki i narzut na ceny w euro.">
    <a href="{{ route('admin.products.index') }}" class="-mt-2 mb-3 inline-flex min-h-11 items-center gap-1.5 text-[13px] text-lead hover:text-ink">← Produkty</a>

    <div class="grid max-w-[720px] gap-[22px]">
        <section class="rounded-[4px] border border-line bg-cream px-[22px] py-6">
            <h2 class="mb-1 font-serif text-[23px] font-light">Strona główna</h2>
            <p class="mb-[18px] text-[13.5px] text-label">Duże zdjęcie na górze strony ciągnie z katalogu — wybierz, który produkt tam stoi. Cena aktualizuje się sama.</p>
            <form method="post" action="{{ route('admin.products.hero') }}" novalidate class="flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <div class="min-w-0 flex-[1_1_200px]">
                    <label for="hero-product" class="mb-1.5 block text-[13.5px] text-graphite">Produkt na dużym zdjęciu</label>
                    <select id="hero-product" name="home_hero_product" @error('home_hero_product') aria-invalid="true" @enderror class="field-control">
                        @foreach ($products as $product)
                            <option value="{{ $product->slug }}" @selected(old('home_hero_product', $heroSlug) === $product->slug)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                    @error('home_hero_product')
                        <p class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                    @enderror
                </div>
                <x-shared::field name="home_hero_badge" id="hero-badge" label="Etykieta" :value="old('home_hero_badge', $heroBadge)" placeholder="np. nowość" class="flex-[0_1_160px]" />
                <x-shared::button variant="secondary" size="md">Zapisz</x-shared::button>
            </form>
            <p class="mt-3 text-[12.5px] leading-[1.5] text-hint">Sekcja „Co teraz jest w pracowni” pokazuje cztery pierwsze widoczne produkty z listy — kolejność ustawiasz na liście produktów.</p>
        </section>

        <section id="ostatnie-sztuki" class="scroll-mt-6 rounded-[4px] border border-line bg-cream px-[22px] py-6">
            <h2 class="mb-1 font-serif text-[23px] font-light">Ostatnie sztuki</h2>
            <p class="mb-[18px] text-[13.5px] text-label">Kafelek na „Na dziś” liczy rozmiary wyprzedane i te, których zostało tyle sztuk albo mniej. Sztuki jednorazowe się nie liczą.</p>
            <form method="post" action="{{ route('admin.products.last-pieces') }}" novalidate class="flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <x-shared::field name="last_pieces_threshold" id="last-pieces" label="Najwyżej tyle sztuk" :value="old('last_pieces_threshold', $lastPiecesThreshold)" inputmode="numeric" placeholder="np. 1" class="flex-[0_1_200px]" />
                <x-shared::button variant="secondary" size="md">Zapisz</x-shared::button>
            </form>
            <p class="mt-3 text-[12.5px] leading-[1.5] text-hint">1 to tylko ostatnia sztuka. Wpisz 2, jeśli chcesz wiedzieć wcześniej, co lepić.</p>
        </section>

        <section id="ceny-w-euro" class="scroll-mt-6 rounded-[4px] border border-line bg-cream px-[22px] py-6">
            <h2 class="mb-1 font-serif text-[23px] font-light">Ceny w euro</h2>
            <p class="mb-[18px] text-[13.5px] text-label">Dotyczy produktów z zaznaczonym „Cena w euro z kursu NBP”: cena w złotych po kursie, plus narzut, w górę do końcówki ,90.</p>
            <form method="post" action="{{ route('admin.products.euro-markup') }}" novalidate class="flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <x-shared::field name="euro_markup_percent" id="euro-markup" label="Narzut w procentach" :value="old('euro_markup_percent', $euroMarkup)" inputmode="numeric" placeholder="np. 15" class="flex-[0_1_200px]" />
                <x-shared::button variant="secondary" size="md">Zapisz</x-shared::button>
            </form>
            <p class="mt-3 text-[12.5px] leading-[1.5] text-hint">Puste pole to bez narzutu. Ceny w euro wpisane ręcznie zostają, jak są.</p>
        </section>
    </div>
</x-admin::layout>
